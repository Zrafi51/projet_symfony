<?php

namespace App\Controller;

use App\Entity\Comment;
use App\Entity\Image;
use App\Entity\Post;
use App\Entity\PostLike;
use App\Entity\Reaction;
use App\Entity\Video;
use App\Form\CommentType;
use App\Form\PostType;
use App\Repository\CommentRepository;
use App\Repository\FollowRepository;
use App\Repository\MusicRepository;
use App\Repository\PostLikeRepository;
use App\Repository\PostRepository;
use App\Repository\ReactionRepository;
use App\Repository\ForumUserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

class ForumController extends AbstractController
{
    #[Route('/social/', name: 'forum_home')]
    public function index(): Response
    {
        return $this->redirectToRoute('forum_forum');
    }

    #[Route('/social/forum', name: 'forum_forum')]
    public function forum(
        Request $request,
        PostRepository $postRepository,
        ForumUserRepository $userRepository,
        PostLikeRepository $postLikeRepository,
        MusicRepository $musicRepository,
        FollowRepository $followRepository
    ): Response {
        $keyword = $request->query->get('keyword');
        $dateFrom = $request->query->get('date_from');
        $dateTo = $request->query->get('date_to');
        $minLikes = $request->query->get('min_likes');
        $sortBy = $request->query->get('sort', 'recent');
        // Tab: "following" = posts from people I follow, "discover" = public accounts I don't.
        $tab = $request->query->get('tab', 'following');
        if (!in_array($tab, ['following', 'discover'], true)) { $tab = 'following'; }

        $dateFromObj = $dateFrom ? new \DateTime($dateFrom) : null;
        $dateToObj = $dateTo ? new \DateTime($dateTo) : null;
        $minLikesInt = $minLikes ? (int) $minLikes : null;

        $me = $this->getUser() ? $this->getUser()->getUserIdentifier() : null;

        // Build the author whitelist / blacklist per tab.
        // - following: only posts by people I follow (+ my own posts).
        // - discover:  everyone public I DON'T follow, excluding me. Private
        //              users' posts are excluded regardless — they're only
        //              reachable via direct username search.
        $includeAuthors = null;
        $excludeAuthors = [];
        if ($me) {
            $following = $followRepository->getFollowingUsernames($me);
            if ($tab === 'following') {
                $includeAuthors = array_values(array_unique(array_merge($following, [$me])));
            } else {
                $privateUsernames = $userRepository->getPrivateUsernames();
                $excludeAuthors = array_values(array_unique(array_merge($following, [$me], $privateUsernames)));
            }
        }

        $posts = $postRepository->searchPosts(
            $keyword, $dateFromObj, $dateToObj, $minLikesInt, $sortBy,
            $includeAuthors, $excludeAuthors
        );

        // Build username => profilePhotoPath map for feed avatars (posts + comments)
        $usernames = [];
        foreach ($posts as $p) {
            if ($p->getAuteur()) { $usernames[$p->getAuteur()] = true; }
            foreach ($p->getComments() as $c) {
                if ($c->getAuteur()) { $usernames[$c->getAuteur()] = true; }
            }
        }
        $authorPhotos = $userRepository->getPhotoMapByUsernames(array_keys($usernames));

        $post = new Post();
        $form = $this->createForm(PostType::class, $post);

        // Posts the current user has already liked, so the template can render
        // a filled heart / "liked" state on the like button.
        $likedPostIds = $this->getUser()
            ? $postLikeRepository->getLikedPostIdsForUser($this->getUser())
            : [];

        // Build id => audio-url map for the music picker preview (so JS can
        // play the selected track without an extra round-trip).
        $musicTracks = $musicRepository->findAllOrdered();
        $musicUrlsById = [];
        foreach ($musicTracks as $m) {
            $musicUrlsById[$m->getId()] = '/uploads/music/' . $m->getFilename();
        }

        // Search sidebar: when the keyword matches usernames (respecting
        // privacy), surface those accounts so a user can navigate directly
        // even if their posts aren't in the feed (private + not followed).
        $userMatches = [];
        if ($keyword && $me) {
            $userMatches = $userRepository->searchByUsername($keyword, 10);
        }

        return $this->render('social/forum/index.html.twig', [
            'posts' => $posts,
            'postForm' => $form->createView(),
            'keyword' => $keyword,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'min_likes' => $minLikes,
            'sort' => $sortBy,
            'tab' => $tab,
            'reaction_types' => Reaction::TYPES,
            'authorPhotos' => $authorPhotos,
            'likedPostIds' => $likedPostIds,
            'musicUrlsById' => $musicUrlsById,
            'userMatches' => $userMatches,
        ]);
    }

    #[Route('/social/forum/post/new', name: 'forum_post_new', methods: ['POST'])]
    public function newPost(
        Request $request,
        EntityManagerInterface $em,
        SluggerInterface $slugger
    ): Response {
        // Where to send the user after the submit. The hidden <input name="return_to">
        // on the form tells us whether they posted from "Mon Profil" or the feed.
        $returnTo = $request->request->get('return_to') === 'profile'
            ? 'forum_profile'
            : 'forum_forum';

        $post = new Post();
        $form = $this->createForm(PostType::class, $post);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $postsDir = $this->getParameter('posts_directory');
            $videosDir = $this->getParameter('videos_directory');

            /** @var \Symfony\Component\HttpFoundation\File\UploadedFile[] $imageFiles */
            $imageFiles = $form->get('imageFiles')->getData() ?? [];
            /** @var \Symfony\Component\HttpFoundation\File\UploadedFile[] $videoFilesPreCheck */
            $videoFilesPreCheck = $form->get('videoFiles')->getData() ?? [];

            // Cross-field rule: a publication must carry at least one media item
            // (image OR video). Both being empty is invalid.
            if (empty($imageFiles) && empty($videoFilesPreCheck)) {
                $this->addFlash('error', 'Veuillez ajouter au moins une image ou une vidéo.');
                return $this->redirectToRoute($returnTo);
            }

            $position = 0;
            foreach ($imageFiles as $imageFile) {
                $newFilename = $this->uploadSingleImage($imageFile, $postsDir, $slugger);
                if ($newFilename === null) {
                    $this->addFlash('warning', 'Une image n\'a pas pu être téléchargée.');
                    continue;
                }
                // First uploaded image becomes the cover photo.
                if ($position === 0) {
                    $post->setCheminPhoto($newFilename);
                }
                $image = (new Image())
                    ->setFilename($newFilename)
                    ->setPosition($position);
                $post->addImage($image);
                $position++;
            }

            // Handle optional videos — stored in uploads/videos.
            $videoFiles = $videoFilesPreCheck;
            $vPos = 0;
            $videoCount = 0;
            foreach ($videoFiles as $videoFile) {
                $newFilename = $this->uploadSingleImage($videoFile, $videosDir, $slugger);
                if ($newFilename === null) {
                    $this->addFlash('warning', 'Une vidéo n\'a pas pu être téléchargée.');
                    continue;
                }
                $video = (new Video())
                    ->setFilename($newFilename)
                    ->setPosition($vPos);
                $post->addVideo($video);
                $vPos++;
                $videoCount++;
            }

            $post->setAuteur($this->getUser());
            $em->persist($post);
            $em->flush();

            $parts = [];
            if ($position > 0)   { $parts[] = $position . ' image(s)'; }
            if ($videoCount > 0) { $parts[] = $videoCount . ' vidéo(s)'; }
            if ($post->getMusic()) { $parts[] = 'musique'; }
            $this->addFlash('success', 'Publication créée' . ($parts ? ' avec ' . implode(' + ', $parts) : '') . ' !');
        } elseif ($form->isSubmitted()) {
            // JS should catch most errors; any that reach here are server-only (file size, mime, etc.)
            foreach ($form->getErrors(true) as $error) {
                $this->addFlash('error', $error->getMessage());
            }
        }

        return $this->redirectToRoute($returnTo);
    }

    #[Route('/social/forum/post/{id}/edit', name: 'forum_post_edit')]
    public function editPost(
        Post $post,
        Request $request,
        EntityManagerInterface $em,
        SluggerInterface $slugger,
        MusicRepository $musicRepository
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_USER');
        if ($post->getAuteur() !== $this->getUser()->getUserIdentifier() && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException();
        }

        // Where to bounce back after save — preserves the feed vs. profile context.
        $returnTo = $request->query->get('return_to') === 'profile'
            ? 'forum_profile'
            : 'forum_forum';
        // The form also carries a hidden field on submit so POST knows it too.
        if ($request->isMethod('POST') && $request->request->get('return_to') === 'profile') {
            $returnTo = 'forum_profile';
        }

        $form = $this->createForm(PostType::class, $post, ['is_new' => false]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $postsDir = $this->getParameter('posts_directory');
            $videosDir = $this->getParameter('videos_directory');

            /** @var \Symfony\Component\HttpFoundation\File\UploadedFile[] $newImageFiles */
            $newImageFiles = $form->get('imageFiles')->getData() ?? [];
            /** @var \Symfony\Component\HttpFoundation\File\UploadedFile[] $newVideoFiles */
            $newVideoFiles = $form->get('videoFiles')->getData() ?? [];

            // —— Ordered media manifests ——
            // The client sends a JSON array per media type:
            //   [ "existing:5", "new:0", "existing:2" ]
            // - existing:<id>  → keep this media, position = index in array
            // - new:<i>        → take newImageFiles[i], upload it, position = index
            // Any existing id NOT in the list is DELETED (including its file).
            $imageOrder = $this->parseOrderManifest((string) $request->request->get('image_order', '[]'));
            $videoOrder = $this->parseOrderManifest((string) $request->request->get('video_order', '[]'));

            // —— Apply image ordering / deletions / additions ——
            $this->applyMediaOrdering(
                $post->getImages()->toArray(),
                $imageOrder,
                $newImageFiles,
                $postsDir,
                $slugger,
                function (array $kept) use ($post) {
                    // Delete the images that were dropped.
                    foreach ($post->getImages()->toArray() as $img) {
                        if (!in_array($img, $kept, true)) {
                            $file = $this->getParameter('posts_directory') . '/' . $img->getFilename();
                            if (is_file($file)) { @unlink($file); }
                            $post->removeImage($img);
                        }
                    }
                },
                function (string $filename, int $pos) use ($post) {
                    $img = (new Image())->setFilename($filename)->setPosition($pos);
                    $post->addImage($img);
                }
            );

            // Keep the legacy cover photo (chemin_photo) in sync with the new
            // first image so older templates that still read cheminPhoto work.
            $firstImg = null;
            foreach ($post->getImages() as $img) {
                if ($firstImg === null || $img->getPosition() < $firstImg->getPosition()) {
                    $firstImg = $img;
                }
            }
            $post->setCheminPhoto($firstImg ? $firstImg->getFilename() : '');

            // —— Apply video ordering / deletions / additions ——
            $this->applyMediaOrdering(
                $post->getVideos()->toArray(),
                $videoOrder,
                $newVideoFiles,
                $videosDir,
                $slugger,
                function (array $kept) use ($post) {
                    foreach ($post->getVideos()->toArray() as $vid) {
                        if (!in_array($vid, $kept, true)) {
                            $file = $this->getParameter('videos_directory') . '/' . $vid->getFilename();
                            if (is_file($file)) { @unlink($file); }
                            $post->removeVideo($vid);
                        }
                    }
                },
                function (string $filename, int $pos) use ($post) {
                    $vid = (new Video())->setFilename($filename)->setPosition($pos);
                    $post->addVideo($vid);
                }
            );

            // A post must still have at least one image OR video after the edit.
            if ($post->getImages()->isEmpty() && $post->getVideos()->isEmpty()) {
                $this->addFlash('error', 'La publication doit contenir au moins une image ou une vidéo.');
            } else {
                $em->flush();
                $this->addFlash('success', 'Publication modifiée !');
                return $this->redirectToRoute($returnTo, ['_fragment' => 'post-' . $post->getId()]);
            }
        } elseif ($form->isSubmitted()) {
            foreach ($form->getErrors(true) as $error) {
                $this->addFlash('error', $error->getMessage());
            }
        }

        // id => audio-url map for the music picker preview.
        $musicUrlsById = [];
        foreach ($musicRepository->findAllOrdered() as $m) {
            $musicUrlsById[$m->getId()] = '/uploads/music/' . $m->getFilename();
        }

        return $this->render('social/forum/edit.html.twig', [
            'post' => $post,
            'form' => $form->createView(),
            'returnTo' => $returnTo === 'forum_profile' ? 'profile' : 'forum',
            'musicUrlsById' => $musicUrlsById,
        ]);
    }

    /**
     * Parses an "image_order" / "video_order" manifest posted from the edit
     * form. Accepts JSON array of strings like "existing:12" or "new:0".
     * Silently returns [] on malformed input — the caller then treats the post
     * as "no reorder info", effectively a no-op that preserves existing media.
     *
     * @return array<int, array{kind: 'existing'|'new', ref: int}>
     */
    private function parseOrderManifest(string $json): array
    {
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) { return []; }
        $out = [];
        foreach ($decoded as $token) {
            if (!is_string($token) || !str_contains($token, ':')) { continue; }
            [$kind, $ref] = explode(':', $token, 2);
            if ($kind !== 'existing' && $kind !== 'new') { continue; }
            if (!ctype_digit($ref)) { continue; }
            $out[] = ['kind' => $kind, 'ref' => (int) $ref];
        }
        return $out;
    }

    /**
     * Core re-ordering routine shared by images and videos:
     *   - Resolves each manifest token to either an existing entity (looked up
     *     by id inside $existing) or a freshly uploaded file from $newFiles.
     *   - Assigns the final `position` = manifest index.
     *   - Invokes $deleteDropped with the list of entities that SURVIVED, so
     *     the caller can delete the rest (and unlink their files).
     *   - Invokes $addNew($filename, $position) for every uploaded file.
     *
     * If the manifest is empty (client didn't send one, or JSON was malformed),
     * existing media is left untouched and new files are appended at the end.
     *
     * @param list<Image|Video>                                       $existing
     * @param list<array{kind: 'existing'|'new', ref: int}>           $order
     * @param list<\Symfony\Component\HttpFoundation\File\UploadedFile> $newFiles
     */
    private function applyMediaOrdering(
        array $existing,
        array $order,
        array $newFiles,
        string $targetDir,
        SluggerInterface $slugger,
        callable $deleteDropped,
        callable $addNew
    ): void {
        // Index existing entities by id for O(1) lookup from the manifest.
        $byId = [];
        foreach ($existing as $e) { $byId[$e->getId()] = $e; }

        if (empty($order)) {
            // No manifest — keep existing intact, append new files at the end.
            $maxPos = -1;
            foreach ($existing as $e) { $maxPos = max($maxPos, $e->getPosition()); }
            $pos = $maxPos + 1;
            foreach ($newFiles as $file) {
                $fn = $this->uploadSingleImage($file, $targetDir, $slugger);
                if ($fn !== null) { $addNew($fn, $pos++); }
            }
            return;
        }

        // Walk the manifest, applying positions and uploading new files.
        $kept = [];
        foreach ($order as $idx => $tok) {
            if ($tok['kind'] === 'existing' && isset($byId[$tok['ref']])) {
                $entity = $byId[$tok['ref']];
                $entity->setPosition($idx);
                $kept[] = $entity;
            } elseif ($tok['kind'] === 'new' && isset($newFiles[$tok['ref']])) {
                $file = $newFiles[$tok['ref']];
                $fn = $this->uploadSingleImage($file, $targetDir, $slugger);
                if ($fn !== null) { $addNew($fn, $idx); }
            }
        }
        // Anything existing but not kept → caller deletes it.
        $deleteDropped($kept);
    }

    #[Route('/social/forum/post/{id}/delete', name: 'forum_post_delete', methods: ['POST'])]
    public function deletePost(Post $post, Request $request, EntityManagerInterface $em): Response
    {
        if ($post->getAuteur() !== $this->getUser()->getUserIdentifier() && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException();
        }

        if ($this->isCsrfTokenValid('delete' . $post->getId(), $request->request->get('_token'))) {
            if ($post->getCheminPhoto()) {
                $file = $this->getParameter('posts_directory') . '/' . $post->getCheminPhoto();
                if (file_exists($file)) {
                    unlink($file);
                }
            }
            $em->remove($post);
            $em->flush();
            $this->addFlash('success', 'Publication supprimée.');
        }

        return $this->redirectToRoute('forum_forum');
    }

    /**
     * Toggle the current user's like on a post.
     *
     * One like per (user, post) is enforced via the `post_likes` table's unique
     * constraint. Clicking again removes the like. The cached `likes` counter
     * on Post is incremented/decremented to stay in sync without recounting.
     */
    #[Route('/social/forum/post/{id}/like', name: 'forum_post_like', methods: ['POST'])]
    public function likePost(
        Post $post,
        EntityManagerInterface $em,
        PostLikeRepository $postLikeRepo
    ): JsonResponse {
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['error' => 'Vous devez être connecté.'], 401);
        }

        $existing = $postLikeRepo->findByPostAndUser($post, $user);

        if ($existing) {
            // Toggle off: the user had already liked this post.
            $em->remove($existing);
            $post->setLikes(max(0, $post->getLikes() - 1));
            $em->flush();

            return new JsonResponse([
                'liked' => false,
                'likes' => $post->getLikes(),
            ]);
        }

        // Toggle on: first time this user likes this post.
        $like = (new PostLike())
            ->setPost($post)
            ->setUsername($user);
        $em->persist($like);
        $post->setLikes($post->getLikes() + 1);
        $em->flush();

        return new JsonResponse([
            'liked' => true,
            'likes' => $post->getLikes(),
        ]);
    }

    #[Route('/social/forum/post/{id}/react', name: 'forum_post_react', methods: ['POST'])]
    public function react(
        Post $post,
        Request $request,
        EntityManagerInterface $em,
        ReactionRepository $reactionRepo
    ): JsonResponse {
        $user = $this->getUser();
        $reactionType = $request->request->get('reaction_type');

        if (!array_key_exists($reactionType, Reaction::TYPES)) {
            return new JsonResponse(['error' => 'Type de réaction invalide'], 400);
        }

        $existing = $reactionRepo->findByPostAndUser($post, $user);

        if ($existing) {
            if ($existing->getReactionType() === $reactionType) {
                $em->remove($existing);
                $em->flush();
                return new JsonResponse([
                    'action' => 'removed',
                    'counts' => $reactionRepo->getReactionCountsForPost($post),
                    'total' => $post->getReactions()->count(),
                ]);
            }
            $existing->setReactionType($reactionType);
        } else {
            $reaction = new Reaction();
            $reaction->setPost($post);
            $reaction->setUser($user);
            $reaction->setReactionType($reactionType);
            $em->persist($reaction);
        }

        $em->flush();

        return new JsonResponse([
            'action' => 'added',
            'counts' => $reactionRepo->getReactionCountsForPost($post),
            'total' => $post->getReactions()->count(),
            'userReaction' => $reactionType,
        ]);
    }

    #[Route('/social/forum/post/{id}/comment', name: 'forum_post_comment', methods: ['POST'])]
    public function addComment(
        Post $post,
        Request $request,
        EntityManagerInterface $em
    ): Response {
        $comment = new Comment();
        $form = $this->createForm(CommentType::class, $comment);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $comment->setPost($post);
            $comment->setAuteur($this->getUser());
            $em->persist($comment);
            $em->flush();
            $this->addFlash('success', 'Commentaire ajouté !');
        }

        return $this->redirectToRoute('forum_forum', ['_fragment' => 'post-' . $post->getId()]);
    }

    #[Route('/social/forum/comment/{id}/delete', name: 'forum_comment_delete', methods: ['POST'])]
    public function deleteComment(Comment $comment, Request $request, EntityManagerInterface $em): Response
    {
        $postId = $comment->getPost()->getId();

        if ($comment->getAuteur() !== $this->getUser()->getUserIdentifier() && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException();
        }

        if ($this->isCsrfTokenValid('delete_comment' . $comment->getId(), $request->request->get('_token'))) {
            $em->remove($comment);
            $em->flush();
            $this->addFlash('success', 'Commentaire supprimé.');
        }

        return $this->redirectToRoute('forum_forum', ['_fragment' => 'post-' . $postId]);
    }

    #[Route('/social/forum/post/{id}', name: 'forum_post_show')]
    public function showPost(Post $post, ReactionRepository $reactionRepo): Response
    {
        $userReaction = null;
        if ($this->getUser()) {
            $existing = $reactionRepo->findByPostAndUser($post, $this->getUser());
            $userReaction = $existing?->getReactionType();
        }

        $commentForm = $this->createForm(CommentType::class, new Comment());

        return $this->render('social/forum/show.html.twig', [
            'post' => $post,
            'userReaction' => $userReaction,
            'reactionCounts' => $reactionRepo->getReactionCountsForPost($post),
            'commentForm' => $commentForm->createView(),
            'reaction_types' => Reaction::TYPES,
        ]);
    }

    /**
     * Moves an uploaded image file to the posts directory with a unique name.
     * Returns the generated filename, or null on failure.
     */
    private function uploadSingleImage(
        \Symfony\Component\HttpFoundation\File\UploadedFile $file,
        string $targetDir,
        SluggerInterface $slugger
    ): ?string {
        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $slugger->slug($originalFilename);
        $newFilename = $safeFilename . '-' . uniqid() . '.' . $file->guessExtension();

        try {
            $file->move($targetDir, $newFilename);
            return $newFilename;
        } catch (FileException $e) {
            return null;
        }
    }
}
