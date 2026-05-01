<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;
use Exception;

class MonumentRecognizer
{
    private HttpClientInterface $httpClient;
    private LoggerInterface $logger;
    private string $openrouterApiKey;
    private string $openrouterModel;

    public function __construct(
        HttpClientInterface $httpClient,
        LoggerInterface $logger,
        string $openrouterApiKey = '',
        string $openrouterModel = ''
    ) {
        $this->httpClient = $httpClient;
        $this->logger = $logger;
        // Use provided args, fallback to env, fallback to default vision model
        $this->openrouterApiKey = $openrouterApiKey ?: ($_ENV['OPENROUTER_API_KEY'] ?? '');
        $this->openrouterModel = $openrouterModel ?: ($_ENV['OPENROUTER_MODEL'] ?? 'google/gemini-2.0-flash-exp:free');
    }

    /**
     * Recognize monument from image using FREE OpenRouter API
     */
    public function recognize(string $imagePath): array
    {
        try {
            // Validate image file exists
            if (!file_exists($imagePath)) {
                throw new Exception("Image file not found: {$imagePath}");
            }

            // Check file size
            $fileSize = filesize($imagePath);
            if ($fileSize === false || $fileSize > 10 * 1024 * 1024) {
                throw new Exception('Image file too large or unreadable (max 10MB)');
            }

            $this->logger->critical('MONUMENT RECOGNITION STARTED', [
                'model' => $this->openrouterModel,
                'image' => basename($imagePath),
                'imageSize' => $fileSize
            ]);

            // Use OpenRouter free vision model
            $result = $this->recognizeWithOpenRouter($imagePath);
            
            if (!empty($result['name'])) {
                return [
                    'success' => true,
                    'name' => $result['name'],
                    'city' => $result['city'] ?? '',
                    'country' => $result['country'] ?? '',
                    'description' => $result['description'] ?? '',
                    'confidence' => $result['confidence'] ?? 0.7,
                    'provider' => 'openrouter_free'
                ];
            }
        } catch (Exception $e) {
            $this->logger->error('Monument recognition failed: ' . $e->getMessage(), [
                'image_path' => $imagePath,
                'trace' => $e->getTraceAsString()
            ]);
        }

        // API failed or no monument detected
        return [
            'success' => false,
            'name' => '',
            'city' => '',
            'country' => '',
            'description' => '',
            'confidence' => 0.0,
            'provider' => 'none',
            'error' => 'Impossible de reconnaître le monument. Vérifiez que l\'image est claire et montre un monument ou bâtiment historique.'
        ];
    }

    /**
     * Recognize monument using FREE OpenRouter API
     */
    private function recognizeWithOpenRouter(string $imagePath): array
    {
        if (empty($this->openrouterApiKey)) {
            throw new Exception('OpenRouter API key not configured');
        }

        $this->logger->info('Monument recognition started', [
            'model' => $this->openrouterModel,
            'image' => basename($imagePath),
            'imageSize' => strlen($imageData)
        ]);

        // Read and encode image
        $imageData = file_get_contents($imagePath);
        if ($imageData === false) {
            throw new Exception('Cannot read image file: ' . $imagePath);
        }

        $base64Image = base64_encode($imageData);
        $mimeType = mime_content_type($imagePath) ?: 'image/jpeg';

        $payload = [
            'model' => $this->openrouterModel,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => 'Analysez cette image et identifiez si elle contient un monument, un lieu historique ou un bâtiment important. Si oui, fournissez uniquement un objet JSON valide avec: nom, ville, pays, description. Si aucun monument n\'est détecté, retournez {"nom": "Aucun monument détecté"}. Format JSON strict.'
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
            'max_tokens' => 500,
            'response_format' => ['type' => 'json_object']
        ];

        $response = $this->httpClient->request('POST', 'https://openrouter.ai/api/v1/chat/completions', [
            'json' => $payload,
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $this->openrouterApiKey,
                'HTTP-Referer' => 'http://localhost:8000',
                'X-Title' => 'EasyTravel Monument Recognition'
            ]
        ]);

        if ($response->getStatusCode() !== 200) {
            throw new Exception('OpenRouter API request failed: ' . $response->getStatusCode());
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
            return $this->parseFrenchTextResponse($content);
        }

        if (!isset($result['nom']) || $result['nom'] === 'Aucun monument détecté') {
            return $this->createEmptyResult();
        }

        return [
            'name' => $result['nom'] ?? '',
            'city' => $result['ville'] ?? '',
            'country' => $result['pays'] ?? '',
            'description' => $result['description'] ?? '',
            'confidence' => 0.8 // High confidence for OpenRouter free models
        ];
    }

    /**
     * Parse French text response when JSON parsing fails
     */
    private function parseFrenchTextResponse(string $content): array
    {
        // Simple French text parsing
        $lines = explode("\n", $content);
        $result = ['name' => '', 'city' => '', 'country' => '', 'description' => ''];
        
        foreach ($lines as $line) {
            if (stripos($line, 'nom') !== false) {
                $result['name'] = trim(str_replace(['Nom:', 'nom:'], '', $line));
            } elseif (stripos($line, 'ville') !== false) {
                $result['city'] = trim(str_replace(['Ville:', 'ville:'], '', $line));
            } elseif (stripos($line, 'pays') !== false) {
                $result['country'] = trim(str_replace(['Pays:', 'pays:'], '', $line));
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
