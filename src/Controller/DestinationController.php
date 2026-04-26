<?php

namespace App\Controller;

use App\Repository\DestinationRepository;
use App\Repository\FavoriteRepository;
use App\Repository\NewsletterRepository;
use App\Service\FlaskRecommendationService;
use App\Validation\LegacyValidator;
use App\View\PhpTemplateRenderer;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/destinations')]
final class DestinationController extends AbstractController
{
    public function __construct(
        private readonly DestinationRepository $destinationRepository,
        private readonly FavoriteRepository $favoriteRepository,
        private readonly NewsletterRepository $newsletterRepository,
        private readonly FlaskRecommendationService $flaskRecommendationService,
        private readonly PhpTemplateRenderer $renderer,
    ) {
    }

    #[Route('', name: 'app_destination_index', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        if ($request->isMethod('POST') && $request->request->has('newsletter_email')) {
            $email = trim((string) $request->request->get('newsletter_email', ''));

            if (!LegacyValidator::isValidEmail($email)) {
                $request->getSession()->getFlashBag()->add('newsletter_error', 'Veuillez saisir un email valide pour la newsletter.');
            } else {
                try {
                    $this->newsletterRepository->subscribe($email);
                    $request->getSession()->getFlashBag()->add('newsletter_success', 'Merci ! Vous etes abonne a la newsletter EasyTravel.');
                } catch (RuntimeException $exception) {
                    $request->getSession()->getFlashBag()->add('newsletter_error', $exception->getMessage());
                }
            }

            return $this->redirectToRoute('app_destination_index');
        }

        $databaseError = null;
        $destinations = [];
        $favoriteKeys = $this->favoriteKeysForRequest($request);

        try {
            $destinations = array_map(
                fn (array $destination): array => $this->buildDestinationCard($destination),
                $this->destinationRepository->findAll()
            );
            $destinations = $this->decorateFavoriteState($destinations, $favoriteKeys);
        } catch (RuntimeException $exception) {
            $databaseError = $exception->getMessage();
        }

        $flaskAvailable = $this->flaskRecommendationService->verifierAPI();
        $flaskContinents = $flaskAvailable ? $this->flaskRecommendationService->obtenirContinents() : [];
        $flaskInterests = $flaskAvailable ? $this->flaskRecommendationService->obtenirInterets() : [];
        $filterMeta = $this->buildFilterMeta($destinations, $flaskContinents, $flaskInterests);
        $initialFilters = $this->buildInitialFilters($request, $filterMeta);
        $destinationHeroVideo = $this->resolveDestinationHeroVideo((string) $request->query->get('hero_video', ''));

        return new Response($this->renderer->render('destination/index', [
            'title' => 'Destinations - EasyTravel',
            'databaseError' => $databaseError,
            'destinations' => $destinations,
            'statusMessage' => $this->statusMessage($request->query->get('status')),
            'errorMessage' => $this->consumeFlash($request, 'error'),
            'pageBodyClass' => 'destinations-page-body',
            'destinationFilterMeta' => $filterMeta,
            'destinationInitialFilters' => $initialFilters,
            'destinationHeroVideo' => $destinationHeroVideo,
            'destinationFlaskAvailable' => $flaskAvailable,
            'destinationRecommendationEndpoint' => '/destinations/recommendations',
            'destinationPackageDetailsEndpoint' => '/destinations/package-details',
            'destinationFavoriteEndpoint' => '/favorites/toggle',
            'destinationFavoriteKeys' => $favoriteKeys,
            'footerNewsletterAction' => '/destinations',
            'footerNewsletterStatusMessage' => $this->consumeFlash($request, 'newsletter_success'),
            'footerNewsletterErrorMessage' => $this->consumeFlash($request, 'newsletter_error'),
            'footerCtaHref' => '/contact',
            'footerCtaLabel' => 'Commencer mon voyage &#8594;',
            'footerContactEmail' => 'contact@easytravel.tn',
            'footerContactPhone' => '+216 71 123 456',
            'footerContactLocation' => 'Tunis, Monastir',
            'footerBrandText' => "Createur d'experiences de voyage uniques avec l'intelligence artificielle depuis 2024.",
        ]));
    }

    #[Route('/recommendations', name: 'app_destination_recommendations', methods: ['POST'])]
    public function recommendations(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            $payload = $request->request->all();
        }

        $filters = $this->normalizeRecommendationFilters($payload);
        $favoriteKeys = $this->favoriteKeysForRequest($request);
        $localCards = [];

        try {
            $localCards = array_map(
                fn (array $destination): array => $this->buildDestinationCard($destination),
                $this->destinationRepository->findAll()
            );
            $localCards = $this->decorateFavoriteState($localCards, $favoriteKeys);
        } catch (RuntimeException) {
            $localCards = [];
        }

        if (!$this->flaskRecommendationService->verifierAPI()) {
            return $this->json([
                'ok' => false,
                'source' => 'database',
                'message' => 'Flask est indisponible, affichage avec les destinations de la base.',
                'cards' => $this->filterCardsForRequest($localCards, $filters),
            ]);
        }

        $response = $this->flaskRecommendationService->obtenirRecommandations(
            $filters['budget_min'],
            $filters['budget_max'],
            $filters['date_debut'],
            $filters['date_fin'],
            $filters['type_voyage'],
            $filters['nb_adultes'],
            $filters['nb_enfants'],
            $filters['interets_api'],
            $filters['continents_api'],
        );

        $cards = $this->decorateFavoriteState($this->buildCardsFromFlaskResponse($response, $filters['interets']), $favoriteKeys);
        if ($cards === []) {
            $relaxedResponse = $this->flaskRecommendationService->obtenirRecommandations(
                $filters['budget_min'],
                $filters['budget_max'],
                $filters['date_debut'],
                $filters['date_fin'],
                $filters['type_voyage'],
                $filters['nb_adultes'],
                $filters['nb_enfants'],
                ['detente', 'culture'],
                $this->buildApiContinents(''),
            );
            $cards = $this->decorateFavoriteState($this->buildCardsFromFlaskResponse($relaxedResponse, $filters['interets']), $favoriteKeys);
        }

        if ($cards === []) {
            return $this->json([
                'ok' => false,
                'source' => 'database',
                'message' => 'Flask n a renvoye aucune recommandation, affichage avec les destinations de la base.',
                'cards' => $this->filterCardsForRequest($localCards, $filters),
            ]);
        }

        return $this->json([
            'ok' => true,
            'source' => 'flask',
            'message' => 'Recommandations IA recues depuis Flask.',
            'cards' => $cards,
        ]);
    }

    #[Route('/package-details', name: 'app_destination_package_details', methods: ['POST'])]
    public function packageDetails(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            $payload = $request->request->all();
        }

        $filters = $this->normalizeRecommendationFilters($payload);
        $destination = trim((string) ($payload['destination'] ?? ''));
        $continent = $this->normalizeContinentForApi((string) ($payload['continent'] ?? ''));
        $budget = max(0.0, (float) ($payload['budget'] ?? $payload['prix_total'] ?? $filters['budget_max']));
        $duree = max(1, (int) ($payload['duree'] ?? 7));

        if ($destination === '') {
            return $this->json([
                'ok' => false,
                'message' => 'Destination manquante pour charger les details IA.',
                'sections' => [],
            ], 400);
        }

        $package = $this->flaskRecommendationService->obtenirDetailsPackage(
            $destination,
            $continent,
            $budget,
            $budget,
            $duree,
            $filters['nb_adultes'],
            $filters['nb_enfants'],
            $filters['interets_api'],
        );

        if ($package === null) {
            return $this->json([
                'ok' => false,
                'message' => 'Details IA indisponibles pour ce pack. Les informations de base restent utilisables.',
                'sections' => [],
            ]);
        }

        return $this->json([
            'ok' => true,
            'message' => 'Details IA charges depuis Flask.',
            'sections' => $this->buildPackageSections($package, $budget, $duree),
        ]);
    }

    #[Route('/new', name: 'app_destination_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $payload = $this->extractPayload($request);
        $errors = [];
        $databaseError = null;

        if ($request->isMethod('POST')) {
            $errors = $this->validatePayload($payload);

            if ($errors === []) {
                try {
                    $this->destinationRepository->create($payload);

                    return $this->redirectToRoute('app_destination_index', ['status' => 'created']);
                } catch (RuntimeException $exception) {
                    $databaseError = $exception->getMessage();
                }
            }
        }

        return new Response($this->renderer->render('destination/form', [
            'title' => 'Nouvelle destination',
            'databaseError' => $databaseError,
            'errors' => $errors,
            'destination' => $payload,
            'formTitle' => 'Ajouter une destination',
            'submitLabel' => 'Enregistrer',
            'action' => $this->generateUrl('app_destination_new'),
        ]));
    }

    #[Route('/{id}/edit', name: 'app_destination_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, int $id): Response
    {
        $databaseError = null;

        try {
            $destination = $this->destinationRepository->find($id);
            if ($destination === null) {
                throw $this->createNotFoundException('Destination introuvable.');
            }
        } catch (RuntimeException $exception) {
            $destination = null;
            $databaseError = $exception->getMessage();
        }

        if ($destination === null && $databaseError !== null) {
            return new Response($this->renderer->render('destination/form', [
                'title' => 'Modifier une destination',
                'databaseError' => $databaseError,
                'errors' => [],
                'destination' => $this->extractPayload($request),
                'formTitle' => 'Modifier une destination',
                'submitLabel' => 'Mettre a jour',
                'action' => $this->generateUrl('app_destination_edit', ['id' => $id]),
            ]));
        }

        $payload = $request->isMethod('POST') ? $this->extractPayload($request) : $destination;
        $errors = [];

        if ($request->isMethod('POST')) {
            $errors = $this->validatePayload($payload);

            if ($errors === []) {
                try {
                    $this->destinationRepository->update($id, $payload);

                    return $this->redirectToRoute('app_destination_index', ['status' => 'updated']);
                } catch (RuntimeException $exception) {
                    $databaseError = $exception->getMessage();
                }
            }
        }

        return new Response($this->renderer->render('destination/form', [
            'title' => 'Modifier une destination',
            'databaseError' => $databaseError,
            'errors' => $errors,
            'destination' => $payload,
            'formTitle' => 'Modifier une destination',
            'submitLabel' => 'Mettre a jour',
            'action' => $this->generateUrl('app_destination_edit', ['id' => $id]),
        ]));
    }

    #[Route('/{id}/delete', name: 'app_destination_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(int $id): RedirectResponse
    {
        try {
            $this->destinationRepository->delete($id);

            return $this->redirectToRoute('app_destination_index', ['status' => 'deleted']);
        } catch (RuntimeException) {
            return $this->redirectToRoute('app_destination_index', ['status' => 'db-error']);
        }
    }

    private function extractPayload(Request $request): array
    {
        return [
            'nom' => trim((string) $request->request->get('nom', '')),
            'pays' => trim((string) $request->request->get('pays', '')),
            'continent' => trim((string) $request->request->get('continent', '')),
            'prix_base' => (float) str_replace(',', '.', (string) $request->request->get('prix_base', '0')),
            'description' => trim((string) $request->request->get('description', '')),
        ];
    }

    private function validatePayload(array $payload): array
    {
        $errors = [];

        if ($payload['nom'] === '') {
            $errors[] = 'Le nom est obligatoire.';
        }

        if ($payload['pays'] === '') {
            $errors[] = 'Le pays est obligatoire.';
        }

        if ($payload['continent'] === '') {
            $errors[] = 'Le continent est obligatoire.';
        }

        if ($payload['prix_base'] < 0) {
            $errors[] = 'Le prix de base ne peut pas etre negatif.';
        }

        return $errors;
    }

    private function statusMessage(?string $status): ?string
    {
        return match ($status) {
            'created' => 'Destination ajoutee avec succes.',
            'updated' => 'Destination mise a jour avec succes.',
            'deleted' => 'Destination supprimee avec succes.',
            'db-error' => 'Operation impossible: verifie la connexion MySQL.',
            default => null,
        };
    }

    private function consumeFlash(Request $request, string $type): ?string
    {
        $messages = $request->getSession()->getFlashBag()->get($type);

        return $messages !== [] ? implode(' ', $messages) : null;
    }

    private function normalizeRecommendationFilters(array $payload): array
    {
        $budgetMin = max(0.0, (float) ($payload['budget_min'] ?? 500));
        $budgetMax = max($budgetMin, (float) ($payload['budget_max'] ?? 5000));
        if ($budgetMin <= 0) {
            $budgetMin = 500.0;
        }
        if ($budgetMax <= 0 || $budgetMax < $budgetMin) {
            $budgetMax = max(5000.0, $budgetMin + 1000.0);
        }

        $rawDateDebut = trim((string) ($payload['date_debut'] ?? $payload['departure'] ?? ''));
        $rawDateFin = trim((string) ($payload['date_fin'] ?? $payload['return_date'] ?? ''));
        $hasExplicitDates = $rawDateDebut !== '' && $rawDateFin !== '';

        $dateDebut = $this->normalizeDateInput($rawDateDebut);
        if ($dateDebut === '') {
            $dateDebut = (new \DateTimeImmutable('+30 days'))->format('Y-m-d');
        }

        $dateFin = $this->normalizeDateInput($rawDateFin);
        if ($dateFin === '' || strtotime($dateFin) <= strtotime($dateDebut)) {
            $dateFin = (new \DateTimeImmutable($dateDebut))->modify('+7 days')->format('Y-m-d');
        }

        $interets = $this->parseStringList($payload['interets'] ?? $payload['interests'] ?? []);
        $continent = trim((string) ($payload['continent'] ?? ''));
        $rawTravelType = trim((string) ($payload['type_voyage'] ?? $payload['travel_type'] ?? 'famille'));
        $travelType = $this->normalizeTravelTypeForApi($rawTravelType);

        return [
            'search' => trim((string) ($payload['search'] ?? '')),
            'continent' => $continent,
            'budget_min' => $budgetMin,
            'budget_max' => $budgetMax,
            'date_debut' => $dateDebut,
            'date_fin' => $dateFin,
            'has_explicit_dates' => $hasExplicitDates,
            'type_voyage' => $travelType,
            'type_voyage_raw' => $rawTravelType,
            'nb_adultes' => max(1, (int) ($payload['nb_adultes'] ?? $payload['adults'] ?? 2)),
            'nb_enfants' => max(0, (int) ($payload['nb_enfants'] ?? $payload['children'] ?? 0)),
            'interets' => $interets,
            'interets_api' => $this->buildApiInterests($interets),
            'continents_api' => $this->buildApiContinents($continent),
        ];
    }

    private function normalizeDateInput(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return '';
        }

        return date('Y-m-d', $timestamp);
    }

    private function parseStringList(mixed $value): array
    {
        if (is_string($value)) {
            $value = preg_split('/[,|]/', $value) ?: [];
        }
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(
            fn (mixed $item): string => trim((string) $item),
            $value
        ))));
    }

    private function buildApiInterests(array $interests): array
    {
        if ($interests === []) {
            return ['detente', 'culture'];
        }

        $normalized = array_values(array_unique(array_filter(array_map(
            fn (string $interest): string => $this->normalizeInterestForApi($interest),
            $interests
        ))));

        return $normalized !== [] ? $normalized : ['detente', 'culture'];
    }

    private function buildApiContinents(string $selectedContinent): array
    {
        $selected = $this->normalizeContinentForApi($selectedContinent);
        if ($selected !== '') {
            return [$selected];
        }

        $continents = $this->flaskRecommendationService->obtenirContinents();
        $continents = array_values(array_filter(array_map(
            fn (string $continent): string => $this->normalizeContinentForApi($continent),
            $continents
        )));

        return $continents !== [] ? array_values(array_unique($continents)) : ['Asie', 'Europe', 'Afrique', 'Oceanie'];
    }

    private function normalizeTravelTypeForApi(string $value): string
    {
        $normalized = $this->normalizeValue($value);

        if (str_contains($normalized, 'couple')) {
            return 'couple';
        }
        if (str_contains($normalized, 'solo')) {
            return 'solo';
        }
        if (str_contains($normalized, 'business')) {
            return 'business';
        }

        return 'famille';
    }

    private function normalizeInterestForApi(string $interest): string
    {
        return match ($this->normalizeValue($interest)) {
            'plage' => 'plage',
            'aventure' => 'aventure',
            'city trip', 'city', 'city break', 'urbain' => 'city',
            'culture' => 'culture',
            'nature' => 'nature',
            'gastronomie' => 'gastronomie',
            'detente' => 'detente',
            'shopping' => 'shopping',
            'safari' => 'aventure',
            default => $this->normalizeValue($interest),
        };
    }

    private function normalizeContinentForApi(string $continent): string
    {
        return match ($this->normalizeValue($continent)) {
            '', 'tous', 'tous les continents' => '',
            'asie' => 'Asie',
            'europe' => 'Europe',
            'afrique' => 'Afrique',
            'oceanie' => 'Oceanie',
            'amerique' => 'Amerique',
            default => trim($continent),
        };
    }

    private function buildCardsFromFlaskResponse(?array $response, array $fallbackInterests): array
    {
        if ($response === null) {
            return [];
        }

        $recommendations = $response['recommendations'] ?? (array_is_list($response) ? $response : []);
        if (!is_array($recommendations)) {
            return [];
        }

        $cards = [];
        foreach ($recommendations as $index => $recommendation) {
            if (!is_array($recommendation)) {
                continue;
            }

            $cards[] = $this->buildDestinationCardFromRecommendation($recommendation, $index + 1, $fallbackInterests);
        }

        return $cards;
    }

    private function buildPackageSections(array $packageResponse, float $fallbackTotal, int $fallbackDuration): array
    {
        $package = is_array($packageResponse['package'] ?? null) ? $packageResponse['package'] : $packageResponse;
        $sections = [];

        $totaux = is_array($package['totaux'] ?? null) ? $package['totaux'] : [];
        $total = (float) ($totaux['cout_total'] ?? $package['prix_total'] ?? $fallbackTotal);
        $sections[] = [
            'icon' => '💰',
            'title' => 'Budget du pack',
            'copy' => trim(sprintf(
                "Total: %s TND\nPar jour: %s TND\nDuree: %d jours",
                number_format($total, 0, '.', ' '),
                number_format($fallbackDuration > 0 ? $total / $fallbackDuration : $total, 0, '.', ' '),
                $fallbackDuration
            )),
        ];

        $composants = is_array($package['composants'] ?? null) ? $package['composants'] : [];
        $transport = is_array($composants['transport'] ?? null) ? $composants['transport'] : [];
        if ($transport !== []) {
            $sections[] = [
                'icon' => '✈',
                'title' => 'Transport',
                'copy' => trim((string) ($transport['type'] ?? 'Transport inclus')."\n".($transport['cout'] ?? '' ? 'Cout: '.number_format((float) $transport['cout'], 0, '.', ' ').' TND' : '')),
            ];
        }

        $hebergement = is_array($composants['hebergement'] ?? null) ? $composants['hebergement'] : [];
        if ($hebergement !== []) {
            $sections[] = [
                'icon' => '🏨',
                'title' => 'Hebergement',
                'copy' => trim((string) ($hebergement['type'] ?? 'Hebergement inclus')."\n".($hebergement['nuits'] ?? '' ? $hebergement['nuits'].' nuits' : '')."\n".($hebergement['cout'] ?? '' ? 'Cout: '.number_format((float) $hebergement['cout'], 0, '.', ' ').' TND' : '')),
            ];
        }

        $activites = is_array($composants['activites'] ?? null) ? $composants['activites'] : [];
        $liste = is_array($activites['liste'] ?? null) ? $activites['liste'] : [];
        foreach (array_slice($liste, 0, 5) as $index => $activite) {
            if (!is_array($activite)) {
                continue;
            }

            $sections[] = [
                'icon' => '🎯',
                'title' => trim((string) ($activite['name'] ?? 'Activite '.($index + 1))),
                'copy' => trim((string) ($activite['description'] ?? $activite['category'] ?? 'Activite recommandee')."\n".($activite['duration_hours'] ?? '' ? 'Duree: '.$activite['duration_hours'].'h' : '')."\n".($activite['price'] ?? '' ? 'Prix: '.number_format((float) $activite['price'], 0, '.', ' ').' TND' : '')),
            ];
        }

        $verification = is_array($packageResponse['verification_budget'] ?? null) ? $packageResponse['verification_budget'] : [];
        if ($verification !== []) {
            $sections[] = [
                'icon' => '✓',
                'title' => 'Verification budget',
                'copy' => trim((string) ($verification['message'] ?? $verification['statut'] ?? 'Budget coherent avec votre demande.')),
            ];
        }

        return array_values(array_filter($sections, fn (array $section): bool => trim((string) ($section['copy'] ?? '')) !== ''));
    }

    private function buildDestinationCardFromRecommendation(array $recommendation, int $index, array $fallbackInterests): array
    {
        $name = $this->firstNonBlank($recommendation, ['destination', 'nom', 'name', 'destination_name'], 'Destination IA');
        $country = $this->firstNonBlank($recommendation, ['pays', 'country'], $name);
        $continent = $this->prettifyContinent($this->firstNonBlank($recommendation, ['continent'], 'Europe'));
        $description = $this->firstNonBlank($recommendation, ['description', 'resume', 'summary'], 'Recommandation IA generee par Flask pour votre profil de voyage.');
        $priceAmount = round((float) $this->firstNonBlank($recommendation, ['prix_total', 'budget_total', 'prix_base', 'price_total', 'price'], '1290'), 2);
        if ($priceAmount <= 0) {
            $priceAmount = 1290.0;
        }

        $durationDays = max(1, (int) $this->firstNonBlank($recommendation, ['duree', 'duration_days', 'days'], '7'));
        $interests = $this->extractRecommendationInterests($recommendation, $fallbackInterests);
        $travelMood = $this->prettifyTravelType($this->firstNonBlank($recommendation, ['type_voyage', 'travel_type', 'type'], $interests[0] ?? 'Voyage'));
        $maxTravelers = max(2, (int) $this->firstNonBlank($recommendation, ['max_travelers', 'capacite_max', 'capacity'], '6'));
        $imagePath = $this->resolveRecommendationImagePath($recommendation, $name, $continent);
        $bestPeriod = $this->resolveBestPeriod($continent);

        return [
            'id' => (int) ($recommendation['id'] ?? $index),
            'destination_name' => $name,
            'country' => $country,
            'continent' => $continent,
            'description' => $description,
            'image_path' => $imagePath,
            'travel_mood' => $travelMood,
            'duration_days' => $durationDays,
            'duration_label' => $durationDays.' jours',
            'price_amount' => $priceAmount,
            'price_label' => number_format($priceAmount, 0, '.', ' ').' TND',
            'original_price_amount' => round($priceAmount * 1.16, 2),
            'original_price_label' => number_format($priceAmount * 1.16, 0, '.', ' ').' TND',
            'audiences' => $this->resolveAudienceProfiles($this->normalizeValue($name.' '.$country.' '.$travelMood), $travelMood),
            'interests' => $interests,
            'max_travelers' => $maxTravelers,
            'best_period' => $bestPeriod,
            'highlights' => $this->extractRecommendationHighlights($recommendation, $name, $country, $travelMood, $interests),
            'subtitle' => trim($country !== '' ? $country.' - '.$continent : $continent),
            'payment_path' => '/paiement',
            'contact_path' => '/contact',
            'search_blob' => $this->normalizeValue($name.' '.$country.' '.$continent.' '.$description.' '.implode(' ', $interests)),
            'source' => 'flask',
        ];
    }

    private function favoriteKeysForRequest(Request $request): array
    {
        $user = $request->getSession()->get('auth_user');
        if (!is_array($user) || (int) ($user['id'] ?? 0) <= 0) {
            return [];
        }

        try {
            return $this->favoriteRepository->findKeysByUser((int) $user['id']);
        } catch (RuntimeException) {
            return [];
        }
    }

    private function decorateFavoriteState(array $cards, array $favoriteKeys): array
    {
        $favoriteKeys = array_flip($favoriteKeys);

        return array_map(function (array $card) use ($favoriteKeys): array {
            $favoriteKey = $this->favoriteRepository->buildFavoriteKey([
                'favorite_key' => $card['favorite_key'] ?? '',
                'source' => $card['source'] ?? 'database',
                'destination_id' => $card['id'] ?? 0,
                'destination_name' => $card['destination_name'] ?? '',
                'country' => $card['country'] ?? '',
            ]);

            return [
                ...$card,
                'favorite_key' => $favoriteKey,
                'is_favorite' => isset($favoriteKeys[$favoriteKey]),
            ];
        }, $cards);
    }

    private function firstNonBlank(array $payload, array $keys, string $fallback = ''): string
    {
        foreach ($keys as $key) {
            $value = trim((string) ($payload[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return $fallback;
    }

    private function extractRecommendationInterests(array $recommendation, array $fallbackInterests): array
    {
        $interests = $this->parseStringList($recommendation['interets'] ?? $recommendation['interests'] ?? []);
        if ($interests === []) {
            $interests = $fallbackInterests;
        }
        if ($interests === []) {
            $interests = ['Culture', 'Nature'];
        }

        return array_values(array_unique(array_map(fn (string $interest): string => $this->prettifyInterest($interest), $interests)));
    }

    private function extractRecommendationHighlights(array $recommendation, string $name, string $country, string $travelMood, array $interests): array
    {
        $highlights = $this->parseStringList($recommendation['highlights'] ?? $recommendation['points_forts'] ?? []);
        if ($highlights !== []) {
            return array_slice($highlights, 0, 4);
        }

        return $this->resolveHighlights($name, $country, $travelMood, $interests);
    }

    private function resolveRecommendationImagePath(array $recommendation, string $name, string $continent): string
    {
        $candidate = $this->firstNonBlank($recommendation, ['image_path', 'image', 'image_url', 'photo_url', 'cover_image']);
        if ($candidate !== '') {
            if (preg_match('/^https?:\/\//i', $candidate) === 1 || str_starts_with($candidate, '/assets/') || str_starts_with($candidate, '/uploads/')) {
                return $candidate;
            }

            return '/assets/java/'.ltrim($candidate, '/');
        }

        return $this->resolveDestinationImagePath($this->normalizeValue($name), $continent);
    }

    private function filterCardsForRequest(array $cards, array $filters): array
    {
        $requestedDays = max(1, (int) ceil((strtotime($filters['date_fin']) - strtotime($filters['date_debut'])) / 86400));
        $travelers = max(1, (int) $filters['nb_adultes'] + (int) $filters['nb_enfants']);
        $search = $this->normalizeValue((string) $filters['search']);
        $continent = $this->normalizeValue((string) $filters['continent']);
        $travelType = $this->normalizeValue((string) ($filters['type_voyage_raw'] ?? $filters['type_voyage']));
        $interestKeys = array_map(fn (string $interest): string => $this->normalizeValue($interest), $filters['interets']);

        return array_values(array_filter($cards, function (array $card) use ($filters, $requestedDays, $travelers, $search, $continent, $travelType, $interestKeys): bool {
            if ($search !== '' && !str_contains((string) ($card['search_blob'] ?? ''), $search)) {
                return false;
            }
            if ($continent !== '' && $this->normalizeValue((string) ($card['continent'] ?? '')) !== $continent) {
                return false;
            }
            $price = (float) ($card['price_amount'] ?? 0);
            if ($price < (float) $filters['budget_min'] || $price > (float) $filters['budget_max']) {
                return false;
            }
            if ($travelType !== '' && !in_array($travelType, ['tous les profils', 'tous'], true)) {
                $audiences = array_map(fn (string $value): string => $this->normalizeValue($value), $card['audiences'] ?? []);
                if (!in_array($travelType, $audiences, true)) {
                    return false;
                }
            }
            if ($travelers > (int) ($card['max_travelers'] ?? 2)) {
                return false;
            }
            if ($interestKeys !== []) {
                $cardInterests = array_map(fn (string $value): string => $this->normalizeValue($value), $card['interests'] ?? []);
                if (array_intersect($interestKeys, $cardInterests) === []) {
                    return false;
                }
            }

            return !$filters['has_explicit_dates'] || (int) ($card['duration_days'] ?? 1) <= $requestedDays;
        }));
    }

    private function prettifyTravelType(string $value): string
    {
        $normalized = $this->normalizeValue($value);

        if (str_contains($normalized, 'famille')) {
            return 'Famille';
        }
        if (str_contains($normalized, 'couple')) {
            return 'Couple';
        }
        if (str_contains($normalized, 'solo')) {
            return 'Solo';
        }
        if (str_contains($normalized, 'business')) {
            return 'Business';
        }
        if (str_contains($normalized, 'aventure')) {
            return 'Aventure';
        }
        if (str_contains($normalized, 'plage')) {
            return 'Plage';
        }

        return 'Voyage';
    }

    private function prettifyInterest(string $value): string
    {
        return match ($this->normalizeValue($value)) {
            'plage' => 'Plage',
            'aventure' => 'Aventure',
            'culture' => 'Culture',
            'nature' => 'Nature',
            'gastronomie' => 'Gastronomie',
            'shopping' => 'Shopping',
            'detente' => 'Detente',
            'city', 'city trip' => 'City Trip',
            'safari' => 'Safari',
            default => trim($value),
        };
    }

    private function prettifyContinent(string $value): string
    {
        return match ($this->normalizeValue($value)) {
            'asie' => 'Asie',
            'europe' => 'Europe',
            'afrique' => 'Afrique',
            'oceanie' => 'Oceanie',
            'amerique' => 'Amerique',
            default => trim($value),
        };
    }

    private function buildFilterMeta(array $destinations, array $flaskContinents = [], array $flaskInterests = []): array
    {
        $continents = [];
        $popular = [];
        $prices = [];

        foreach ($destinations as $destination) {
            $continent = trim((string) ($destination['continent'] ?? ''));
            if ($continent !== '' && !in_array($continent, $continents, true)) {
                $continents[] = $continent;
            }

            $displayName = trim((string) ($destination['destination_name'] ?? ''));
            if ($displayName !== '' && count($popular) < 4) {
                $popular[] = $displayName;
            }

            $prices[] = (float) ($destination['price_amount'] ?? 0.0);
        }

        sort($continents);
        $continents = array_values(array_unique(array_filter(
            [
                ...array_map(fn (string $value): string => $this->prettifyContinent($value), $flaskContinents),
                ...$continents,
            ],
            fn (string $value): bool => !in_array($this->normalizeValue($value), ['', 'tous', 'tous les continents'], true)
        )));
        sort($continents);

        $interests = array_values(array_unique(array_filter(array_map(
            fn (string $value): string => $this->prettifyInterest($value),
            $flaskInterests !== [] ? $flaskInterests : ['Plage', 'Aventure', 'Culture', 'Nature', 'Gastronomie', 'Detente', 'Shopping']
        ))));

        $minPrice = $prices !== [] ? floor(min($prices) / 100) * 100 : 0;
        $maxPrice = $prices !== [] ? ceil(max($prices) / 100) * 100 : 5000;
        if ($maxPrice <= $minPrice) {
            $maxPrice = $minPrice + 1000;
        }

        return [
            'continents' => $continents,
            'travel_types' => ['Tous les profils', 'Famille', 'Couple', 'Solo', 'Business'],
            'interests' => $interests,
            'popular' => $popular,
            'budget_min' => max(0, (int) $minPrice),
            'budget_max' => max(100, (int) $maxPrice),
        ];
    }

    private function buildInitialFilters(Request $request, array $filterMeta): array
    {
        $queryTravelers = max(0, (int) $request->query->get('travelers', 0));
        $defaultAdults = $queryTravelers > 0 ? $queryTravelers : 2;

        return [
            'search' => trim((string) $request->query->get('search', '')),
            'continent' => trim((string) $request->query->get('continent', '')),
            'budget_min' => max(0, (int) $request->query->get('budget_min', (int) ($filterMeta['budget_min'] ?? 0))),
            'budget_max' => max(0, (int) $request->query->get('budget_max', (int) ($filterMeta['budget_max'] ?? 5000))),
            'departure' => trim((string) $request->query->get('departure', (string) $request->query->get('date', ''))),
            'return' => trim((string) $request->query->get('return', '')),
            'travel_type' => trim((string) $request->query->get('travel_type', 'Tous les profils')),
            'adults' => max(1, (int) $request->query->get('adults', $defaultAdults)),
            'children' => max(0, (int) $request->query->get('children', 0)),
        ];
    }

    private function resolveDestinationHeroVideo(string $rawVideo): string
    {
        $video = trim(str_replace("\0", '', $rawVideo));
        if ($video === '') {
            return '/assets/java/Sky2Tours.mp4';
        }

        if (preg_match('/^https?:\/\//i', $video) === 1) {
            $path = (string) (parse_url($video, PHP_URL_PATH) ?? '');
            if (preg_match('/\.(mp4|webm|ogg)$/i', $path) === 1) {
                return $video;
            }

            return '/assets/java/Sky2Tours.mp4';
        }

        if (str_starts_with($video, '//') || str_contains($video, '..')) {
            return '/assets/java/Sky2Tours.mp4';
        }

        if (preg_match('/\.(mp4|webm|ogg)$/i', $video) !== 1) {
            return '/assets/java/Sky2Tours.mp4';
        }

        if (str_starts_with($video, '/')) {
            return $video;
        }

        return '/assets/java/'.$video;
    }

    private function buildDestinationCard(array $destination): array
    {
        $name = trim((string) ($destination['nom'] ?? 'Destination'));
        $country = trim((string) ($destination['pays'] ?? ''));
        $continent = trim((string) ($destination['continent'] ?? 'Europe'));
        $description = trim((string) ($destination['description'] ?? ''));
        if ($description === '') {
            $description = 'Une destination signature selectionnee pour vivre un voyage plus inspire, plus fluide et plus premium.';
        }

        $normalized = $this->normalizeValue($name.' '.$country.' '.$continent.' '.$description);
        $priceAmount = round((float) ($destination['prix_base'] ?? 0.0), 2);
        if ($priceAmount <= 0) {
            $priceAmount = 1290.0;
        }

        $travelMood = $this->resolveTravelMood($normalized, $continent);
        $durationDays = $this->resolveDurationDays($normalized, $continent, $priceAmount);
        $audiences = $this->resolveAudienceProfiles($normalized, $travelMood);
        $interests = $this->resolveInterests($normalized, $travelMood, $continent);
        $maxTravelers = $this->resolveMaxTravelers($audiences, $travelMood);
        $bestPeriod = $this->resolveBestPeriod($continent);
        $highlights = $this->resolveHighlights($name, $country, $travelMood, $interests);
        $imagePath = $this->resolveDestinationImagePath($normalized, $continent);

        return [
            ...$destination,
            'destination_name' => $name,
            'country' => $country,
            'continent' => $continent,
            'description' => $description,
            'image_path' => $imagePath,
            'travel_mood' => $travelMood,
            'duration_days' => $durationDays,
            'duration_label' => $durationDays.' jours',
            'price_amount' => $priceAmount,
            'price_label' => number_format($priceAmount, 0, '.', ' ').' TND',
            'original_price_amount' => round($priceAmount * 1.16, 2),
            'original_price_label' => number_format($priceAmount * 1.16, 0, '.', ' ').' TND',
            'audiences' => $audiences,
            'interests' => $interests,
            'max_travelers' => $maxTravelers,
            'best_period' => $bestPeriod,
            'highlights' => $highlights,
            'subtitle' => trim($country !== '' ? $country.' - '.$continent : $continent),
            'payment_path' => '/paiement',
            'contact_path' => '/contact',
            'search_blob' => $this->normalizeValue($name.' '.$country.' '.$continent.' '.$description.' '.implode(' ', $interests)),
        ];
    }

    private function resolveTravelMood(string $normalized, string $continent): string
    {
        if (
            str_contains($normalized, 'plage')
            || str_contains($normalized, 'mer')
            || str_contains($normalized, 'bali')
            || str_contains($normalized, 'maldives')
            || str_contains($normalized, 'santorini')
        ) {
            return 'Plage';
        }

        if (
            str_contains($normalized, 'safari')
            || str_contains($normalized, 'kenya')
            || str_contains($normalized, 'maroc')
            || str_contains($normalized, 'desert')
            || str_contains($normalized, 'aventure')
        ) {
            return 'Aventure';
        }

        if (
            str_contains($normalized, 'tokyo')
            || str_contains($normalized, 'paris')
            || str_contains($normalized, 'sydney')
            || str_contains($normalized, 'city')
            || str_contains($normalized, 'urbain')
        ) {
            return 'City Trip';
        }

        return match ($this->normalizeValue($continent)) {
            'asie' => 'Plage',
            'afrique' => 'Aventure',
            'oceanie' => 'City Trip',
            default => 'Culture',
        };
    }

    private function resolveDurationDays(string $normalized, string $continent, float $priceAmount): int
    {
        if (str_contains($normalized, 'sydney') || str_contains($normalized, 'australie')) {
            return 12;
        }
        if (str_contains($normalized, 'tokyo') || str_contains($normalized, 'japon')) {
            return 9;
        }
        if (str_contains($normalized, 'maldives')) {
            return 10;
        }
        if (str_contains($normalized, 'bali')) {
            return 8;
        }
        if (str_contains($normalized, 'santorini')) {
            return 7;
        }
        if (str_contains($normalized, 'maroc') || str_contains($normalized, 'kenya')) {
            return 6;
        }

        if ($priceAmount >= 2600) {
            return 10;
        }
        if ($priceAmount >= 1800) {
            return 8;
        }

        return $this->normalizeValue($continent) === 'europe' ? 5 : 7;
    }

    private function resolveAudienceProfiles(string $normalized, string $travelMood): array
    {
        if (str_contains($normalized, 'business') || $travelMood === 'City Trip') {
            return ['Business', 'Solo', 'Couple'];
        }
        if ($travelMood === 'Plage') {
            return ['Couple', 'Famille'];
        }
        if ($travelMood === 'Aventure') {
            return ['Famille', 'Solo', 'Couple'];
        }

        return ['Famille', 'Couple', 'Solo'];
    }

    private function resolveInterests(string $normalized, string $travelMood, string $continent): array
    {
        $interests = match ($travelMood) {
            'Plage' => ['Plage', 'Detente', 'Nature'],
            'Aventure' => ['Aventure', 'Nature', 'Culture'],
            'City Trip' => ['Shopping', 'Gastronomie', 'Culture'],
            default => ['Culture', 'Nature', 'Detente'],
        };

        if (str_contains($normalized, 'tokyo') || str_contains($normalized, 'paris')) {
            $interests[] = 'City Trip';
        }
        if ($this->normalizeValue($continent) === 'afrique') {
            $interests[] = 'Safari';
        }

        return array_values(array_unique($interests));
    }

    private function resolveMaxTravelers(array $audiences, string $travelMood): int
    {
        if (in_array('Famille', $audiences, true)) {
            return $travelMood === 'Plage' ? 5 : 6;
        }
        if (in_array('Business', $audiences, true)) {
            return 3;
        }

        return 2;
    }

    private function resolveBestPeriod(string $continent): string
    {
        return match ($this->normalizeValue($continent)) {
            'europe' => 'Avril a octobre',
            'asie' => 'Mars a mai',
            'afrique' => 'Juin a octobre',
            'oceanie' => 'Octobre a mars',
            default => 'Toute l annee',
        };
    }

    private function resolveHighlights(string $name, string $country, string $travelMood, array $interests): array
    {
        $destinationLabel = trim($name !== '' ? $name : $country);
        $interestLabel = $interests[0] ?? 'Voyage';

        return [
            'Experience signature a '.$destinationLabel,
            'Ambiance '.$travelMood.' avec tempo '.$interestLabel,
            'Itineraire pense pour un depart simple et premium',
        ];
    }

    private function resolveDestinationImagePath(string $normalized, string $continent): string
    {
        if (str_contains($normalized, 'bali')) {
            return '/assets/java/bali-1-1679062958.profileImage.2x-1536x884.webp';
        }
        if (str_contains($normalized, 'santorini')) {
            return '/assets/java/GettyImages-158525984-5b6df57dc9e77c005086b0ca.jpg';
        }
        if (str_contains($normalized, 'maldives')) {
            return '/assets/java/aede2fa75f528a9251e4809645f62f7a.jpg';
        }
        if (str_contains($normalized, 'tokyo') || str_contains($normalized, 'japon')) {
            return '/assets/java/bac4bce325c9a10f6fb77f30682cc7fa.jpg';
        }
        if (str_contains($normalized, 'maroc')) {
            return '/assets/java/da89f34fb5595d60358fcefe64fc6659.jpg';
        }
        if (str_contains($normalized, 'paris') || str_contains($normalized, 'france')) {
            return '/assets/java/paris.jpg';
        }
        if (str_contains($normalized, 'kenya') || str_contains($normalized, 'tanzanie') || str_contains($normalized, 'safari')) {
            return '/assets/java/safari.jpg';
        }
        if (str_contains($normalized, 'sydney') || str_contains($normalized, 'australie')) {
            return '/assets/java/80281906250b49a80467292e998492eb.jpg';
        }

        return match ($this->normalizeValue($continent)) {
            'asie' => '/assets/java/asia.jpg',
            'afrique' => '/assets/java/b98f59bef70929b9642bc88dd2a56f11.jpg',
            'oceanie' => '/assets/java/80281906250b49a80467292e998492eb.jpg',
            default => '/assets/java/9b4f03d821c26c149892eb9b646573bc.jpg',
        };
    }

    private function normalizeValue(string $value): string
    {
        $value = trim($value);
        $transliterated = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if (is_string($transliterated) && $transliterated !== '') {
            $value = $transliterated;
        }

        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/', ' ', $value) ?? $value;

        return trim($value);
    }
}
