<?php

namespace App\Controller;

use App\Entity\Comment;
use App\Entity\Post;
use App\Entity\User;
use App\Repository\CommentRepository;
use App\Repository\PostRepository;
use App\Repository\ReactionRepository;
use App\Repository\ForumUserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/social/admin')]
class ForumAdminController extends AbstractController
{
    #[Route('', name: 'forum_admin_dashboard')]
    public function dashboard(
        ForumUserRepository $userRepo,
        PostRepository $postRepo,
        CommentRepository $commentRepo,
        ReactionRepository $reactionRepo
    ): Response {
        $users = $userRepo->findAll();
        $totalPosts = $postRepo->countAll();
        $totalComments = $commentRepo->countAll();
        $totalUsers = count($users);

        $userStats = [];
        foreach ($users as $user) {
            $userStats[] = [
                'user' => $user,
                'postCount' => $postRepo->getPostCountByUser($user),
                'totalLikes' => $postRepo->getTotalLikesReceived($user),
                'totalReactions' => $reactionRepo->getTotalReactionsForUser($user),
                'totalComments' => $commentRepo->getTotalCommentsForUser($user),
            ];
        }

        return $this->render('social/admin/dashboard.html.twig', [
            'totalUsers' => $totalUsers,
            'totalPosts' => $totalPosts,
            'totalComments' => $totalComments,
            'userStats' => $userStats,
        ]);
    }

    #[Route('/posts', name: 'forum_admin_posts')]
    public function posts(PostRepository $postRepo): Response
    {
        return $this->render('social/admin/posts.html.twig', [
            'posts' => $postRepo->findAllOrderedByDate(),
        ]);
    }

    #[Route('/post/{id}/delete', name: 'forum_admin_post_delete', methods: ['POST'])]
    public function deletePost(Post $post, Request $request, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('admin_delete' . $post->getId(), $request->request->get('_token'))) {
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

        return $this->redirectToRoute('forum_admin_posts');
    }

    #[Route('/users', name: 'forum_admin_users')]
    public function users(ForumUserRepository $userRepo): Response
    {
        return $this->render('social/admin/users.html.twig', [
            'users' => $userRepo->findAllOrderedByName(),
        ]);
    }

    #[Route('/user/{id}/toggle-admin', name: 'forum_admin_toggle_role', methods: ['POST'])]
    public function toggleAdmin(User $user, Request $request, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('toggle_admin' . $user->getId(), $request->request->get('_token'))) {
            $roles = $user->getRoles();
            if (in_array('ROLE_ADMIN', $roles)) {
                $user->setRoles(['ROLE_USER']);
            } else {
                $user->setRoles(['ROLE_ADMIN']);
            }
            $em->flush();
            $this->addFlash('success', 'Rôle modifié pour ' . $user->getUsername());
        }

        return $this->redirectToRoute('forum_admin_users');
    }

    #[Route('/user/{id}/delete', name: 'forum_admin_user_delete', methods: ['POST'])]
    public function deleteUser(User $user, Request $request, EntityManagerInterface $em): Response
    {
        if ($user === $this->getUser()) {
            $this->addFlash('error', 'Vous ne pouvez pas supprimer votre propre compte.');
            return $this->redirectToRoute('forum_admin_users');
        }

        if ($this->isCsrfTokenValid('delete_user' . $user->getId(), $request->request->get('_token'))) {
            $em->remove($user);
            $em->flush();
            $this->addFlash('success', 'Utilisateur supprimé.');
        }

        return $this->redirectToRoute('forum_admin_users');
    }

    #[Route('/comments', name: 'forum_admin_comments')]
    public function comments(CommentRepository $commentRepo): Response
    {
        return $this->render('social/admin/comments.html.twig', [
            'comments' => $commentRepo->findBy([], ['dateCommentaire' => 'DESC']),
        ]);
    }

    #[Route('/comment/{id}/delete', name: 'forum_admin_comment_delete', methods: ['POST'])]
    public function deleteComment(Comment $comment, Request $request, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('admin_delete_comment' . $comment->getId(), $request->request->get('_token'))) {
            $em->remove($comment);
            $em->flush();
            $this->addFlash('success', 'Commentaire supprimé.');
        }

        return $this->redirectToRoute('forum_admin_comments');
    }
}
