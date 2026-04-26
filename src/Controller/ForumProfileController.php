<?php

namespace App\Controller;

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
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/social/profile')]
class ForumProfileController extends AbstractController
{
    #[Route('', name: 'forum_profile')]
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

        // Social stats (Instagram-style header row).
        $socialStats = [
            'posts'     => $postCount,
            'followers' => $followRepo->countFollowers($username),
            'following' => $followRepo->countFollowing($username),
        ];
        $pendingRequestCount = count($followRepo->getPendingRequestsFor($username));

        // New-post form (same one as on the feed) — so the user can publish
        // directly from their profile.
        $postForm = $this->createForm(PostType::class, new Post());
        $musicUrlsById = [];
        foreach ($musicRepository->findAllOrdered() as $m) {
            $musicUrlsById[$m->getId()] = '/uploads/music/' . $m->getFilename();
        }

        // Whether to draw the Instagram-style "story ring" around the avatar.
        $hasActiveStory = $storyRepository->userHasActiveStory($username);

        return $this->render('social/profile/index.html.twig', [
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

    #[Route('/edit', name: 'forum_profile_edit')]
    public function edit(
        Request $request,
        EntityManagerInterface $em,
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

            // Since auteur/username is stored as a plain string FK on posts, comments
            // and reactions, cascade the rename across those tables so existing posts
            // reflect the new username on the feed.
            if ($oldUsername !== $newUsername) {
                $conn = $em->getConnection();
                $conn->executeStatement(
                    'UPDATE sf_posts SET auteur = :new WHERE auteur = :old',
                    ['new' => $newUsername, 'old' => $oldUsername]
                );
                $conn->executeStatement(
                    'UPDATE sf_comments SET auteur = :new WHERE auteur = :old',
                    ['new' => $newUsername, 'old' => $oldUsername]
                );
                $conn->executeStatement(
                    'UPDATE sf_reactions SET username = :new WHERE username = :old',
                    ['new' => $newUsername, 'old' => $oldUsername]
                );
                // Also cascade into post_likes so historical likes follow the rename.
                // If a row would collide with an existing (post_id, new_username) pair,
                // keep the older one (IGNORE) — a user can't legitimately have two likes
                // on the same post under the same identity.
                $conn->executeStatement(
                    'UPDATE IGNORE sf_post_likes SET username = :new WHERE username = :old',
                    ['new' => $newUsername, 'old' => $oldUsername]
                );
                $conn->executeStatement(
                    'DELETE FROM sf_post_likes WHERE username = :old',
                    ['old' => $oldUsername]
                );
            }

            $em->flush();
            $this->addFlash('success', 'Profil mis à jour !');
            return $this->redirectToRoute('forum_profile');
        }

        return $this->render('social/profile/edit.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
