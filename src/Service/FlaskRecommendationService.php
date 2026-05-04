<?php

namespace App\Service;

final class FlaskRecommendationService
{
    public function __construct(private readonly string $baseUrl = 'http://localhost:5000/api')
    {
    }

    public function verifierAPI(): bool
    {
        return $this->request('GET', '/health', null, 3) !== null;
    }

    public function obtenirContinents(): array
    {
        $response = $this->request('GET', '/continents', null, 5);
        $continents = is_array($response['continents'] ?? null) ? $response['continents'] : [];

        return array_values(array_filter(array_map('strval', $continents)));
    }

    public function obtenirInterets(): array
    {
        $response = $this->request('GET', '/interests', null, 5);
        $interests = is_array($response['interests'] ?? null) ? $response['interests'] : [];

        return array_values(array_filter(array_map('strval', $interests)));
    }

    public function obtenirRecommandations(
        float $budgetMin,
        float $budgetMax,
        string $dateDebut,
        string $dateFin,
        string $typeVoyage,
        int $nbAdultes,
        int $nbEnfants,
        array $interets,
        array $continents,
    ): ?array {
        return $this->request('POST', '/recommendations', [
            'budget_min' => $budgetMin,
            'budget_max' => $budgetMax,
            'date_debut' => $dateDebut,
            'date_fin' => $dateFin,
            'type_voyage' => $typeVoyage,
            'nb_adultes' => $nbAdultes,
            'nb_enfants' => $nbEnfants,
            'interets' => array_values($interets),
            'continents' => array_values($continents),
        ], 10);
    }

    public function obtenirDetailsPackage(
        string $destination,
        string $continent,
        float $budgetMin,
        float $budgetMax,
        int $duree,
        int $nbAdultes,
        int $nbEnfants,
        array $interets,
    ): ?array {
        return $this->request('POST', '/package', [
            'destination' => $destination,
            'continent' => $continent,
            'budget_min' => $budgetMin,
            'budget_max' => $budgetMax,
            'duree' => $duree,
            'nb_adultes' => $nbAdultes,
            'nb_enfants' => $nbEnfants,
            'interets' => array_values($interets),
        ], 10);
    }

    private function request(string $method, string $path, ?array $payload = null, int $timeout = 8): ?array
    {
        $url = rtrim($this->baseUrl, '/').'/'.ltrim($path, '/');
        $body = $payload !== null ? json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
        if ($payload !== null && $body === false) {
            return null;
        }

        if (function_exists('curl_init')) {
            return $this->curlRequest($url, $method, $body, $timeout);
        }

        return $this->streamRequest($url, $method, $body, $timeout);
    }

    private function curlRequest(string $url, string $method, ?string $body, int $timeout): ?array
    {
        $curl = curl_init($url);
        if ($curl === false) {
            return null;
        }

        $headers = ['Accept: application/json'];
        if ($body !== null) {
            $headers[] = 'Content-Type: application/json';
            curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
        }

        curl_setopt_array($curl, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => min(5, $timeout),
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_HTTPHEADER => $headers,
        ]);

        $response = curl_exec($curl);
        $statusCode = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);

        if (!is_string($response) || $statusCode < 200 || $statusCode >= 300) {
            return null;
        }

        return $this->decodeResponse($response);
    }

    private function streamRequest(string $url, string $method, ?string $body, int $timeout): ?array
    {
        $headers = "Accept: application/json\r\n";
        if ($body !== null) {
            $headers .= "Content-Type: application/json\r\n";
        }

        $context = stream_context_create([
            'http' => [
                'method' => $method,
                'header' => $headers,
                'content' => $body ?? '',
                'ignore_errors' => true,
                'timeout' => $timeout,
            ],
        ]);

        $response = @file_get_contents($url, false, $context);
        if (!is_string($response)) {
            return null;
        }

        $statusLine = $http_response_header[0] ?? '';
        if (!preg_match('/\s2\d\d\s/', $statusLine)) {
            return null;
        }

        return $this->decodeResponse($response);
    }

    private function decodeResponse(string $response): ?array
    {
        $decoded = json_decode($response, true);

        return is_array($decoded) ? $decoded : null;
    }
}
