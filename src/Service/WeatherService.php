<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

final class WeatherService
{
    private const API_KEY = 'd937c4d5e323ba07156fa97da211a4ae';
    private const API_URL = 'https://api.openweathermap.org/data/2.5/weather';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    public function getWeatherForDestination(string $city, string $country = ''): ?array
    {
        try {
            $query = $city;
            if ($country !== '') {
                $query .= ','.$country;
            }

            $response = $this->httpClient->request('GET', self::API_URL, [
                'query' => [
                    'q' => $query,
                    'appid' => self::API_KEY,
                    'units' => 'metric',
                    'lang' => 'fr',
                ],
                'timeout' => 5,
            ]);

            if ($response->getStatusCode() !== 200) {
                return null;
            }

            $data = $response->toArray();

            return [
                'temperature' => round((float) ($data['main']['temp'] ?? 0)),
                'feels_like' => round((float) ($data['main']['feels_like'] ?? 0)),
                'humidity' => (int) ($data['main']['humidity'] ?? 0),
                'description' => (string) ($data['weather'][0]['description'] ?? 'Non disponible'),
                'icon' => (string) ($data['weather'][0]['icon'] ?? '01d'),
                'wind_speed' => round((float) ($data['wind']['speed'] ?? 0), 1),
                'pressure' => (int) ($data['main']['pressure'] ?? 0),
            ];
        } catch (\Exception) {
            return null;
        }
    }
}
