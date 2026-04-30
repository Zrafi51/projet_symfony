<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;
use Exception;

class MonumentRecognizer
{
    private HttpClientInterface $httpClient;
    private LoggerInterface $logger;
    private string $googleVisionApiKey;
    private string $openaiApiKey;

    public function __construct(
        HttpClientInterface $httpClient,
        LoggerInterface $logger,
        string $googleVisionApiKey = '',
        string $openaiApiKey = ''
    ) {
        $this->httpClient = $httpClient;
        $this->logger = $logger;
        $this->googleVisionApiKey = $googleVisionApiKey ?: $_ENV['GOOGLE_VISION_API_KEY'] ?? '';
        $this->openaiApiKey = $openaiApiKey ?: $_ENV['OPENAI_API_KEY'] ?? '';
    }

    /**
     * Recognize monument from image using Google Vision API with OpenAI fallback
     */
    public function recognize(string $imagePath): array
    {
        try {
            // First try Google Vision API
            $result = $this->recognizeWithGoogleVision($imagePath);
            
            if ($result['confidence'] >= 0.7) {
                return [
                    'success' => true,
                    'name' => $result['name'],
                    'city' => $result['city'],
                    'country' => $result['country'],
                    'description' => $result['description'],
                    'confidence' => $result['confidence'],
                    'provider' => 'google_vision'
                ];
            }
        } catch (Exception $e) {
            $this->logger->warning('Google Vision API failed: ' . $e->getMessage());
        }

        // Fallback to OpenAI GPT-4 Vision
        try {
            $result = $this->recognizeWithOpenAI($imagePath);
            
            if ($result['name'] !== '') {
                return [
                    'success' => true,
                    'name' => $result['name'],
                    'city' => $result['city'],
                    'country' => $result['country'],
                    'description' => $result['description'],
                    'confidence' => $result['confidence'] ?? 0.6, // OpenAI confidence is estimated
                    'provider' => 'openai'
                ];
            }
        } catch (Exception $e) {
            $this->logger->error('OpenAI API failed: ' . $e->getMessage());
        }

        // Both APIs failed
        return [
            'success' => false,
            'name' => '',
            'city' => '',
            'country' => '',
            'description' => '',
            'confidence' => 0.0,
            'provider' => 'none',
            'error' => 'Unable to recognize monument. Please try with a clearer photo.'
        ];
    }

    /**
     * Recognize monument using Google Vision API Landmark Detection
     */
    private function recognizeWithGoogleVision(string $imagePath): array
    {
        if (empty($this->googleVisionApiKey)) {
            throw new Exception('Google Vision API key not configured');
        }

        // Read and encode image
        $imageData = file_get_contents($imagePath);
        if ($imageData === false) {
            throw new Exception('Cannot read image file');
        }

        $base64Image = base64_encode($imageData);
        
        $payload = [
            'requests' => [
                [
                    'image' => [
                        'content' => $base64Image
                    ],
                    'features' => [
                        [
                            'type' => 'LANDMARK_DETECTION',
                            'maxResults' => 3
                        ]
                    ]
                ]
            ]
        ];

        $response = $this->httpClient->request('POST', 'https://vision.googleapis.com/v1/images:annotate?key=' . $this->googleVisionApiKey, [
            'json' => $payload,
            'headers' => [
                'Content-Type' => 'application/json'
            ]
        ]);

        if ($response->getStatusCode() !== 200) {
            throw new Exception('Google Vision API request failed: ' . $response->getStatusCode());
        }

        $data = $response->toArray();
        
        if (!isset($data['responses'][0]['landmarkAnnotations'])) {
            return $this->createEmptyResult();
        }

        $landmarks = $data['responses'][0]['landmarkAnnotations'];
        
        if (empty($landmarks)) {
            return $this->createEmptyResult();
        }

        // Get the most confident landmark
        $landmark = $landmarks[0];
        $confidence = $landmark['score'] ?? 0.0;
        $name = $landmark['description'] ?? '';
        
        // Extract location information
        $locations = $landmark['locations'] ?? [];
        $city = '';
        $country = '';
        
        if (!empty($locations)) {
            $latLng = $locations[0]['latLng'] ?? [];
            $lat = $latLng['latitude'] ?? null;
            $lng = $latLng['longitude'] ?? null;
            
            if ($lat && $lng) {
                $locationInfo = $this->getLocationFromCoordinates($lat, $lng);
                $city = $locationInfo['city'];
                $country = $locationInfo['country'];
            }
        }

        // Generate description
        $description = $this->generateMonumentDescription($name, $city, $country);

        return [
            'name' => $name,
            'city' => $city,
            'country' => $country,
            'description' => $description,
            'confidence' => $confidence
        ];
    }

    /**
     * Recognize monument using OpenAI GPT-4 Vision
     */
    private function recognizeWithOpenAI(string $imagePath): array
    {
        if (empty($this->openaiApiKey)) {
            throw new Exception('OpenAI API key not configured');
        }

        // Read and encode image
        $imageData = file_get_contents($imagePath);
        if ($imageData === false) {
            throw new Exception('Cannot read image file');
        }

        $base64Image = base64_encode($imageData);
        $mimeType = mime_content_type($imagePath) ?: 'image/jpeg';

        $payload = [
            'model' => 'gpt-4-vision-preview',
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => 'Analyze this image and identify if it contains a monument, landmark, or historical building. If yes, provide the monument name, city, country, and a brief description. If not, respond with "No monument detected". Format your response as JSON: {"name": "...", "city": "...", "country": "...", "description": "..."}'
                        ],
                        [
                            'type' => 'image_url',
                            'image_url' => [
                                'url' => "data:{$mimeType};base64,{$base64Image}"
                            ]
                        ]
                    ]
                ]
            ],
            'max_tokens' => 300
        ];

        $response = $this->httpClient->request('POST', 'https://api.openai.com/v1/chat/completions', [
            'json' => $payload,
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $this->openaiApiKey
            ]
        ]);

        if ($response->getStatusCode() !== 200) {
            throw new Exception('OpenAI API request failed: ' . $response->getStatusCode());
        }

        $data = $response->toArray();
        
        if (!isset($data['choices'][0]['message']['content'])) {
            return $this->createEmptyResult();
        }

        $content = $data['choices'][0]['message']['content'];
        
        // Try to parse JSON response
        $result = json_decode($content, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            // If JSON parsing fails, try to extract information from text
            return $this->parseTextResponse($content);
        }

        if (!isset($result['name']) || $result['name'] === 'No monument detected') {
            return $this->createEmptyResult();
        }

        return [
            'name' => $result['name'] ?? '',
            'city' => $result['city'] ?? '',
            'country' => $result['country'] ?? '',
            'description' => $result['description'] ?? '',
            'confidence' => 0.6 // Estimated confidence for OpenAI
        ];
    }

    /**
     * Get location information from coordinates (using reverse geocoding)
     */
    private function getLocationFromCoordinates(float $lat, float $lng): array
    {
        try {
            // Using Nominatim (OpenStreetMap) for reverse geocoding
            $response = $this->httpClient->request('GET', 'https://nominatim.openstreetmap.org/reverse', [
                'query' => [
                    'format' => 'json',
                    'lat' => $lat,
                    'lon' => $lng,
                    'zoom' => 10
                ]
            ]);

            if ($response->getStatusCode() === 200) {
                $data = $response->toArray();
                $address = $data['address'] ?? [];
                
                return [
                    'city' => $address['city'] ?? $address['town'] ?? $address['village'] ?? '',
                    'country' => $address['country'] ?? ''
                ];
            }
        } catch (Exception $e) {
            $this->logger->warning('Reverse geocoding failed: ' . $e->getMessage());
        }

        return ['city' => '', 'country' => ''];
    }

    /**
     * Generate a description for the monument
     */
    private function generateMonumentDescription(string $name, string $city, string $country): string
    {
        $location = $city && $country ? "in {$city}, {$country}" : ($country ?: '');
        
        return "The {$name} is a notable monument {$location}. This landmark represents an important cultural or historical significance and attracts visitors from around the world.";
    }

    /**
     * Parse text response from OpenAI when JSON parsing fails
     */
    private function parseTextResponse(string $content): array
    {
        // Simple text parsing - you might want to enhance this
        $lines = explode("\n", $content);
        $result = ['name' => '', 'city' => '', 'country' => '', 'description' => ''];
        
        foreach ($lines as $line) {
            if (stripos($line, 'name') !== false) {
                $result['name'] = trim(str_replace(['Name:', 'name:'], '', $line));
            } elseif (stripos($line, 'city') !== false) {
                $result['city'] = trim(str_replace(['City:', 'city:'], '', $line));
            } elseif (stripos($line, 'country') !== false) {
                $result['country'] = trim(str_replace(['Country:', 'country:'], '', $line));
            } elseif (stripos($line, 'description') !== false) {
                $result['description'] = trim(str_replace(['Description:', 'description:'], '', $line));
            }
        }
        
        return $result;
    }

    /**
     * Create empty result when no monument is detected
     */
    private function createEmptyResult(): array
    {
        return [
            'name' => '',
            'city' => '',
            'country' => '',
            'description' => '',
            'confidence' => 0.0
        ];
    }
}
