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
        $pendingPrompt = trim((string) $request->query->get('prompt', ''));
        $tripProfile = $this->decodeTripProfile((string) $request->query->get('trip_profile', ''));
        if ($tripProfile !== []) {
            $this->createTripProfileSession($userId, $tripProfile);
        }

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
            'pendingPrompt' => $pendingPrompt,
        ]));
    }

    #[Route('/sessions', name: 'app_chat_session_create', methods: ['POST'])]
    public function createSession(Request $request): JsonResponse
    {
        $currentUser = $this->getAuthenticatedUser($request);
        $userId = $this->resolveUserId($currentUser);
        $title = trim((string) $request->request->get('title', 'Nouvelle discussion'));
        $sessionId = $this->chatRepository->generateId();

        try {
            $sessionId = $this->travelAiClient->createSession($userId, $title, 'fr', $sessionId);
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
        $agentPayload = str_starts_with($sessionId, 'offline-')
            ? ['reply' => $this->offlineReply($message)]
            : $this->sendToAgent($sessionId, $userId, $message);
        $reply = trim((string) ($agentPayload['reply'] ?? ''));
        $this->chatRepository->addMessage($sessionId, $userId, 'assistant', $reply, $this->extractAgentMetadata($agentPayload));
        $this->persistAgentSnapshot($sessionId, $userId, $agentPayload);

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

    #[Route('/sessions/{sessionId}/favorite', name: 'app_chat_session_favorite', methods: ['POST'])]
    public function toggleFavoriteSession(string $sessionId, Request $request): JsonResponse
    {
        $currentUser = $this->getAuthenticatedUser($request);
        $userId = $this->resolveUserId($currentUser);
        if (!$this->chatRepository->sessionBelongsToUser($sessionId, $userId)) {
            return $this->json(['ok' => false, 'error' => 'Discussion introuvable.'], 404);
        }

        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            $payload = $request->request->all();
        }

        $favorite = filter_var($payload['favorite'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $this->chatRepository->setFavorite($sessionId, $userId, $favorite);

        return $this->json([
            'ok' => true,
            'session' => $this->hydrateSessionById($sessionId, $userId),
        ]);
    }

    #[Route('/sessions/{sessionId}/cards/generate', name: 'app_chat_card_generate', methods: ['POST'])]
    public function generateCard(string $sessionId, Request $request): JsonResponse
    {
        $currentUser = $this->getAuthenticatedUser($request);
        $userId = $this->resolveUserId($currentUser);
        if (!$this->chatRepository->sessionBelongsToUser($sessionId, $userId)) {
            return $this->json(['ok' => false, 'error' => 'Discussion introuvable.'], 404);
        }

        try {
            if (str_starts_with($sessionId, 'offline-')) {
                throw new RuntimeException('session hors ligne');
            }
            $payload = $this->travelAiClient->generateCard($sessionId, 'fr');
        } catch (RuntimeException $exception) {
            if (!str_contains($exception->getMessage(), 'invalid_session') || !$this->restoreRemoteSession($sessionId, $userId)) {
                return $this->json(['ok' => false, 'error' => 'Generation du pack indisponible: '.$exception->getMessage()], 502);
            }
            try {
                $payload = $this->travelAiClient->generateCard($sessionId, 'fr');
            } catch (RuntimeException $retryException) {
                return $this->json(['ok' => false, 'error' => 'Generation du pack indisponible: '.$retryException->getMessage()], 502);
            }
        }

        $reply = trim((string) ($payload['reply'] ?? ''));
        if ($reply !== '') {
            $this->chatRepository->addMessage($sessionId, $userId, 'assistant', $reply, $this->extractAgentMetadata($payload));
        }
        $this->persistAgentSnapshot($sessionId, $userId, $payload);

        return $this->json(['ok' => true, 'session' => $this->hydrateSessionById($sessionId, $userId)]);
    }

    #[Route('/sessions/{sessionId}/cards/{cardId}/delete', name: 'app_chat_card_delete', methods: ['POST'])]
    public function deleteCard(string $sessionId, string $cardId, Request $request): JsonResponse
    {
        $currentUser = $this->getAuthenticatedUser($request);
        $userId = $this->resolveUserId($currentUser);
        if (!$this->chatRepository->sessionBelongsToUser($sessionId, $userId)) {
            return $this->json(['ok' => false, 'error' => 'Discussion introuvable.'], 404);
        }

        if (!str_starts_with($sessionId, 'offline-')) {
            try {
                $this->travelAiClient->deleteCard($sessionId, $cardId);
            } catch (RuntimeException) {
                // Local snapshots remain authoritative for the Symfony UI.
            }
        }
        $this->chatRepository->deleteCard($sessionId, $userId, $cardId);

        return $this->json(['ok' => true, 'session' => $this->hydrateSessionById($sessionId, $userId)]);
    }

    private function hydrateSessions(array $sessions, string $userId): array
    {
        return array_map(fn (array $session): array => [
            'id' => $session['id'],
            'title' => $session['title'] ?: 'Discussion',
            'lastActivity' => $this->formatTime((string) ($session['last_message_at'] ?? $session['updated_at'] ?? '')),
            'isFavorite' => (bool) ((int) ($session['is_favorite'] ?? 0)),
            'favoritedAt' => trim((string) ($session['favorited_at'] ?? '')) !== ''
                ? $this->formatTime((string) $session['favorited_at'])
                : '',
            'messages' => array_map(fn (array $message): array => [
                'role' => $message['role'],
                'content' => $message['content'],
                'time' => $this->formatTime((string) ($message['created_at'] ?? '')),
                'metadata' => $this->decodeJson((string) ($message['content_json'] ?? '')),
            ], $this->chatRepository->findMessages((string) $session['id'], $userId)),
            'cards' => $this->chatRepository->findCards((string) $session['id'], $userId),
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

    private function createTripProfileSession(string $userId, array $tripProfile): void
    {
        $formData = $this->buildFormDataFromTripProfile($tripProfile);
        $sessionId = $this->chatRepository->generateId();
        $title = $this->buildTripProfileTitle($tripProfile);

        try {
            $sessionId = $this->travelAiClient->createSession($userId, $title, 'fr', $sessionId, null, $formData);
        } catch (RuntimeException) {
            $sessionId = 'offline-'.$this->chatRepository->generateId();
            $title .= ' (hors ligne)';
        }

        $this->chatRepository->createSession($sessionId, $userId, $title, 'fr', $formData);
        $this->chatRepository->addMessage(
            $sessionId,
            $userId,
            'assistant',
            'J ai bien recu vos preferences de voyage. Je peux maintenant affiner les destinations, hotels, activites et budget avec vous.',
            ['handoff_profile' => $tripProfile]
        );
    }

    private function decodeTripProfile(string $rawProfile): array
    {
        $rawProfile = trim($rawProfile);
        if ($rawProfile === '') {
            return [];
        }

        $decoded = json_decode($rawProfile, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function buildFormDataFromTripProfile(array $tripProfile): array
    {
        $departure = trim((string) ($tripProfile['date_debut'] ?? ''));
        $returnDate = trim((string) ($tripProfile['date_fin'] ?? ''));
        $rawInterests = $tripProfile['interets'] ?? [];
        if (!is_array($rawInterests)) {
            $rawInterests = [$rawInterests];
        }
        $interests = array_values(array_filter(array_map('strval', $rawInterests)));
        $travelType = trim((string) ($tripProfile['type_voyage'] ?? ''));
        $travelStyle = $travelType !== '' && $travelType !== 'Tous les profils' ? [$travelType] : [];
        $continent = trim((string) ($tripProfile['continent'] ?? ''));
        $search = trim((string) ($tripProfile['search'] ?? ''));

        return [
            'adults' => max(1, (int) ($tripProfile['nb_adultes'] ?? 2)),
            'children' => max(0, (int) ($tripProfile['nb_enfants'] ?? 0)),
            'children_ages' => [],
            'trip_length_days' => $this->daysBetween($departure, $returnDate) ?? 7,
            'budget_usd' => max(0, (int) ($tripProfile['budget_max'] ?? $tripProfile['budget_min'] ?? 3000)),
            'travel_style' => array_values(array_unique([...$travelStyle, ...$interests])),
            'preferred_climate' => [],
            'preferred_activities' => $interests,
            'avoid_activities' => [],
            'departure_city' => '',
            'travel_month' => '',
            'depart_date' => $departure,
            'return_date' => $returnDate,
            'destination_hint' => $search,
            'continent_hint' => $continent,
            'language' => 'fr',
        ];
    }

    private function buildTripProfileTitle(array $tripProfile): string
    {
        $label = trim((string) ($tripProfile['search'] ?? $tripProfile['continent'] ?? ''));
        if ($label === '') {
            $label = trim((string) ($tripProfile['type_voyage'] ?? 'Voyage personnalise'));
        }

        return 'Preferences: '.mb_substr($label !== '' ? $label : 'voyage', 0, 42);
    }

    private function daysBetween(string $departure, string $returnDate): ?int
    {
        if ($departure === '' || $returnDate === '') {
            return null;
        }

        $start = strtotime($departure);
        $end = strtotime($returnDate);
        if ($start === false || $end === false || $end < $start) {
            return null;
        }

        return max(1, (int) floor(($end - $start) / 86400) + 1);
    }

    private function sendToAgent(string $sessionId, string $userId, string $message): array
    {
        try {
            return $this->travelAiClient->sendMessage($sessionId, $message, 'fr');
        } catch (RuntimeException $exception) {
            if (str_contains($exception->getMessage(), 'invalid_session') && $this->restoreRemoteSession($sessionId, $userId, $message)) {
                try {
                    return $this->travelAiClient->sendMessage($sessionId, $message, 'fr');
                } catch (RuntimeException $retryException) {
                    $exception = $retryException;
                }
            }

            return [
                'reply' => 'Je n arrive pas a joindre le service IA pour l instant ('.$exception->getMessage().'). Votre message a ete conserve. Reessayez dans quelques secondes.',
                'error' => $exception->getMessage(),
            ];
        }
    }

    private function persistAgentSnapshot(string $sessionId, string $userId, array $payload): void
    {
        $state = $this->extractAgentMetadata($payload);
        if ($state !== []) {
            $this->chatRepository->updateAgentState($sessionId, $userId, $state);
        }
        $card = $payload['card'] ?? null;
        $cardId = trim((string) ($payload['card_current_id'] ?? ''));
        if (is_array($card) && $card !== [] && $cardId !== '') {
            $this->chatRepository->saveCardSnapshot($sessionId, $userId, [
                'id' => $cardId,
                'card' => $card,
                'card_status' => (string) ($payload['card_status'] ?? 'generated'),
            ]);
        }
        $this->syncRemoteState($sessionId, $userId);
    }

    private function syncRemoteState(string $sessionId, string $userId): void
    {
        if (str_starts_with($sessionId, 'offline-')) {
            return;
        }

        try {
            $payload = $this->travelAiClient->getState($sessionId);
        } catch (RuntimeException) {
            return;
        }

        $state = $payload['state'] ?? null;
        if (is_array($state) && $state !== []) {
            $this->chatRepository->updateAgentState($sessionId, $userId, $state);
        }
    }

    private function extractAgentMetadata(array $payload): array
    {
        return array_filter([
            'chosen_destination' => $payload['chosen_destination'] ?? null,
            'card' => $payload['card'] ?? null,
            'card_status' => $payload['card_status'] ?? null,
            'card_current_id' => $payload['card_current_id'] ?? null,
            'token_usage' => $payload['token_usage'] ?? null,
            'last_turn_token_usage' => $payload['last_turn_token_usage'] ?? null,
            'language' => $payload['language'] ?? null,
        ], static fn ($value): bool => $value !== null && $value !== '');
    }

    private function restoreRemoteSession(string $sessionId, string $userId, ?string $pendingMessage = null): bool
    {
        $session = $this->chatRepository->findSession($sessionId, $userId);
        if ($session === null) {
            return false;
        }

        $formData = $this->decodeJson((string) ($session['form_data'] ?? ''));
        $state = $this->decodeJson((string) ($session['agent_state'] ?? ''));
        $messages = array_map(static fn (array $message): array => [
            'role' => (string) ($message['role'] ?? 'user'),
            'content' => (string) ($message['content'] ?? ''),
        ], $this->chatRepository->findMessages($sessionId, $userId));
        if ($pendingMessage !== null && $messages !== []) {
            $lastIndex = array_key_last($messages);
            $last = $messages[$lastIndex];
            if (($last['role'] ?? '') === 'user' && trim((string) ($last['content'] ?? '')) === trim($pendingMessage)) {
                array_pop($messages);
            }
        }
        $state['messages'] = $messages;
        $state['form_data'] = is_array($formData) && $formData !== [] ? $formData : $this->defaultFormData();
        $state['language'] = (string) ($state['language'] ?? $formData['language'] ?? 'fr');

        try {
            $this->travelAiClient->createSession(
                $userId,
                (string) ($session['title'] ?? 'Discussion'),
                (string) $state['language'],
                $sessionId,
                $state
            );
        } catch (RuntimeException) {
            return false;
        }

        return true;
    }

    private function decodeJson(string $json): array
    {
        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function defaultFormData(): array
    {
        return [
            'adults' => 2,
            'children' => 0,
            'children_ages' => [],
            'trip_length_days' => 7,
            'budget_usd' => 3000,
            'travel_style' => ['relax'],
            'preferred_climate' => ['mild'],
            'preferred_activities' => [],
            'avoid_activities' => [],
            'departure_city' => '',
            'travel_month' => '',
            'depart_date' => '',
            'return_date' => '',
        ];
    }

    private function offlineReply(string $prompt): string
    {
        $prompt = mb_strtolower($prompt);
        
        // Extraction du budget
        if (preg_match('/(\d+)\s*(euro?|eur|€|dollar|usd|\$)/i', $prompt, $matches)) {
            $budget = (int) $matches[1];
            if ($budget < 1000) {
                return "Pour un budget de {$budget}€, je vous suggère des destinations proches comme le Portugal, la Grèce ou le Maroc. Pensez aux vols low-cost et aux auberges de jeunesse pour optimiser vos dépenses.";
            } elseif ($budget < 2000) {
                return "Avec {$budget}€, vous pouvez envisager Bali, la Tunisie, la Turquie ou l'Europe de l'Est (Budapest, Prague). Ces destinations offrent un excellent rapport qualité-prix.";
            } else {
                return "Avec un budget de {$budget}€, vous avez de nombreuses options : Japon, Thaïlande, Maldives, ou même des circuits en Europe. Je peux vous aider à affiner selon vos préférences (plage, culture, aventure).";
            }
        }
        
        // Destinations spécifiques
        if (str_contains($prompt, 'plage') || str_contains($prompt, 'mer')) {
            return "Pour des vacances à la plage, je recommande : Maldives (luxe), Bali (culture + plage), Djerba (budget), Grèce (îles), ou Thaïlande (diversité). Quelle est votre période de voyage et votre budget ?";
        }
        if (str_contains($prompt, 'japon') || str_contains($prompt, 'tokyo')) {
            return "Le Japon est magnifique ! Tokyo est idéal au printemps (cerisiers en fleurs) ou en automne (feuillages). Prévoyez 2000-3000€ pour 10 jours. Intéressé par Kyoto, Osaka ou les Alpes japonaises aussi ?";
        }
        if (str_contains($prompt, 'italie') || str_contains($prompt, 'rome')) {
            return "L'Italie offre art, gastronomie et histoire. Rome, Florence et Venise sont incontournables. Budget moyen : 1500-2500€ pour une semaine. Préférez le printemps ou l'automne pour éviter la foule.";
        }
        if (str_contains($prompt, 'bali')) {
            return "Bali combine plages, temples et rizières. Budget : 1500-2000€ pour 2 semaines tout compris. Meilleure période : avril-octobre (saison sèche). Ubud pour la culture, Seminyak pour la plage.";
        }
        
        // Type de voyage
        if (str_contains($prompt, 'couple') || str_contains($prompt, 'romantique')) {
            return "Pour un voyage romantique, je suggère : Santorin (Grèce), Venise, Maldives, Paris, ou Bali. Donnez-moi votre budget et période pour affiner mes recommandations.";
        }
        if (str_contains($prompt, 'famille') || str_contains($prompt, 'enfant')) {
            return "Pour des vacances en famille : Espagne (Costa Brava), Portugal (Algarve), Tunisie, ou parcs à thème (Disneyland). Privilégiez des destinations avec plages et activités pour enfants.";
        }
        if (str_contains($prompt, 'aventure') || str_contains($prompt, 'trek')) {
            return "Pour l'aventure : Népal (trek), Islande (nature), Nouvelle-Zélande, Patagonie, ou Maroc (désert). Quel type d'activités vous intéresse ? Randonnée, escalade, safari ?";
        }
        
        // Saisons
        if (str_contains($prompt, 'été') || str_contains($prompt, 'aout') || str_contains($prompt, 'juillet')) {
            return "En été, privilégiez : Grèce, Croatie, Espagne, Scandinavie, ou destinations exotiques (Bali, Thaïlande). Évitez les destinations trop chaudes (Moyen-Orient, Afrique du Nord).";
        }
        if (str_contains($prompt, 'hiver') || str_contains($prompt, 'décembre') || str_contains($prompt, 'janvier')) {
            return "En hiver, optez pour : ski (Alpes, Pyrénées), soleil (Canaries, Thaïlande, Caraïbes), ou marchés de Noël (Allemagne, Alsace). Quel type d'ambiance recherchez-vous ?";
        }
        
        // Réponse générique améliorée
        return "Je suis en mode hors ligne, mais je peux vous aider ! Pour des recommandations personnalisées, indiquez-moi : votre budget, la période de voyage, le type de séjour (plage, culture, aventure) et le nombre de voyageurs. L'API Travel-AI complète sera bientôt disponible pour des suggestions encore plus précises.";
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
