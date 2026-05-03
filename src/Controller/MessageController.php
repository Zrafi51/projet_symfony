<?php

namespace App\Controller;

use App\Entity\Message;
use App\Repository\FollowRepository;
use App\Repository\MessageRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[IsGranted('ROLE_USER')]
class MessageController extends AbstractController
{
    #[Route('/messages', name: 'app_messages')]
    public function inbox(
        MessageRepository $messages,
        UserRepository $users
    ): Response {
        $me = $this->getUser()->getUserIdentifier();
        $conversations = $messages->getRecentConversations($me);

        $peers = array_column($conversations, 'peer');
        $photos = $users->getPhotoMapByUsernames($peers);

        return $this->render('messages/inbox.html.twig', [
            'conversations' => $conversations,
            'photoMap'      => $photos,
        ]);
    }

    #[Route('/messages/{username}', name: 'app_messages_thread')]
    public function thread(
        string $username,
        Request $request,
        UserRepository $users,
        MessageRepository $messages,
        FollowRepository $follows,
        ValidatorInterface $validator
    ): Response {
        $me = $this->getUser()->getUserIdentifier();
        if ($username === $me) {
            return $this->redirectToRoute('app_messages');
        }
        $peer = $users->findOneBy(['username' => $username]);
        if (!$peer) { throw $this->createNotFoundException('Utilisateur introuvable.'); }

        $iFollowThem = $follows->isFollowing($me, $username);
        $theyFollowMe = $follows->isFollowing($username, $me);
        if (!$iFollowThem && !$theyFollowMe) {
            $this->addFlash('warning', 'Vous devez suivre @' . $username . ' pour lui envoyer un message.');
            return $this->redirectToRoute('app_user_profile', ['username' => $username]);
        }

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
                $messages->save($msg);
            } else {
                foreach ($errors as $e) { $this->addFlash('error', $e->getMessage()); }
            }
            return $this->redirectToRoute('app_messages_thread', ['username' => $username]);
        }

        $messages->markThreadRead($me, $username);

        $thread = $messages->getConversation($me, $username);
        return $this->render('messages/thread.html.twig', [
            'peer'     => $peer,
            'messages' => $thread,
        ]);
    }

    #[Route('/api/messages/unread-count', name: 'app_messages_unread', methods: ['GET'])]
    public function unreadCount(MessageRepository $messages): JsonResponse
    {
        return new JsonResponse([
            'count' => $messages->countUnreadFor($this->getUser()->getUserIdentifier()),
        ]);
    }
}
