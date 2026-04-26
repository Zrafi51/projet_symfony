<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class WeatherController extends AbstractController
{
    private $httpClient;

    public function __construct(HttpClientInterface $httpClient)
    {
        $this->httpClient = $httpClient;
    }

    #[Route('/weather', name: 'app_weather')]
    public function index(Request $request): Response
    {
        $form = $this->createFormBuilder()
            ->add('destination', TextType::class, [
                'label' => 'Destination',
                'attr' => [
                    'placeholder' => 'Enter city name...',
                    'class' => 'form-control'
                ]
            ])
            ->add('search', SubmitType::class, [
                'label' => 'Get Weather',
                'attr' => ['class' => 'btn btn-primary mt-2']
            ])
            ->getForm();

        $weatherData = null;
        $error = null;

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $destination = $form->get('destination')->getData();
            
            try {
                $weatherData = $this->getWeatherData($destination);
            } catch (\Exception $e) {
                $error = 'Unable to fetch weather data. Please check the city name and try again.';
            }
        }

        return $this->render('weather/index.html.twig', [
            'form' => $form->createView(),
            'weatherData' => $weatherData,
            'error' => $error
        ]);
    }

    private function getWeatherData(string $city): array
    {
        // Using OpenWeatherMap API (you'll need to get an API key)
        $apiKey = $_ENV['WEATHER_API_KEY'] ?? 'demo_key';
        $url = "https://api.openweathermap.org/data/2.5/weather?q={$city}&appid={$apiKey}&units=metric";

        $response = $this->httpClient->request('GET', $url);
        
        if ($response->getStatusCode() !== 200) {
            throw new \Exception('Weather API request failed');
        }

        $data = $response->toArray();

        return [
            'city' => $data['name'],
            'country' => $data['sys']['country'] ?? '',
            'temperature' => $data['main']['temp'],
            'feels_like' => $data['main']['feels_like'],
            'humidity' => $data['main']['humidity'],
            'pressure' => $data['main']['pressure'],
            'description' => $data['weather'][0]['description'],
            'icon' => $data['weather'][0]['icon'],
            'wind_speed' => $data['wind']['speed'] ?? 0,
            'wind_direction' => $data['wind']['deg'] ?? 0,
            'visibility' => $data['visibility'] ?? 0,
            'timestamp' => $data['dt']
        ];
    }
}
