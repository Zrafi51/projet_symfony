<?php

namespace App\Controller;

use App\Entity\Message;
use App\Repository\FollowRepository;
use App\Repository\MessageRepository;
use App\Repository\ForumUserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Direct messaging — you can only chat with users you follow.
 * The `/messages` index is the inbox, `/messages/{username}` is a thread.
 */
#[IsGranted('ROLE_USER')]
class MessageController extends AbstractController
{
    #[Route('/social/messages', name: 'forum_messages')]
    public function inbox(
        MessageRepository $messages,
        ForumUserRepository $users
    ): Response {
        $me = $this->getUser()->getUserIdentifier();
        $conversations = $messages->getRecentConversations($me);

        $peers = array_column($conversations, 'peer');
        $photos = $users->getPhotoMapByUsernames($peers);

        return $this->render('social/messages/inbox.html.twig', [
            'conversations' => $conversations,
            'photoMap'      => $photos,
        ]);
    }

    /**
     * Thread view + send form. Enforces follow-gated chat: you must follow the
     * peer (or they must follow you) — otherwise redirect back to inbox.
     */
    #[Route('/social/messages/{username}', name: 'forum_messages_thread')]
    public function thread(
        string $username,
        Request $request,
        ForumUserRepository $users,
        MessageRepository $messages,
        FollowRepository $follows,
        EntityManagerInterface $em,
        ValidatorInterface $validator
    ): Response {
        $me = $this->getUser()->getUserIdentifier();
        if ($username === $me) {
            return $this->redirectToRoute('forum_messages');
        }
        $peer = $users->findOneBy(['username' => $username]);
        if (!$peer) { throw $this->createNotFoundException('Utilisateur introuvable.'); }

        // You can chat if EITHER side follows the other (accepted). Mirrors
        // Instagram DM behaviour — if the other user follows you, you can reply.
        $iFollowThem = $follows->isFollowing($me, $username);
        $theyFollowMe = $follows->isFollowing($username, $me);
        if (!$iFollowThem && !$theyFollowMe) {
            $this->addFlash('warning', 'Vous devez suivre @' . $username . ' pour lui envoyer un message.');
            return $this->redirectToRoute('forum_user_profile', ['username' => $username]);
        }

        // POST: send a new message (PHP-side validation only, no HTML required).
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('send-msg', $request->request->get('_token'))) {
                throw $this->createAccessDeniedException();
            }
            $content = trim((string) $request->request->get('content'));
            $msg = (new Message())
                ->setSenderUsername($me)
                ->setReceiverUsername($username)
                ->setContent($content);

            $errors = $validator->validate($msg);
            if (count($errors) === 0) {
                $em->persist($msg);
                $em->flush();
            } else {
                foreach ($errors as $e) { $this->addFlash('error', $e->getMessage()); }
            }
            return $this->redirectToRoute('forum_messages_thread', ['username' => $username]);
        }

        // Mark incoming messages as read when the thread opens.
        $messages->markThreadRead($me, $username);

        $thread = $messages->getConversation($me, $username);
        return $this->render('social/messages/thread.html.twig', [
            'peer'     => $peer,
            'messages' => $thread,
        ]);
    }

    /** Polling endpoint — live unread badge on the nav. URL chosen so it
     *  can't collide with `/messages/{username}`. */
    #[Route('/social/api/messages/unread-count', name: 'forum_messages_unread', methods: ['GET'])]
    public function unreadCount(MessageRepository $messages): JsonResponse
    {
        return new JsonResponse([
            'count' => $messages->countUnreadFor($this->getUser()->getUserIdentifier()),
        ]);
    }
}
