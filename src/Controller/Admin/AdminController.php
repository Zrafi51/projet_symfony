<?php

namespace App\Controller\Admin;

use App\Repository\CommentRepository;
use App\Repository\PostRepository;
use App\Repository\ReactionRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin')]
class AdminController extends AbstractController
{
    #[Route('', name: 'app_admin_dashboard')]
    public function dashboard(
        UserRepository $userRepo,
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

        return $this->render('admin/dashboard.html.twig', [
            'totalUsers' => $totalUsers,
            'totalPosts' => $totalPosts,
            'totalComments' => $totalComments,
            'userStats' => $userStats,
        ]);
    }

    #[Route('/posts', name: 'app_admin_posts')]
    public function posts(PostRepository $postRepo): Response
    {
        return $this->render('admin/posts.html.twig', [
            'posts' => $postRepo->findAllOrderedByDate(),
        ]);
    }

    #[Route('/post/{id}/delete', name: 'app_admin_post_delete', methods: ['POST'])]
    public function deletePost(int $id, Request $request, PostRepository $postRepo): Response
    {
        $post = $postRepo->find($id) ?? throw $this->createNotFoundException();
        if ($this->isCsrfTokenValid('admin_delete' . $post->getId(), $request->request->get('_token'))) {
            if ($post->getCheminPhoto()) {
                $file = $this->getParameter('posts_directory') . '/' . $post->getCheminPhoto();
                if (file_exists($file)) {
                    unlink($file);
                }
            }
            $postRepo->remove($post);
            $this->addFlash('success', 'Publication supprimée.');
        }

        return $this->redirectToRoute('app_admin_posts');
    }

    #[Route('/users', name: 'app_admin_users')]
    public function users(UserRepository $userRepo): Response
    {
        return $this->render('admin/users.html.twig', [
            'users' => $userRepo->findAllOrderedByName(),
        ]);
    }

    #[Route('/user/{id}/toggle-admin', name: 'app_admin_toggle_role', methods: ['POST'])]
    public function toggleAdmin(int $id, Request $request, UserRepository $userRepo): Response
    {
        $user = $userRepo->find($id) ?? throw $this->createNotFoundException();
        if ($this->isCsrfTokenValid('toggle_admin' . $user->getId(), $request->request->get('_token'))) {
            $roles = $user->getRoles();
            if (in_array('ROLE_ADMIN', $roles)) {
                $user->setRoles(['ROLE_USER']);
            } else {
                $user->setRoles(['ROLE_ADMIN']);
            }
            $userRepo->save($user);
            $this->addFlash('success', 'Rôle modifié pour ' . $user->getUsername());
        }

        return $this->redirectToRoute('app_admin_users');
    }

    #[Route('/user/{id}/delete', name: 'app_admin_user_delete', methods: ['POST'])]
    public function deleteUser(int $id, Request $request, UserRepository $userRepo): Response
    {
        $user = $userRepo->find($id) ?? throw $this->createNotFoundException();
        if ($this->getUser() && $user->getId() === $this->getUser()->getId()) {
            $this->addFlash('error', 'Vous ne pouvez pas supprimer votre propre compte.');
            return $this->redirectToRoute('app_admin_users');
        }

        if ($this->isCsrfTokenValid('delete_user' . $user->getId(), $request->request->get('_token'))) {
            $userRepo->remove($user);
            $this->addFlash('success', 'Utilisateur supprimé.');
        }

        return $this->redirectToRoute('app_admin_users');
    }

    #[Route('/comments', name: 'app_admin_comments')]
    public function comments(CommentRepository $commentRepo): Response
    {
        return $this->render('admin/comments.html.twig', [
            'comments' => $commentRepo->findAllOrderedByDate(),
        ]);
    }

    #[Route('/comment/{id}/delete', name: 'app_admin_comment_delete', methods: ['POST'])]
    public function deleteComment(int $id, Request $request, CommentRepository $commentRepo): Response
    {
        $comment = $commentRepo->find($id) ?? throw $this->createNotFoundException();
        if ($this->isCsrfTokenValid('admin_delete_comment' . $comment->getId(), $request->request->get('_token'))) {
            $commentRepo->remove($comment);
            $this->addFlash('success', 'Commentaire supprimé.');
        }

        return $this->redirectToRoute('app_admin_comments');
    }
}
