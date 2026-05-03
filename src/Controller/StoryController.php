<?php

namespace App\Controller;

use App\Entity\Message;
use App\Entity\Story;
use App\Entity\StoryLike;
use App\Repository\FollowRepository;
use App\Repository\MessageRepository;
use App\Repository\MusicRepository;
use App\Repository\StoryLikeRepository;
use App\Repository\StoryRepository;
use App\Repository\StoryViewRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

class StoryController extends AbstractController
{
    private const IMAGE_MIMES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    private const VIDEO_MIMES = ['video/mp4', 'video/webm', 'video/ogg', 'video/quicktime', 'video/x-matroska'];
    private const MAX_IMAGE_BYTES = 8 * 1024 * 1024;
    private const MAX_VIDEO_BYTES = 50 * 1024 * 1024;

    #[Route('/story/new', name: 'app_story_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        StoryRepository $stories,
        SluggerInterface $slugger,
        MusicRepository $musicRepo
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_USER');

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('story_new', (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'Jeton CSRF invalide.');
                return $this->redirectToRoute('app_story_new');
            }

            /** @var UploadedFile|null $file */
            $file = $request->files->get('media');
            if (!$file) {
                $this->addFlash('error', 'Veuillez choisir une image ou une vidéo.');
                return $this->redirectToRoute('app_story_new');
            }

            $mime = $file->getMimeType();
            if (in_array($mime, self::IMAGE_MIMES, true)) {
                $kind = Story::TYPE_IMAGE;
                if ($file->getSize() > self::MAX_IMAGE_BYTES) {
                    $this->addFlash('error', 'L\'image ne doit pas dépasser 8 Mo.');
                    return $this->redirectToRoute('app_story_new');
                }
            } elseif (in_array($mime, self::VIDEO_MIMES, true)) {
                $kind = Story::TYPE_VIDEO;
                if ($file->getSize() > self::MAX_VIDEO_BYTES) {
                    $this->addFlash('error', 'La vidéo ne doit pas dépasser 50 Mo.');
                    return $this->redirectToRoute('app_story_new');
                }
            } else {
                $this->addFlash('error', 'Format non supporté. JPEG/PNG/GIF/WebP ou MP4/WebM/MOV.');
                return $this->redirectToRoute('app_story_new');
            }

            $original = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
            $safe     = $slugger->slug($original);
            $newName  = $safe . '-' . uniqid() . '.' . ($file->guessExtension() ?: 'bin');
            try {
                $file->move($this->getParameter('stories_directory'), $newName);
            } catch (FileException $e) {
                $this->addFlash('error', 'Erreur lors du téléchargement.');
                return $this->redirectToRoute('app_story_new');
            }

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
            $stories->save($story);

            $this->addFlash('success', 'Story publiée — visible pendant 24 heures.');
            return $this->redirectToRoute('app_forum');
        }

        return $this->render('stories/new.html.twig', [
            'musicTracks' => $musicRepo->findAllOrdered(),
        ]);
    }

    public function bar(
        StoryRepository $stories,
        FollowRepository $follows,
        UserRepository $users
    ): Response {
        $user = $this->getUser();
        if (!$user) {
            return new Response('');
        }
        $me = $user->getUserIdentifier();

        $stories->purgeExpired();

        $followingUsernames = $follows->getFollowingUsernames($me);
        $authors = array_values(array_unique(array_merge([$me], $followingUsernames)));

        $activeStories = $stories->findActiveByAuthors($authors);

        $byAuthor = [];
        foreach ($activeStories as $s) {
            $a = $s->getAuteur();
            if (!isset($byAuthor[$a])) {
                $byAuthor[$a] = ['author' => $a, 'cover' => $s, 'count' => 0];
            }
            $byAuthor[$a]['count']++;
        }

        $myGroup = $byAuthor[$me] ?? null;
        unset($byAuthor[$me]);
        $groups = array_values($byAuthor);
        if ($myGroup !== null) {
            array_unshift($groups, $myGroup);
        }

        $authorList = array_map(fn ($g) => $g['author'], $groups);
        $photoMap = $users->getPhotoMapByUsernames($authorList);

        return $this->render('_partials/stories_bar.html.twig', [
            'groups'   => $groups,
            'photoMap' => $photoMap,
            'me'       => $me,
            'hasMine'  => $myGroup !== null,
        ]);
    }

    #[Route('/story/user/{username}', name: 'app_story_view', methods: ['GET'])]
    public function view(
        string $username,
        Request $request,
        StoryRepository $stories,
        UserRepository $users,
        FollowRepository $follows,
        StoryLikeRepository $storyLikes
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $me = $this->getUser()->getUserIdentifier();
        $target = $users->findOneBy(['username' => $username]);
        if (!$target) {
            throw $this->createNotFoundException();
        }

        $returnParam = (string) $request->query->get('return', '');
        $returnUrl = match ($returnParam) {
            'profile' => $this->generateUrl('app_profile'),
            'user'    => $this->generateUrl('app_user_profile', ['username' => $username]),
            default   => $this->generateUrl('app_forum'),
        };

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

        $likedMap  = $storyLikes->likedMapFor($list, $me);
        $likeCount = [];
        foreach ($list as $s) {
            $likeCount[$s->getId()] = $storyLikes->countForStory($s);
        }

        return $this->render('stories/show.html.twig', [
            'stories'     => $list,
            'author'      => $username,
            'authorPhoto' => $authorPhoto,
            'isOwner'     => $username === $me,
            'returnUrl'   => $returnUrl,
            'likedMap'    => $likedMap,
            'likeCount'   => $likeCount,
        ]);
    }

    #[Route('/story/{id}/view', name: 'app_story_view_record', methods: ['POST'])]
    public function recordView(int $id, StoryRepository $storyRepo, StoryViewRepository $views): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        $story = $storyRepo->find($id) ?? throw $this->createNotFoundException();
        $me = $this->getUser()->getUserIdentifier();
        $created = $views->recordOnce($story, $me);
        return new JsonResponse([
            'ok'      => true,
            'created' => $created,
        ]);
    }

    #[Route('/story/{id}/like', name: 'app_story_like_toggle', methods: ['POST'])]
    public function toggleLike(
        int $id,
        StoryRepository $storyRepo,
        StoryLikeRepository $storyLikes
    ): JsonResponse {
        $this->denyAccessUnlessGranted('ROLE_USER');
        $story = $storyRepo->find($id) ?? throw $this->createNotFoundException();
        $me = $this->getUser()->getUserIdentifier();

        $existing = $storyLikes->findLike($story, $me);
        if ($existing) {
            $storyLikes->remove($existing);
            $liked = false;
        } else {
            $like = (new StoryLike())
                ->setStory($story)
                ->setLikerUsername($me);
            $storyLikes->save($like);
            $liked = true;
        }
        return new JsonResponse([
            'liked' => $liked,
            'count' => $storyLikes->countForStory($story),
        ]);
    }

    #[Route('/story/{id}/reply', name: 'app_story_reply', methods: ['POST'])]
    public function reply(
        int $id,
        Request $request,
        StoryRepository $storyRepo,
        FollowRepository $follows,
        MessageRepository $messageRepo
    ): JsonResponse {
        $this->denyAccessUnlessGranted('ROLE_USER');
        $story = $storyRepo->find($id) ?? throw $this->createNotFoundException();
        $me = $this->getUser()->getUserIdentifier();
        $author = $story->getAuteur();

        if ($author === $me) {
            return new JsonResponse(['error' => 'Vous ne pouvez pas répondre à votre propre story.'], 400);
        }
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
        $messageRepo->save($msg);

        return new JsonResponse([
            'ok'      => true,
            'threadUrl' => $this->generateUrl('app_messages_thread', ['username' => $author]),
        ]);
    }

    #[Route('/story/{id}/delete', name: 'app_story_delete', methods: ['POST'])]
    public function delete(
        int $id,
        Request $request,
        StoryRepository $storyRepo
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_USER');
        $story = $storyRepo->find($id) ?? throw $this->createNotFoundException();

        $me = $this->getUser()->getUserIdentifier();
        if ($story->getAuteur() !== $me && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException();
        }
        if (!$this->isCsrfTokenValid('story_delete_' . $story->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $file = $this->getParameter('stories_directory') . '/' . $story->getFilename();
        if (is_file($file)) { @unlink($file); }

        $storyRepo->remove($story);
        $this->addFlash('success', 'Story supprimée.');

        $returnParam = (string) $request->request->get('return', '');
        $returnUrl = match ($returnParam) {
            'profile' => $this->generateUrl('app_profile'),
            'user'    => $this->generateUrl('app_user_profile', ['username' => $story->getAuteur()]),
            default   => $this->generateUrl('app_forum'),
        };
        return $this->redirect($returnUrl);
    }
}
