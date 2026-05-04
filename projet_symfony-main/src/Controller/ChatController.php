<?php

namespace App\Controller;

use App\Repository\ChatRepository;
use App\Service\TravelAiClient;
use App\View\PhpTemplateRenderer;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/chat')]
final class ChatController extends AbstractController
{
    private const WELCOME_MESSAGE = 'Bonjour, je suis Travel-AI. Donnez-moi votre budget, vos dates ou le style de voyage que vous cherchez.';

    public function __construct(
        private readonly ChatRepository $chatRepository,
        private readonly TravelAiClient $travelAiClient,
        private readonly PhpTemplateRenderer $renderer,
    ) {
    }

    #[Route('', name: 'app_chat_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $currentUser = $this->getAuthenticatedUser($request);
        $userId = $this->resolveUserId($currentUser);
        $sessions = $this->hydrateSessions($this->chatRepository->findRecentSessions($userId), $userId);

        if ($sessions === []) {
            $sessionId = 'offline-'.$this->chatRepository->generateId();
            $this->chatRepository->createSession($sessionId, $userId, 'Nouvelle discussion', 'fr');
            $this->chatRepository->addMessage($sessionId, $userId, 'assistant', self::WELCOME_MESSAGE);
            $sessions = $this->hydrateSessions($this->chatRepository->findRecentSessions($userId), $userId);
        }

        return new Response($this->renderer->render('chat/index', [
            'title' => 'Travel-AI',
            'showPageHeading' => false,
            'stylesheets' => ['/chat.css'],
            'currentUser' => $currentUser,
            'chatUserId' => $userId,
            'chatApiBaseUrl' => $this->travelAiClient->getBaseUrl(),
            'chatSessions' => $sessions,
            'pendingPrompt' => trim((string) $request->query->get('prompt', '')),
        ]));
    }

    #[Route('/sessions', name: 'app_chat_session_create', methods: ['POST'])]
    public function createSession(Request $request): JsonResponse
    {
        $currentUser = $this->getAuthenticatedUser($request);
        $userId = $this->resolveUserId($currentUser);
        $title = trim((string) $request->request->get('title', 'Nouvelle discussion'));

        try {
            $sessionId = $this->travelAiClient->createSession($userId, $title, 'fr');
        } catch (RuntimeException) {
            $sessionId = 'offline-'.$this->chatRepository->generateId();
            $title .= ' (hors ligne)';
        }

        $this->chatRepository->createSession($sessionId, $userId, $title, 'fr');
        $this->chatRepository->addMessage($sessionId, $userId, 'assistant', self::WELCOME_MESSAGE);

        return $this->json(['ok' => true, 'session' => $this->hydrateSessionById($sessionId, $userId)]);
    }

    #[Route('/sessions/{sessionId}/messages', name: 'app_chat_message_send', methods: ['POST'])]
    public function sendMessage(string $sessionId, Request $request): JsonResponse
    {
        $currentUser = $this->getAuthenticatedUser($request);
        $userId = $this->resolveUserId($currentUser);
        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            $payload = $request->request->all();
        }

        $message = trim((string) ($payload['message'] ?? ''));
        if ($message === '') {
            return $this->json(['ok' => false, 'error' => 'Message vide.'], 400);
        }
        if (!$this->chatRepository->sessionBelongsToUser($sessionId, $userId)) {
            return $this->json(['ok' => false, 'error' => 'Discussion introuvable.'], 404);
        }

        $this->chatRepository->addMessage($sessionId, $userId, 'user', $message);
        $reply = str_starts_with($sessionId, 'offline-')
            ? $this->offlineReply($message)
            : $this->sendToAgent($sessionId, $message);
        $this->chatRepository->addMessage($sessionId, $userId, 'assistant', $reply);

        $title = $this->buildHistoryTitle($message);
        $this->chatRepository->renameSession($sessionId, $userId, $title);

        return $this->json([
            'ok' => true,
            'reply' => $reply,
            'session' => $this->hydrateSessionById($sessionId, $userId),
        ]);
    }

    #[Route('/sessions/{sessionId}/delete', name: 'app_chat_session_delete', methods: ['POST'])]
    public function deleteSession(string $sessionId, Request $request): JsonResponse
    {
        $currentUser = $this->getAuthenticatedUser($request);
        $this->chatRepository->deleteSession($sessionId, $this->resolveUserId($currentUser));

        return $this->json(['ok' => true]);
    }

    private function hydrateSessions(array $sessions, string $userId): array
    {
        return array_map(fn (array $session): array => [
            'id' => $session['id'],
            'title' => $session['title'] ?: 'Discussion',
            'lastActivity' => $this->formatTime((string) ($session['last_message_at'] ?? $session['updated_at'] ?? '')),
            'messages' => array_map(fn (array $message): array => [
                'role' => $message['role'],
                'content' => $message['content'],
                'time' => $this->formatTime((string) ($message['created_at'] ?? '')),
            ], $this->chatRepository->findMessages((string) $session['id'], $userId)),
        ], $sessions);
    }

    private function hydrateSessionById(string $sessionId, string $userId): ?array
    {
        foreach ($this->hydrateSessions($this->chatRepository->findRecentSessions($userId), $userId) as $session) {
            if ($session['id'] === $sessionId) {
                return $session;
            }
        }

        return null;
    }

    private function sendToAgent(string $sessionId, string $message): string
    {
        try {
            return $this->travelAiClient->sendMessage($sessionId, $message, 'fr');
        } catch (RuntimeException $exception) {
            return 'Je n arrive pas a joindre le service IA pour l instant ('.$exception->getMessage().'). Votre message a ete conserve. Reessayez dans quelques secondes.';
        }
    }

    private function offlineReply(string $prompt): string
    {
        $prompt = mb_strtolower($prompt);
        if (str_contains($prompt, 'budget') || preg_match('/\d+/', $prompt)) {
            return 'Mode hors ligne: pour un budget maitrise, je recommande Bali, Marrakech ou Istanbul selon la saison.';
        }
        if (str_contains($prompt, 'plage')) {
            return 'Mode hors ligne: pour la plage, regardez Maldives, Bali ou Djerba selon votre budget.';
        }
        if (str_contains($prompt, 'japon') || str_contains($prompt, 'tokyo')) {
            return 'Mode hors ligne: Tokyo est excellent au printemps et en automne, avec un bon mix culture et gastronomie.';
        }

        return 'Mode hors ligne: je peux deja vous orienter si vous me donnez budget, periode et style de voyage.';
    }

    private function buildHistoryTitle(string $prompt): string
    {
        $prompt = trim($prompt);

        return mb_strlen($prompt) <= 42 ? $prompt : mb_substr($prompt, 0, 39).'...';
    }

    private function formatTime(string $value): string
    {
        $timestamp = strtotime($value);

        return $timestamp !== false ? date('H:i', $timestamp) : date('H:i');
    }

    private function getAuthenticatedUser(Request $request): ?array
    {
        $user = $request->getSession()->get('auth_user');

        return is_array($user) ? $user : null;
    }

    private function resolveUserId(?array $currentUser): string
    {
        $seed = trim((string) ($currentUser['id'] ?? $currentUser['email'] ?? $currentUser['display_name'] ?? 'guest'));

        if ($seed === '') {
            return 'guest';
        }

        $safeSeed = preg_replace('/[^A-Za-z0-9@._-]/', '_', $seed) ?? '';

        return trim($safeSeed, '_') !== '' ? substr($safeSeed, 0, 120) : 'guest';
    }
}
