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
                    'placeholder' => 'Entrez le nom de la ville...',
                    'class' => 'form-control'
                ]
            ])
            ->add('search', SubmitType::class, [
                'label' => 'Obtenir la météo',
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
                $error = 'Impossible de récupérer les données météo. Veuillez vérifier le nom de la ville et réessayer.';
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
        // Using OpenWeatherMap API 
        $apiKey = 'd937c4d5e323ba07156fa97da211a4ae';
        $url = "https://api.openweathermap.org/data/2.5/weather?q={$city}&appid={$apiKey}&units=metric";

        $response = $this->httpClient->request('GET', $url);
        
        if ($response->getStatusCode() !== 200) {
            throw new \Exception('La requête API météo a échoué');
        }

        $data = $response->toArray();

        return [
            'city' => $data['name'],
            'country' => $this->translateCountry($data['sys']['country'] ?? ''),
            'temperature' => $data['main']['temp'],
            'feels_like' => $data['main']['feels_like'],
            'humidity' => $data['main']['humidity'],
            'pressure' => $data['main']['pressure'],
            'description' => $this->translateWeatherDescription($data['weather'][0]['description']),
            'icon' => $data['weather'][0]['icon'],
            'wind_speed' => $data['wind']['speed'] ?? 0,
            'wind_direction' => $data['wind']['deg'] ?? 0,
            'visibility' => $data['visibility'] ?? 0,
            'timestamp' => $data['dt'],
            'local_time' => date('H:i', $data['dt']),
            'timezone_offset' => $data['timezone'] ?? 0
        ];
    }

    private function translateWeatherDescription(string $description): string
    {
        $translations = [
            'clear sky' => 'ciel dégagé',
            'few clouds' => 'quelques nuages',
            'scattered clouds' => 'nuages épars',
            'broken clouds' => 'nuages fragmentés',
            'shower rain' => 'averse',
            'rain' => 'pluie',
            'thunderstorm' => 'orage',
            'snow' => 'neige',
            'mist' => 'brume',
            'fog' => 'brouillard',
            'haze' => 'brume sèche',
            'dust' => 'poussière',
            'sand' => 'sable',
            'ash' => 'cendres',
            'squall' => 'grain',
            'tornado' => 'tornade',
            'clouds' => 'nuageux',
            'overcast clouds' => 'ciel couvert',
            'light rain' => 'pluie légère',
            'moderate rain' => 'pluie modérée',
            'heavy intensity rain' => 'pluie intense',
            'very heavy rain' => 'très forte pluie',
            'extreme rain' => 'pluie extrême',
            'freezing rain' => 'pluie verglaçante',
            'light intensity shower rain' => 'averse légère',
            'heavy intensity shower rain' => 'averse intense',
            'ragged shower rain' => 'averse irrégulière',
            'light snow' => 'neige légère',
            'heavy snow' => 'neige forte',
            'sleet' => 'grésil',
            'shower sleet' => 'averse de grésil',
            'light rain and snow' => 'pluie et neige légères',
            'rain and snow' => 'pluie et neige',
            'light shower snow' => 'averse de neige légère',
            'shower snow' => 'averse de neige',
            'heavy shower snow' => 'averse de neige intense',
            'drizzle' => 'bruine',
            'light intensity drizzle' => 'bruine légère',
            'heavy intensity drizzle' => 'bruine intense',
            'light intensity drizzle rain' => 'pluie bruine légère',
            'drizzle rain' => 'pluie bruine',
            'heavy intensity drizzle rain' => 'pluie bruine intense',
            'shower drizzle' => 'averse de bruine',
            'tornado' => 'tornade',
            'tropical storm' => 'tempête tropicale',
            'hurricane' => 'ouragan',
            'cold' => 'froid',
            'hot' => 'chaud',
            'windy' => 'venteux',
            'hail' => 'grêle'
        ];

        return $translations[strtolower($description)] ?? $description;
    }

    private function translateCountry(string $countryInput): string
    {
        // Handle both country codes (TN) and full country names (Tunisia)
        $countries = [
            // Country codes
            'AF' => 'Afghanistan', 'AL' => 'Albanie', 'DZ' => 'Algérie', 'AD' => 'Andorre',
            'AO' => 'Angola', 'AG' => 'Antigua-et-Barbuda', 'AR' => 'Argentine', 'AM' => 'Arménie',
            'AU' => 'Australie', 'AT' => 'Autriche', 'AZ' => 'Azerbaïdjan', 'BS' => 'Bahamas',
            'BH' => 'Bahreïn', 'BD' => 'Bangladesh', 'BB' => 'Barbade', 'BY' => 'Biélorussie',
            'BE' => 'Belgique', 'BZ' => 'Belize', 'BJ' => 'Bénin', 'BT' => 'Bhoutan',
            'BO' => 'Bolivie', 'BA' => 'Bosnie-Herzégovine', 'BW' => 'Botswana', 'BR' => 'Brésil',
            'BN' => 'Brunei', 'BG' => 'Bulgarie', 'BF' => 'Burkina Faso', 'BI' => 'Burundi',
            'CV' => 'Cap-Vert', 'KH' => 'Cambodge', 'CM' => 'Cameroun', 'CA' => 'Canada',
            'CF' => 'Centrafrique', 'TD' => 'Tchad', 'CL' => 'Chili', 'CN' => 'Chine',
            'CO' => 'Colombie', 'KM' => 'Comores', 'CG' => 'Congo-Brazzaville', 'CD' => 'Congo-Kinshasa',
            'CR' => 'Costa Rica', 'CI' => 'Côte d\'Ivoire', 'HR' => 'Croatie', 'CU' => 'Cuba',
            'CY' => 'Chypre', 'CZ' => 'Tchéquie', 'DK' => 'Danemark', 'DJ' => 'Djibouti',
            'DM' => 'Dominique', 'DO' => 'République dominicaine', 'EC' => 'Équateur', 'EG' => 'Égypte',
            'SV' => 'Salvador', 'GQ' => 'Guinée équatoriale', 'ER' => 'Érythrée', 'EE' => 'Estonie',
            'SZ' => 'Eswatini', 'ET' => 'Éthiopie', 'FJ' => 'Fidji', 'FI' => 'Finlande',
            'FR' => 'France', 'GA' => 'Gabon', 'GM' => 'Gambie', 'GE' => 'Géorgie',
            'DE' => 'Allemagne', 'GH' => 'Ghana', 'GR' => 'Grèce', 'GD' => 'Grenade',
            'GT' => 'Guatemala', 'GN' => 'Guinée', 'GW' => 'Guinée-Bissau', 'GY' => 'Guyana',
            'HT' => 'Haïti', 'HN' => 'Honduras', 'HU' => 'Hongrie', 'IS' => 'Islande',
            'IN' => 'Inde', 'ID' => 'Indonésie', 'IR' => 'Iran', 'IQ' => 'Irak',
            'IE' => 'Irlande', 'IL' => 'Israël', 'IT' => 'Italie', 'JM' => 'Jamaïque',
            'JP' => 'Japon', 'JO' => 'Jordanie', 'KZ' => 'Kazakhstan', 'KE' => 'Kenya',
            'KI' => 'Kiribati', 'KP' => 'Corée du Nord', 'KR' => 'Corée du Sud', 'KW' => 'Koweït',
            'KG' => 'Kirghizistan', 'LA' => 'Laos', 'LV' => 'Lettonie', 'LB' => 'Liban',
            'LS' => 'Lesotho', 'LR' => 'Libéria', 'LY' => 'Libye', 'LI' => 'Liechtenstein',
            'LT' => 'Lituanie', 'LU' => 'Luxembourg', 'MG' => 'Madagascar', 'MW' => 'Malawi',
            'MY' => 'Malaisie', 'MV' => 'Maldives', 'ML' => 'Mali', 'MT' => 'Malte',
            'MH' => 'Îles Marshall', 'MR' => 'Mauritanie', 'MU' => 'Maurice', 'MX' => 'Mexique',
            'FM' => 'Micronésie', 'MD' => 'Moldavie', 'MC' => 'Monaco', 'MN' => 'Mongolie',
            'ME' => 'Monténégro', 'MA' => 'Maroc', 'MZ' => 'Mozambique', 'MM' => 'Myanmar',
            'NA' => 'Namibie', 'NR' => 'Nauru', 'NP' => 'Népal', 'NL' => 'Pays-Bas',
            'NZ' => 'Nouvelle-Zélande', 'NI' => 'Nicaragua', 'NE' => 'Niger', 'NG' => 'Nigeria',
            'MK' => 'Macédoine du Nord', 'NO' => 'Norvège', 'OM' => 'Oman', 'PK' => 'Pakistan',
            'PW' => 'Palaos', 'PA' => 'Panama', 'PG' => 'Papouasie-Nouvelle-Guinée', 'PY' => 'Paraguay',
            'PE' => 'Pérou', 'PH' => 'Philippines', 'PL' => 'Pologne', 'PT' => 'Portugal',
            'QA' => 'Qatar', 'RO' => 'Roumanie', 'RU' => 'Russie', 'RW' => 'Rwanda',
            'KN' => 'Saint-Christophe-et-Niévès', 'LC' => 'Sainte-Lucie', 'VC' => 'Saint-Vincent-et-les-Grenadines',
            'WS' => 'Samoa', 'SM' => 'Saint-Marin', 'ST' => 'Sao Tomé-et-Principe', 'SA' => 'Arabie Saoudite',
            'SN' => 'Sénégal', 'RS' => 'Serbie', 'SC' => 'Seychelles', 'SL' => 'Sierra Leone',
            'SG' => 'Singapour', 'SK' => 'Slovaquie', 'SI' => 'Slovénie', 'SB' => 'Îles Salomon',
            'SO' => 'Somalie', 'ZA' => 'Afrique du Sud', 'SS' => 'Soudan du Sud', 'ES' => 'Espagne',
            'LK' => 'Sri Lanka', 'SD' => 'Soudan', 'SR' => 'Suriname', 'SE' => 'Suède',
            'CH' => 'Suisse', 'SY' => 'Syrie', 'TW' => 'Taïwan', 'TJ' => 'Tadjikistan',
            'TZ' => 'Tanzanie', 'TH' => 'Thaïlande', 'TL' => 'Timor-Leste', 'TG' => 'Togo',
            'TO' => 'Tonga', 'TT' => 'Trinité-et-Tobago', 'TN' => 'Tunisie', 'TR' => 'Turquie',
            'TM' => 'Turkménistan', 'TV' => 'Tuvalu', 'UG' => 'Ouganda', 'UA' => 'Ukraine',
            'AE' => 'Émirats arabes unis', 'GB' => 'Royaume-Uni', 'US' => 'États-Unis', 'UY' => 'Uruguay',
            'UZ' => 'Ouzbékistan', 'VU' => 'Vanuatu', 'VA' => 'Vatican', 'VE' => 'Venezuela',
            'VN' => 'Vietnam', 'YE' => 'Yémen', 'ZM' => 'Zambie', 'ZW' => 'Zimbabwe',
            
            // Full country names in English to French
            'Afghanistan' => 'Afghanistan', 'Albania' => 'Albanie', 'Algeria' => 'Algérie',
            'Andorra' => 'Andorre', 'Angola' => 'Angola', 'Antigua and Barbuda' => 'Antigua-et-Barbuda',
            'Argentina' => 'Argentine', 'Armenia' => 'Arménie', 'Australia' => 'Australie',
            'Austria' => 'Autriche', 'Azerbaijan' => 'Azerbaïdjan', 'Bahamas' => 'Bahamas',
            'Bahrain' => 'Bahreïn', 'Bangladesh' => 'Bangladesh', 'Barbados' => 'Barbade',
            'Belarus' => 'Biélorussie', 'Belgium' => 'Belgique', 'Belize' => 'Belize',
            'Benin' => 'Bénin', 'Bhutan' => 'Bhoutan', 'Bolivia' => 'Bolivie',
            'Bosnia and Herzegovina' => 'Bosnie-Herzégovine', 'Botswana' => 'Botswana', 'Brazil' => 'Brésil',
            'Brunei' => 'Brunei', 'Bulgaria' => 'Bulgarie', 'Burkina Faso' => 'Burkina Faso',
            'Burundi' => 'Burundi', 'Cabo Verde' => 'Cap-Vert', 'Cambodia' => 'Cambodge',
            'Cameroon' => 'Cameroun', 'Canada' => 'Canada', 'Central African Republic' => 'Centrafrique',
            'Chad' => 'Tchad', 'Chile' => 'Chili', 'China' => 'Chine', 'Colombia' => 'Colombie',
            'Comoros' => 'Comores', 'Congo' => 'Congo-Brazzaville', 'DR Congo' => 'Congo-Kinshasa',
            'Costa Rica' => 'Costa Rica', 'Cote d\'Ivoire' => 'Côte d\'Ivoire', 'Croatia' => 'Croatie',
            'Cuba' => 'Cuba', 'Cyprus' => 'Chypre', 'Czechia' => 'Tchéquie', 'Denmark' => 'Danemark',
            'Djibouti' => 'Djibouti', 'Dominica' => 'Dominique', 'Dominican Republic' => 'République dominicaine',
            'Ecuador' => 'Équateur', 'Egypt' => 'Égypte', 'El Salvador' => 'Salvador',
            'Equatorial Guinea' => 'Guinée équatoriale', 'Eritrea' => 'Érythrée', 'Estonia' => 'Estonie',
            'Eswatini' => 'Eswatini', 'Ethiopia' => 'Éthiopie', 'Fiji' => 'Fidji', 'Finland' => 'Finlande',
            'France' => 'France', 'Gabon' => 'Gabon', 'Gambia' => 'Gambie', 'Georgia' => 'Géorgie',
            'Germany' => 'Allemagne', 'Ghana' => 'Ghana', 'Greece' => 'Grèce', 'Grenada' => 'Grenade',
            'Guatemala' => 'Guatemala', 'Guinea' => 'Guinée', 'Guinea-Bissau' => 'Guinée-Bissau',
            'Guyana' => 'Guyana', 'Haiti' => 'Haïti', 'Honduras' => 'Honduras', 'Hungary' => 'Hongrie',
            'Iceland' => 'Islande', 'India' => 'Inde', 'Indonesia' => 'Indonésie', 'Iran' => 'Iran',
            'Iraq' => 'Irak', 'Ireland' => 'Irlande', 'Israel' => 'Israël', 'Italy' => 'Italie',
            'Jamaica' => 'Jamaïque', 'Japan' => 'Japon', 'Jordan' => 'Jordanie', 'Kazakhstan' => 'Kazakhstan',
            'Kenya' => 'Kenya', 'Kiribati' => 'Kiribati', 'North Korea' => 'Corée du Nord',
            'South Korea' => 'Corée du Sud', 'Kuwait' => 'Koweït', 'Kyrgyzstan' => 'Kirghizistan',
            'Laos' => 'Laos', 'Latvia' => 'Lettonie', 'Lebanon' => 'Liban', 'Lesotho' => 'Lesotho',
            'Liberia' => 'Libéria', 'Libya' => 'Libye', 'Liechtenstein' => 'Liechtenstein', 'Lithuania' => 'Lituanie',
            'Luxembourg' => 'Luxembourg', 'Madagascar' => 'Madagascar', 'Malawi' => 'Malawi',
            'Malaysia' => 'Malaisie', 'Maldives' => 'Maldives', 'Mali' => 'Mali', 'Malta' => 'Malte',
            'Marshall Islands' => 'Îles Marshall', 'Mauritania' => 'Mauritanie', 'Mauritius' => 'Maurice',
            'Mexico' => 'Mexique', 'Micronesia' => 'Micronésie', 'Moldova' => 'Moldavie', 'Monaco' => 'Monaco',
            'Mongolia' => 'Mongolie', 'Montenegro' => 'Monténégro', 'Morocco' => 'Maroc',
            'Mozambique' => 'Mozambique', 'Myanmar' => 'Myanmar', 'Namibia' => 'Namibie', 'Nauru' => 'Nauru',
            'Nepal' => 'Népal', 'Netherlands' => 'Pays-Bas', 'New Zealand' => 'Nouvelle-Zélande',
            'Nicaragua' => 'Nicaragua', 'Niger' => 'Niger', 'Nigeria' => 'Nigeria',
            'North Macedonia' => 'Macédoine du Nord', 'Norway' => 'Norvège', 'Oman' => 'Oman',
            'Pakistan' => 'Pakistan', 'Palau' => 'Palaos', 'Panama' => 'Panama',
            'Papua New Guinea' => 'Papouasie-Nouvelle-Guinée', 'Paraguay' => 'Paraguay', 'Peru' => 'Pérou',
            'Philippines' => 'Philippines', 'Poland' => 'Pologne', 'Portugal' => 'Portugal', 'Qatar' => 'Qatar',
            'Romania' => 'Roumanie', 'Russia' => 'Russie', 'Rwanda' => 'Rwanda',
            'Saint Kitts and Nevis' => 'Saint-Christophe-et-Niévès', 'Saint Lucia' => 'Sainte-Lucie',
            'Saint Vincent and the Grenadines' => 'Saint-Vincent-et-les-Grenadines', 'Samoa' => 'Samoa',
            'San Marino' => 'Saint-Marin', 'Sao Tome and Principe' => 'Sao Tomé-et-Principe',
            'Saudi Arabia' => 'Arabie Saoudite', 'Senegal' => 'Sénégal', 'Serbia' => 'Serbie',
            'Seychelles' => 'Seychelles', 'Sierra Leone' => 'Sierra Leone', 'Singapore' => 'Singapour',
            'Slovakia' => 'Slovaquie', 'Slovenia' => 'Slovénie', 'Solomon Islands' => 'Îles Salomon',
            'Somalia' => 'Somalie', 'South Africa' => 'Afrique du Sud', 'South Sudan' => 'Soudan du Sud',
            'Spain' => 'Espagne', 'Sri Lanka' => 'Sri Lanka', 'Sudan' => 'Soudan', 'Suriname' => 'Suriname',
            'Sweden' => 'Suède', 'Switzerland' => 'Suisse', 'Syria' => 'Syrie', 'Taiwan' => 'Taïwan',
            'Tajikistan' => 'Tadjikistan', 'Tanzania' => 'Tanzanie', 'Thailand' => 'Thaïlande',
            'Timor-Leste' => 'Timor-Leste', 'Togo' => 'Togo', 'Tonga' => 'Tonga',
            'Trinidad and Tobago' => 'Trinité-et-Tobago', 'Tunisia' => 'Tunisie', 'Turkey' => 'Turquie',
            'Turkmenistan' => 'Turkménistan', 'Tuvalu' => 'Tuvalu', 'Uganda' => 'Ouganda',
            'Ukraine' => 'Ukraine', 'United Arab Emirates' => 'Émirats arabes unis',
            'United Kingdom' => 'Royaume-Uni', 'United States' => 'États-Unis', 'Uruguay' => 'Uruguay',
            'Uzbekistan' => 'Ouzbékistan', 'Vanuatu' => 'Vanuatu', 'Vatican' => 'Vatican',
            'Venezuela' => 'Venezuela', 'Vietnam' => 'Vietnam', 'Yemen' => 'Yémen',
            'Zambia' => 'Zambie', 'Zimbabwe' => 'Zimbabwe'
        ];

        // Try exact match first (case sensitive)
        if (isset($countries[$countryInput])) {
            return $countries[$countryInput];
        }
        
        // Try uppercase (for country codes)
        if (isset($countries[strtoupper($countryInput)])) {
            return $countries[strtoupper($countryInput)];
        }
        
        // Try lowercase (for country names)
        if (isset($countries[strtolower($countryInput)])) {
            return $countries[strtolower($countryInput)];
        }
        
        // Try title case (for country names)
        if (isset($countries[ucwords(strtolower($countryInput))])) {
            return $countries[ucwords(strtolower($countryInput))];
        }
        
        // Return original if no translation found
        return $countryInput;
    }
}
