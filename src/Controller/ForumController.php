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
use App\Repository\ImageRepository;
use App\Repository\MusicRepository;
use App\Repository\PostLikeRepository;
use App\Repository\PostRepository;
use App\Repository\ReactionRepository;
use App\Repository\UserRepository;
use App\Repository\VideoRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

class ForumController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(): Response
    {
        return $this->redirectToRoute('app_forum');
    }

    #[Route('/forum', name: 'app_forum')]
    public function forum(
        Request $request,
        PostRepository $postRepository,
        UserRepository $userRepository,
        PostLikeRepository $postLikeRepository,
        MusicRepository $musicRepository,
        FollowRepository $followRepository
    ): Response {
        $keyword = $request->query->get('keyword');
        $dateFrom = $request->query->get('date_from');
        $dateTo = $request->query->get('date_to');
        $minLikes = $request->query->get('min_likes');
        $sortBy = $request->query->get('sort', 'recent');
        $tab = $request->query->get('tab', 'following');
        if (!in_array($tab, ['following', 'discover'], true)) { $tab = 'following'; }

        $dateFromObj = $dateFrom ? new \DateTime($dateFrom) : null;
        $dateToObj = $dateTo ? new \DateTime($dateTo) : null;
        $minLikesInt = $minLikes ? (int) $minLikes : null;

        $me = $this->getUser() ? $this->getUser()->getUserIdentifier() : null;

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

        $likedPostIds = $this->getUser()
            ? $postLikeRepository->getLikedPostIdsForUser($this->getUser())
            : [];

        $musicTracks = $musicRepository->findAllOrdered();
        $musicUrlsById = [];
        foreach ($musicTracks as $m) {
            $musicUrlsById[$m->getId()] = '/uploads/music/' . $m->getFilename();
        }

        $userMatches = [];
        if ($keyword && $me) {
            $userMatches = $userRepository->searchByUsername($keyword, 10);
        }

        return $this->render('forum/index.html.twig', [
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

    #[Route('/forum/post/new', name: 'app_post_new', methods: ['POST'])]
    public function newPost(
        Request $request,
        PostRepository $postRepository,
        ImageRepository $imageRepository,
        VideoRepository $videoRepository,
        SluggerInterface $slugger
    ): Response {
        $returnTo = $request->request->get('return_to') === 'profile'
            ? 'app_profile'
            : 'app_forum';

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
                if ($position === 0) {
                    $post->setCheminPhoto($newFilename);
                }
                $image = (new Image())
                    ->setFilename($newFilename)
                    ->setPosition($position);
                $post->addImage($image);
                $position++;
            }

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
            $postRepository->saveWithMedia($post, $imageRepository, $videoRepository);

            $parts = [];
            if ($position > 0)   { $parts[] = $position . ' image(s)'; }
            if ($videoCount > 0) { $parts[] = $videoCount . ' vidéo(s)'; }
            if ($post->getMusic()) { $parts[] = 'musique'; }
            $this->addFlash('success', 'Publication créée' . ($parts ? ' avec ' . implode(' + ', $parts) : '') . ' !');
        } elseif ($form->isSubmitted()) {
            foreach ($form->getErrors(true) as $error) {
                $this->addFlash('error', $error->getMessage());
            }
        }

        return $this->redirectToRoute($returnTo);
    }

    #[Route('/forum/post/{id}/edit', name: 'app_post_edit')]
    public function editPost(
        int $id,
        Request $request,
        PostRepository $postRepository,
        ImageRepository $imageRepository,
        VideoRepository $videoRepository,
        SluggerInterface $slugger,
        MusicRepository $musicRepository
    ): Response {
        $post = $postRepository->find($id) ?? throw $this->createNotFoundException();
        $this->denyAccessUnlessGranted('ROLE_USER');
        if ($post->getAuteur() !== $this->getUser()->getUserIdentifier() && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException();
        }

        $returnTo = $request->query->get('return_to') === 'profile'
            ? 'app_profile'
            : 'app_forum';
        if ($request->isMethod('POST') && $request->request->get('return_to') === 'profile') {
            $returnTo = 'app_profile';
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

            $imageOrder = $this->parseOrderManifest((string) $request->request->get('image_order', '[]'));
            $videoOrder = $this->parseOrderManifest((string) $request->request->get('video_order', '[]'));

            // Capture original image/video lists so we can compute deletions.
            $originalImages = $post->getImages()->toArray();
            $originalVideos = $post->getVideos()->toArray();

            $this->applyMediaOrdering(
                $originalImages,
                $imageOrder,
                $newImageFiles,
                $postsDir,
                $slugger,
                function (array $kept) use ($post, $originalImages, $imageRepository) {
                    foreach ($originalImages as $img) {
                        if (!in_array($img, $kept, true)) {
                            $file = $this->getParameter('posts_directory') . '/' . $img->getFilename();
                            if (is_file($file)) { @unlink($file); }
                            $imageRepository->remove($img);
                            $post->removeImage($img);
                        }
                    }
                },
                function (string $filename, int $pos) use ($post) {
                    $img = (new Image())->setFilename($filename)->setPosition($pos);
                    $post->addImage($img);
                }
            );

            $firstImg = null;
            foreach ($post->getImages() as $img) {
                if ($firstImg === null || $img->getPosition() < $firstImg->getPosition()) {
                    $firstImg = $img;
                }
            }
            $post->setCheminPhoto($firstImg ? $firstImg->getFilename() : '');

            $this->applyMediaOrdering(
                $originalVideos,
                $videoOrder,
                $newVideoFiles,
                $videosDir,
                $slugger,
                function (array $kept) use ($post, $originalVideos, $videoRepository) {
                    foreach ($originalVideos as $vid) {
                        if (!in_array($vid, $kept, true)) {
                            $file = $this->getParameter('videos_directory') . '/' . $vid->getFilename();
                            if (is_file($file)) { @unlink($file); }
                            $videoRepository->remove($vid);
                            $post->removeVideo($vid);
                        }
                    }
                },
                function (string $filename, int $pos) use ($post) {
                    $vid = (new Video())->setFilename($filename)->setPosition($pos);
                    $post->addVideo($vid);
                }
            );

            if ($post->getImages()->isEmpty() && $post->getVideos()->isEmpty()) {
                $this->addFlash('error', 'La publication doit contenir au moins une image ou une vidéo.');
            } else {
                $postRepository->saveWithMedia($post, $imageRepository, $videoRepository);
                $this->addFlash('success', 'Publication modifiée !');
                return $this->redirectToRoute($returnTo, ['_fragment' => 'post-' . $post->getId()]);
            }
        } elseif ($form->isSubmitted()) {
            foreach ($form->getErrors(true) as $error) {
                $this->addFlash('error', $error->getMessage());
            }
        }

        $musicUrlsById = [];
        foreach ($musicRepository->findAllOrdered() as $m) {
            $musicUrlsById[$m->getId()] = '/uploads/music/' . $m->getFilename();
        }

        return $this->render('forum/edit.html.twig', [
            'post' => $post,
            'form' => $form->createView(),
            'returnTo' => $returnTo === 'app_profile' ? 'profile' : 'forum',
            'musicUrlsById' => $musicUrlsById,
        ]);
    }

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

    private function applyMediaOrdering(
        array $existing,
        array $order,
        array $newFiles,
        string $targetDir,
        SluggerInterface $slugger,
        callable $deleteDropped,
        callable $addNew
    ): void {
        $byId = [];
        foreach ($existing as $e) { $byId[$e->getId()] = $e; }

        if (empty($order)) {
            $maxPos = -1;
            foreach ($existing as $e) { $maxPos = max($maxPos, $e->getPosition()); }
            $pos = $maxPos + 1;
            foreach ($newFiles as $file) {
                $fn = $this->uploadSingleImage($file, $targetDir, $slugger);
                if ($fn !== null) { $addNew($fn, $pos++); }
            }
            return;
        }

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
        $deleteDropped($kept);
    }

    #[Route('/forum/post/{id}/delete', name: 'app_post_delete', methods: ['POST'])]
    public function deletePost(int $id, Request $request, PostRepository $postRepository): Response
    {
        $post = $postRepository->find($id) ?? throw $this->createNotFoundException();
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
            $postRepository->remove($post);
            $this->addFlash('success', 'Publication supprimée.');
        }

        return $this->redirectToRoute('app_forum');
    }

    #[Route('/forum/post/{id}/like', name: 'app_post_like', methods: ['POST'])]
    public function likePost(
        int $id,
        PostRepository $postRepository,
        PostLikeRepository $postLikeRepo
    ): JsonResponse {
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['error' => 'Vous devez être connecté.'], 401);
        }
        $post = $postRepository->find($id) ?? throw $this->createNotFoundException();

        $existing = $postLikeRepo->findByPostAndUser($post, $user);

        if ($existing) {
            $postLikeRepo->remove($existing);
            $post->setLikes(max(0, $post->getLikes() - 1));
            $postRepository->save($post);

            return new JsonResponse([
                'liked' => false,
                'likes' => $post->getLikes(),
            ]);
        }

        $like = (new PostLike())
            ->setPost($post)
            ->setUsername($user);
        $postLikeRepo->save($like);
        $post->setLikes($post->getLikes() + 1);
        $postRepository->save($post);

        return new JsonResponse([
            'liked' => true,
            'likes' => $post->getLikes(),
        ]);
    }

    #[Route('/forum/post/{id}/react', name: 'app_post_react', methods: ['POST'])]
    public function react(
        int $id,
        Request $request,
        PostRepository $postRepository,
        ReactionRepository $reactionRepo
    ): JsonResponse {
        $post = $postRepository->find($id) ?? throw $this->createNotFoundException();
        $user = $this->getUser();
        $reactionType = $request->request->get('reaction_type');

        if (!array_key_exists($reactionType, Reaction::TYPES)) {
            return new JsonResponse(['error' => 'Type de réaction invalide'], 400);
        }

        $existing = $reactionRepo->findByPostAndUser($post, $user);

        if ($existing) {
            if ($existing->getReactionType() === $reactionType) {
                $reactionRepo->remove($existing);
                // Reload post to refresh reactions collection count.
                $post = $postRepository->find($id);
                return new JsonResponse([
                    'action' => 'removed',
                    'counts' => $reactionRepo->getReactionCountsForPost($post),
                    'total' => $post->getReactions()->count(),
                ]);
            }
            $existing->setReactionType($reactionType);
            $reactionRepo->save($existing);
        } else {
            $reaction = new Reaction();
            $reaction->setPost($post);
            $reaction->setUser($user);
            $reaction->setReactionType($reactionType);
            $reactionRepo->save($reaction);
        }

        $post = $postRepository->find($id);

        return new JsonResponse([
            'action' => 'added',
            'counts' => $reactionRepo->getReactionCountsForPost($post),
            'total' => $post->getReactions()->count(),
            'userReaction' => $reactionType,
        ]);
    }

    #[Route('/forum/post/{id}/comment', name: 'app_post_comment', methods: ['POST'])]
    public function addComment(
        int $id,
        Request $request,
        PostRepository $postRepository,
        CommentRepository $commentRepository,
        UserRepository $userRepository
    ): Response {
        $post = $postRepository->find($id) ?? throw $this->createNotFoundException();
        $comment = new Comment();
        $form = $this->createForm(CommentType::class, $comment);
        $form->handleRequest($request);

        // AJAX submissions get a JSON response so the JS can append the new
        // comment to the DOM without a full page reload.
        $isAjax = $request->isXmlHttpRequest()
            || str_contains((string) $request->headers->get('Accept'), 'application/json');

        if ($form->isSubmitted() && $form->isValid()) {
            $comment->setPost($post);
            $comment->setAuteur($this->getUser());
            $commentRepository->save($comment);

            if ($isAjax) {
                $username = $this->getUser()->getUserIdentifier();
                $photoMap = $userRepository->getPhotoMapByUsernames([$username]);
                $photo = $photoMap[$username] ?? null;

                return new JsonResponse([
                    'ok'             => true,
                    'id'             => $comment->getId(),
                    'auteur'         => $username,
                    'contenu'        => $comment->getContenu(),
                    'dateFormatted'  => $comment->getDateCommentaire()->format('d/m/Y H:i'),
                    'authorPhoto'    => $photo,
                    'photoUrl'       => $photo ? '/uploads/profiles/' . $photo : null,
                    'deleteUrl'      => $this->generateUrl('app_comment_delete', ['id' => $comment->getId()]),
                    'deleteCsrf'     => $this->container->get('security.csrf.token_manager')
                                            ->getToken('delete_comment' . $comment->getId())->getValue(),
                    'totalComments'  => count($commentRepository->findByPost($post)),
                    'isAdmin'        => $this->isGranted('ROLE_ADMIN'),
                ]);
            }

            $this->addFlash('success', 'Commentaire ajouté !');
            return $this->redirectToRoute('app_forum', ['_fragment' => 'post-' . $post->getId()]);
        }

        // Validation failed
        $errors = [];
        foreach ($form->getErrors(true) as $err) {
            $errors[] = $err->getMessage();
        }

        if ($isAjax) {
            return new JsonResponse([
                'ok'     => false,
                'errors' => $errors ?: ['Commentaire invalide.'],
            ], 400);
        }

        foreach ($errors as $msg) {
            $this->addFlash('error', $msg);
        }
        return $this->redirectToRoute('app_forum', ['_fragment' => 'post-' . $post->getId()]);
    }

    #[Route('/forum/comment/{id}/delete', name: 'app_comment_delete', methods: ['POST'])]
    public function deleteComment(int $id, Request $request, CommentRepository $commentRepository): Response
    {
        $comment = $commentRepository->find($id) ?? throw $this->createNotFoundException();
        $postId = $comment->getPostId();

        if ($comment->getAuteur() !== $this->getUser()->getUserIdentifier() && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException();
        }

        if ($this->isCsrfTokenValid('delete_comment' . $comment->getId(), $request->request->get('_token'))) {
            $commentRepository->remove($comment);
            $this->addFlash('success', 'Commentaire supprimé.');
        }

        return $this->redirectToRoute('app_forum', ['_fragment' => 'post-' . $postId]);
    }

    #[Route('/forum/post/{id}', name: 'app_post_show')]
    public function showPost(int $id, PostRepository $postRepository, ReactionRepository $reactionRepo): Response
    {
        $post = $postRepository->find($id) ?? throw $this->createNotFoundException();
        $userReaction = null;
        if ($this->getUser()) {
            $existing = $reactionRepo->findByPostAndUser($post, $this->getUser());
            $userReaction = $existing?->getReactionType();
        }

        $commentForm = $this->createForm(CommentType::class, new Comment());

        return $this->render('forum/show.html.twig', [
            'post' => $post,
            'userReaction' => $userReaction,
            'reactionCounts' => $reactionRepo->getReactionCountsForPost($post),
            'commentForm' => $commentForm->createView(),
            'reaction_types' => Reaction::TYPES,
        ]);
    }

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
