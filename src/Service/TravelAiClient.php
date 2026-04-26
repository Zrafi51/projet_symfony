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

    public function createSession(string $userId, string $title, string $language = 'fr'): string
    {
        $response = $this->post('/api/session', [
            'form_data' => $this->defaultFormData(),
            'user_id' => $userId,
            'title' => $title,
            'language' => $this->normalizeLanguage($language),
            'prompt_bundle' => new \stdClass(),
        ]);

        $sessionId = trim((string) ($response['session_id'] ?? ''));
        if ($sessionId === '') {
            throw new RuntimeException('FastAPI n a pas renvoye session_id.');
        }

        return $sessionId;
    }

    public function sendMessage(string $sessionId, string $message, string $language = 'fr'): string
    {
        $response = $this->post('/api/chat', [
            'session_id' => $sessionId,
            'message' => $message,
            'language' => $this->normalizeLanguage($language),
            'prompt_bundle' => new \stdClass(),
        ]);

        $reply = trim((string) ($response['reply'] ?? ''));
        if ($reply !== '') {
            return $reply;
        }

        throw new RuntimeException(trim((string) ($response['error'] ?? 'Reponse FastAPI vide.')));
    }

    private function post(string $endpoint, array $payload): array
    {
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($body === false) {
            throw new RuntimeException('Payload JSON invalide.');
        }

        $url = $this->getBaseUrl().$endpoint;
        $response = null;

        if (function_exists('curl_init')) {
            $curl = curl_init($url);
            curl_setopt_array($curl, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $body,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 8,
                CURLOPT_TIMEOUT => 45,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json; charset=UTF-8', 'Accept: application/json'],
            ]);
            $response = curl_exec($curl);
            $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
            curl_close($curl);
            if (!is_string($response) || $status >= 400 || $status <= 0) {
                throw new RuntimeException('FastAPI indisponible.');
            }
        } else {
            $context = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => "Content-Type: application/json; charset=UTF-8\r\nAccept: application/json\r\n",
                    'content' => $body,
                    'ignore_errors' => true,
                    'timeout' => 45,
                ],
            ]);
            $response = @file_get_contents($url, false, $context);
            if (!is_string($response)) {
                throw new RuntimeException('FastAPI indisponible.');
            }
        }

        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Reponse FastAPI invalide.');
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
}
