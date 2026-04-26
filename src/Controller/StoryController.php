<?php

namespace App\Controller;

use App\Entity\Message;
use App\Entity\Story;
use App\Entity\StoryLike;
use App\Repository\FollowRepository;
use App\Repository\MusicRepository;
use App\Repository\StoryLikeRepository;
use App\Repository\StoryRepository;
use App\Repository\StoryViewRepository;
use App\Repository\ForumUserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * Stories (24h ephemeral image/video + optional background music).
 *
 * Three public endpoints:
 *   - GET/POST /story/new           → upload form + handler
 *   - GET      /story/user/{username} → fullscreen viewer for all that user's active stories
 *   - POST     /story/{id}/delete   → owner-only deletion
 *
 * Plus `bar()` which is rendered inline from the feed template via
 * {{ render(controller('App\\\\Controller\\\\Forum\\\\StoryController::bar')) }} and
 * returns the Facebook-style carousel.
 */
class StoryController extends AbstractController
{
    private const IMAGE_MIMES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    private const VIDEO_MIMES = ['video/mp4', 'video/webm', 'video/ogg', 'video/quicktime', 'video/x-matroska'];
    private const MAX_IMAGE_BYTES = 8 * 1024 * 1024;   // 8 MB
    private const MAX_VIDEO_BYTES = 50 * 1024 * 1024;  // 50 MB

    #[Route('/social/story/new', name: 'forum_story_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $em,
        SluggerInterface $slugger,
        MusicRepository $musicRepo
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_USER');

        if ($request->isMethod('POST')) {
            // CSRF
            if (!$this->isCsrfTokenValid('story_new', (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'Jeton CSRF invalide.');
                return $this->redirectToRoute('forum_story_new');
            }

            /** @var UploadedFile|null $file */
            $file = $request->files->get('media');
            if (!$file) {
                $this->addFlash('error', 'Veuillez choisir une image ou une vidéo.');
                return $this->redirectToRoute('forum_story_new');
            }

            // Detect kind from MIME and enforce the per-type size limit.
            $mime = $file->getMimeType();
            if (in_array($mime, self::IMAGE_MIMES, true)) {
                $kind = Story::TYPE_IMAGE;
                if ($file->getSize() > self::MAX_IMAGE_BYTES) {
                    $this->addFlash('error', 'L\'image ne doit pas dépasser 8 Mo.');
                    return $this->redirectToRoute('forum_story_new');
                }
            } elseif (in_array($mime, self::VIDEO_MIMES, true)) {
                $kind = Story::TYPE_VIDEO;
                if ($file->getSize() > self::MAX_VIDEO_BYTES) {
                    $this->addFlash('error', 'La vidéo ne doit pas dépasser 50 Mo.');
                    return $this->redirectToRoute('forum_story_new');
                }
            } else {
                $this->addFlash('error', 'Format non supporté. JPEG/PNG/GIF/WebP ou MP4/WebM/MOV.');
                return $this->redirectToRoute('forum_story_new');
            }

            // Move to /uploads/stories with a unique name.
            $original = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
            $safe     = $slugger->slug($original);
            $newName  = $safe . '-' . uniqid() . '.' . ($file->guessExtension() ?: 'bin');
            try {
                $file->move($this->getParameter('stories_directory'), $newName);
            } catch (FileException $e) {
                $this->addFlash('error', 'Erreur lors du téléchargement.');
                return $this->redirectToRoute('forum_story_new');
            }

            // Optional music from the playlist + optional start-offset (seconds).
            $musicId = $request->request->get('music_id');
            $music   = $musicId ? $musicRepo->find((int) $musicId) : null;
            $musicStartRaw = $request->request->get('music_start');
            $musicStart = ($music && $musicStartRaw !== null && $musicStartRaw !== '')
                ? max(0.0, (float) $musicStartRaw)
                : null;

            $story = (new Story())
                ->setAuteur($this->getUser())
                ->setMediaType($kind)
                ->setFilename($newName)
                ->setMusic($music)
                ->setMusicStart($musicStart);
            $em->persist($story);
            $em->flush();

            $this->addFlash('success', 'Story publiée — visible pendant 24 heures.');
            return $this->redirectToRoute('forum_forum');
        }

        return $this->render('social/stories/new.html.twig', [
            'musicTracks' => $musicRepo->findAllOrdered(),
        ]);
    }

    /**
     * Renders the Facebook-style carousel ("Créer une story" + friends' stories)
     * for embedding in the home feed. Called from templates via:
     *   {{ render(path('forum_story_bar')) }}
     */
    #[Route('/social/story/bar', name: 'forum_story_bar', methods: ['GET'])]
    public function bar(
        StoryRepository $stories,
        FollowRepository $follows,
        ForumUserRepository $users
    ): Response {
        $user = $this->getUser();
        if (!$user) {
            return new Response('');
        }
        $me = $user->getUserIdentifier();

        // Opportunistic cleanup of expired rows (cheap — indexed on expires_at).
        $stories->purgeExpired();

        // Pool of story authors to show: me + people I follow.
        $followingUsernames = $follows->getFollowingUsernames($me);
        $authors = array_values(array_unique(array_merge([$me], $followingUsernames)));

        $activeStories = $stories->findActiveByAuthors($authors);

        // Group by author so the carousel shows one tile per person (the most
        // recent thumbnail), and the viewer plays all that person's stories.
        $byAuthor = [];
        foreach ($activeStories as $s) {
            $a = $s->getAuteur();
            if (!isset($byAuthor[$a])) {
                $byAuthor[$a] = ['author' => $a, 'cover' => $s, 'count' => 0];
            }
            $byAuthor[$a]['count']++;
            // Keep the newest as the cover (findActiveByAuthors is DESC, so the first hit IS the newest).
        }

        // Put me first, then others in newest-story-first order (already the DB order).
        $myGroup = $byAuthor[$me] ?? null;
        unset($byAuthor[$me]);
        $groups = array_values($byAuthor);
        if ($myGroup !== null) {
            array_unshift($groups, $myGroup);
        }

        // Profile photos for the author avatars on each tile.
        $authorList = array_map(fn ($g) => $g['author'], $groups);
        $photoMap = $users->getPhotoMapByUsernames($authorList);

        return $this->render('social/_partials/stories_bar.html.twig', [
            'groups'   => $groups,
            'photoMap' => $photoMap,
            'me'       => $me,
            'hasMine'  => $myGroup !== null,
        ]);
    }

    /**
     * Fullscreen viewer — plays every active story from a single author in
     * publication order. Access control:
     *   - public accounts: anyone logged-in can view
     *   - private accounts: only the owner + accepted followers
     */
    #[Route('/social/story/user/{username}', name: 'forum_story_view', methods: ['GET'])]
    public function view(
        string $username,
        Request $request,
        StoryRepository $stories,
        ForumUserRepository $users,
        FollowRepository $follows,
        StoryLikeRepository $storyLikes
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $me = $this->getUser()->getUserIdentifier();
        $target = $users->findOneBy(['username' => $username]);
        if (!$target) {
            throw $this->createNotFoundException();
        }

        // Where to bounce back when the user closes the viewer or deletes the
        // last story. ?return=profile (own profile) / ?return=user (someone's
        // public profile page). Default = home feed.
        $returnParam = (string) $request->query->get('return', '');
        $returnUrl = match ($returnParam) {
            'profile' => $this->generateUrl('forum_profile'),
            'user'    => $this->generateUrl('forum_user_profile', ['username' => $username]),
            default   => $this->generateUrl('forum_forum'),
        };

        // Privacy guard: private accounts restrict their stories to accepted followers.
        if ($target->isPrivate() && $username !== $me && !$follows->isFollowing($me, $username)) {
            $this->addFlash('error', 'Ce compte est privé.');
            return $this->redirect($returnUrl);
        }

        $list = $stories->findActiveForUser($username);
        if (empty($list)) {
            $this->addFlash('info', 'Aucune story active.');
            return $this->redirect($returnUrl);
        }

        $authorPhoto = $users->getPhotoMapByUsernames([$username])[$username] ?? null;

        // Pre-compute the "is liked by me" boolean for every slide, plus the
        // like-count, so the heart in the viewer starts in the right state
        // without an extra round-trip.
        $likedMap  = $storyLikes->likedMapFor($list, $me);
        $likeCount = [];
        foreach ($list as $s) {
            $likeCount[$s->getId()] = $storyLikes->countForStory($s);
        }

        return $this->render('social/stories/show.html.twig', [
            'stories'     => $list,
            'author'      => $username,
            'authorPhoto' => $authorPhoto,
            'isOwner'     => $username === $me,
            'returnUrl'   => $returnUrl,
            'likedMap'    => $likedMap,   // { storyId: bool }
            'likeCount'   => $likeCount,  // { storyId: int }
        ]);
    }

    /**
     * AJAX: record that the current user has opened this story. Idempotent —
     * re-opening the same story won't create duplicate rows or duplicate
     * "X a vu votre story" notifications.
     */
    #[Route('/social/story/{id}/view', name: 'forum_story_view_record', methods: ['POST'])]
    public function recordView(Story $story, StoryViewRepository $views): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        $me = $this->getUser()->getUserIdentifier();
        $created = $views->recordOnce($story, $me);
        return new JsonResponse([
            'ok'      => true,
            'created' => $created, // true = first time this viewer opens it
        ]);
    }

    /**
     * AJAX: toggle the heart on a story. First hit creates the StoryLike,
     * second hit removes it. Returns the resulting state + count so the
     * viewer can refresh its button without reloading.
     */
    #[Route('/social/story/{id}/like', name: 'forum_story_like_toggle', methods: ['POST'])]
    public function toggleLike(
        Story $story,
        StoryLikeRepository $storyLikes,
        EntityManagerInterface $em
    ): JsonResponse {
        $this->denyAccessUnlessGranted('ROLE_USER');
        $me = $this->getUser()->getUserIdentifier();

        $existing = $storyLikes->findLike($story, $me);
        if ($existing) {
            $em->remove($existing);
            $em->flush();
            $liked = false;
        } else {
            $like = (new StoryLike())
                ->setStory($story)
                ->setLikerUsername($me);
            $em->persist($like);
            $em->flush();
            $liked = true;
        }
        return new JsonResponse([
            'liked' => $liked,
            'count' => $storyLikes->countForStory($story),
        ]);
    }

    /**
     * AJAX: reply to a story. Creates a regular DM with a `story_id`
     * attached, which the thread view renders as a story-thumbnail + text
     * bubble (Instagram-style). Returns JSON instead of redirecting so the
     * viewer stays open on the story.
     *
     * Enforces the same follow-gate as regular DMs — either side must follow
     * the other. The story author can always reply to viewers who replied to
     * them (they don't need to follow back first).
     */
    #[Route('/social/story/{id}/reply', name: 'forum_story_reply', methods: ['POST'])]
    public function reply(
        Story $story,
        Request $request,
        FollowRepository $follows,
        EntityManagerInterface $em
    ): JsonResponse {
        $this->denyAccessUnlessGranted('ROLE_USER');
        $me = $this->getUser()->getUserIdentifier();
        $author = $story->getAuteur();

        if ($author === $me) {
            return new JsonResponse(['error' => 'Vous ne pouvez pas répondre à votre propre story.'], 400);
        }
        // CSRF token was rendered into the viewer when the page loaded.
        if (!$this->isCsrfTokenValid('story_reply_' . $story->getId(), (string) $request->request->get('_token'))) {
            return new JsonResponse(['error' => 'Jeton CSRF invalide.'], 400);
        }

        $content = trim((string) $request->request->get('content'));
        if ($content === '') {
            return new JsonResponse(['error' => 'Le message ne peut pas être vide.'], 400);
        }
        if (mb_strlen($content) > 5000) {
            return new JsonResponse(['error' => 'Le message est trop long.'], 400);
        }

        // Same follow-gate as MessageController::thread.
        $iFollowThem  = $follows->isFollowing($me, $author);
        $theyFollowMe = $follows->isFollowing($author, $me);
        if (!$iFollowThem && !$theyFollowMe) {
            return new JsonResponse(
                ['error' => 'Vous devez suivre @' . $author . ' pour lui envoyer un message.'],
                403
            );
        }

        $msg = (new Message())
            ->setSenderUsername($me)
            ->setReceiverUsername($author)
            ->setContent($content)
            ->setStory($story);
        $em->persist($msg);
        $em->flush();

        return new JsonResponse([
            'ok'      => true,
            'threadUrl' => $this->generateUrl('forum_messages_thread', ['username' => $author]),
        ]);
    }

    #[Route('/social/story/{id}/delete', name: 'forum_story_delete', methods: ['POST'])]
    public function delete(
        Story $story,
        Request $request,
        EntityManagerInterface $em
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $me = $this->getUser()->getUserIdentifier();
        if ($story->getAuteur() !== $me && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException();
        }
        if (!$this->isCsrfTokenValid('story_delete_' . $story->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        // Unlink the media file from disk too.
        $file = $this->getParameter('stories_directory') . '/' . $story->getFilename();
        if (is_file($file)) { @unlink($file); }

        $em->remove($story);
        $em->flush();
        $this->addFlash('success', 'Story supprimée.');

        // Honor the caller's return context so deleting the last story from
        // the profile viewer sends the user BACK to the profile, not the feed.
        $returnParam = (string) $request->request->get('return', '');
        $returnUrl = match ($returnParam) {
            'profile' => $this->generateUrl('forum_profile'),
            'user'    => $this->generateUrl('forum_user_profile', ['username' => $story->getAuteur()]),
            default   => $this->generateUrl('forum_forum'),
        };
        return $this->redirect($returnUrl);
    }
}
