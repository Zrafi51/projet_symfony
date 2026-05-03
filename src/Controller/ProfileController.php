<?php

namespace App\Controller;

use App\Database\PdoConnectionFactory;
use App\Entity\Post;
use App\Entity\Reaction;
use App\Form\PostType;
use App\Form\ProfileType;
use App\Repository\CommentRepository;
use App\Repository\FollowRepository;
use App\Repository\MusicRepository;
use App\Repository\PostRepository;
use App\Repository\ReactionRepository;
use App\Repository\StoryRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/profile')]
class ProfileController extends AbstractController
{
    #[Route('', name: 'app_profile')]
    public function index(
        PostRepository $postRepo,
        ReactionRepository $reactionRepo,
        CommentRepository $commentRepo,
        FollowRepository $followRepo,
        MusicRepository $musicRepository,
        StoryRepository $storyRepository
    ): Response {
        $user = $this->getUser();
        $username = $user->getUserIdentifier();

        $posts = $postRepo->findByUser($user);
        $postCount = $postRepo->getPostCountByUser($user);
        $totalLikes = $postRepo->getTotalLikesReceived($user);
        $totalReactions = $reactionRepo->getTotalReactionsForUser($user);
        $totalComments = $commentRepo->getTotalCommentsForUser($user);
        $postsPerMonth = $postRepo->getPostsPerMonth($user);
        $reactionBreakdown = $reactionRepo->getReactionBreakdownForUser($user);

        $socialStats = [
            'posts'     => $postCount,
            'followers' => $followRepo->countFollowers($username),
            'following' => $followRepo->countFollowing($username),
        ];
        $pendingRequestCount = count($followRepo->getPendingRequestsFor($username));

        $postForm = $this->createForm(PostType::class, new Post());
        $musicUrlsById = [];
        foreach ($musicRepository->findAllOrdered() as $m) {
            $musicUrlsById[$m->getId()] = '/uploads/music/' . $m->getFilename();
        }

        $hasActiveStory = $storyRepository->userHasActiveStory($username);

        return $this->render('profile/index.html.twig', [
            'user' => $user,
            'posts' => $posts,
            'postCount' => $postCount,
            'totalLikes' => $totalLikes,
            'totalReactions' => $totalReactions,
            'totalComments' => $totalComments,
            'postsPerMonth' => $postsPerMonth,
            'reactionBreakdown' => $reactionBreakdown,
            'reactionTypes' => Reaction::TYPES,
            'socialStats' => $socialStats,
            'pendingRequestCount' => $pendingRequestCount,
            'postForm' => $postForm->createView(),
            'musicUrlsById' => $musicUrlsById,
            'hasActiveStory' => $hasActiveStory,
        ]);
    }

    #[Route('/edit', name: 'app_profile_edit')]
    public function edit(
        Request $request,
        UserRepository $userRepository,
        PdoConnectionFactory $pdoFactory,
        SluggerInterface $slugger
    ): Response {
        $user = $this->getUser();
        $oldUsername = $user->getUsername();

        $form = $this->createForm(ProfileType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $photoFile = $form->get('profilePhoto')->getData();
            if ($photoFile) {
                $originalFilename = pathinfo($photoFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $photoFile->guessExtension();

                try {
                    $photoFile->move($this->getParameter('profiles_directory'), $newFilename);
                    if ($user->getProfilePhotoPath()) {
                        $oldFile = $this->getParameter('profiles_directory') . '/' . $user->getProfilePhotoPath();
                        if (file_exists($oldFile)) {
                            unlink($oldFile);
                        }
                    }
                    $user->setProfilePhotoPath($newFilename);
                } catch (FileException $e) {
                    $this->addFlash('error', 'Erreur lors du téléchargement de la photo.');
                }
            }

            $newUsername = $user->getUsername();
            $pdo = $pdoFactory->getConnection();

            if ($oldUsername !== $newUsername) {
                $stmt = $pdo->prepare('UPDATE posts SET auteur = :new WHERE auteur = :old');
                $stmt->execute(['new' => $newUsername, 'old' => $oldUsername]);

                $stmt = $pdo->prepare('UPDATE comments SET auteur = :new WHERE auteur = :old');
                $stmt->execute(['new' => $newUsername, 'old' => $oldUsername]);

                $stmt = $pdo->prepare('UPDATE reactions SET username = :new WHERE username = :old');
                $stmt->execute(['new' => $newUsername, 'old' => $oldUsername]);

                $stmt = $pdo->prepare('UPDATE IGNORE post_likes SET username = :new WHERE username = :old');
                $stmt->execute(['new' => $newUsername, 'old' => $oldUsername]);

                $stmt = $pdo->prepare('DELETE FROM post_likes WHERE username = :old');
                $stmt->execute(['old' => $oldUsername]);
            }

            $userRepository->save($user);
            $this->addFlash('success', 'Profil mis à jour !');
            return $this->redirectToRoute('app_profile');
        }

        return $this->render('profile/edit.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
