<?php

namespace App\Service;

use RuntimeException;

final class TravelAiClient
{
    private string $baseUrl;

    public function __construct(?string $baseUrl = null)
    {
        $candidate = trim((string) ($baseUrl ?: getenv('EASY_TRAVEL_AGENT_URL') ?: 'http://127.0.0.1:8000'));
        $this->baseUrl = rtrim($candidate, '/');
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    public function createSession(string $userId, string $title, string $language = 'fr', ?string $sessionId = null, ?array $state = null, ?array $formData = null): string
    {
        $payload = [
            'form_data' => is_array($formData) && $formData !== [] ? $formData : $this->defaultFormData(),
            'user_id' => $userId,
            'title' => $title,
            'language' => $this->normalizeLanguage($language),
            'prompt_bundle' => new \stdClass(),
        ];
        if ($sessionId !== null && trim($sessionId) !== '') {
            $payload['session_id'] = $sessionId;
        }
        if ($state !== null) {
            $payload['state'] = $state;
        }

        $response = $this->post('/api/session', $payload);

        $sessionId = trim((string) ($response['session_id'] ?? ''));
        if ($sessionId === '') {
            throw new RuntimeException('FastAPI n a pas renvoye session_id.');
        }

        return $sessionId;
    }

    public function sendMessage(string $sessionId, string $message, string $language = 'fr'): array
    {
        $response = $this->post('/api/chat', [
            'session_id' => $sessionId,
            'message' => $message,
            'language' => $this->normalizeLanguage($language),
            'prompt_bundle' => new \stdClass(),
        ]);

        $reply = trim((string) ($response['reply'] ?? ''));
        if ($reply !== '') {
            return $response;
        }

        throw new RuntimeException(trim((string) ($response['error'] ?? 'Reponse FastAPI vide.')));
    }

    public function generateCard(string $sessionId, string $language = 'fr'): array
    {
        return $this->post('/api/sessions/'.$this->encodePath($sessionId).'/card', [
            'language' => $this->normalizeLanguage($language),
        ]);
    }

    public function listCards(string $sessionId): array
    {
        return $this->get('/api/sessions/'.$this->encodePath($sessionId).'/cards');
    }

    public function getState(string $sessionId): array
    {
        return $this->get('/api/sessions/'.$this->encodePath($sessionId).'/state');
    }

    public function deleteCard(string $sessionId, string $cardId): array
    {
        return $this->delete('/api/sessions/'.$this->encodePath($sessionId).'/card/'.$this->encodePath($cardId));
    }

    public function reloadPrompts(): void
    {
        try {
            $this->post('/api/prompts/reload', []);
        } catch (RuntimeException) {
            // Prompt reload is best effort; FastAPI will refresh later through its TTL.
        }
    }

    private function post(string $endpoint, array $payload): array
    {
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($body === false) {
            throw new RuntimeException('Payload JSON invalide.');
        }

        return $this->request('POST', $endpoint, $body);
    }

    private function get(string $endpoint): array
    {
        return $this->request('GET', $endpoint);
    }

    private function delete(string $endpoint): array
    {
        return $this->request('DELETE', $endpoint);
    }

    private function request(string $method, string $endpoint, ?string $body = null): array
    {
        $url = $this->getBaseUrl().$endpoint;
        $response = null;
        $status = 0;

        if (function_exists('curl_init')) {
            $curl = curl_init($url);
            $options = [
                CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 8,
                CURLOPT_TIMEOUT => 45,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json; charset=UTF-8', 'Accept: application/json'],
            ];
            if ($body !== null) {
                $options[CURLOPT_POSTFIELDS] = $body;
            }
            curl_setopt_array($curl, $options);
            $response = curl_exec($curl);
            $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
            curl_close($curl);
            if (!is_string($response) || $status <= 0) {
                throw new RuntimeException('FastAPI indisponible.');
            }
        } else {
            $header = "Content-Type: application/json; charset=UTF-8\r\nAccept: application/json\r\n";
            $context = stream_context_create([
                'http' => [
                    'method' => $method,
                    'header' => $header,
                    'content' => $body ?? '',
                    'ignore_errors' => true,
                    'timeout' => 45,
                ],
            ]);
            $response = @file_get_contents($url, false, $context);
            if (!is_string($response)) {
                throw new RuntimeException('FastAPI indisponible.');
            }
            $status = $this->extractStreamStatus($http_response_header ?? []);
        }

        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Reponse FastAPI invalide.');
        }
        if ($status >= 400) {
            throw new RuntimeException(trim((string) ($decoded['error'] ?? 'FastAPI indisponible.')));
        }

        return $decoded;
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

    private function normalizeLanguage(string $language): string
    {
        return str_starts_with(strtolower(trim($language)), 'en') ? 'en' : 'fr';
    }

    private function encodePath(string $value): string
    {
        return rawurlencode($value);
    }

    private function extractStreamStatus(array $headers): int
    {
        $first = (string) ($headers[0] ?? '');
        if (preg_match('/\s(\d{3})\s/', $first, $matches) === 1) {
            return (int) $matches[1];
        }

        return 200;
    }
}
