<?php

namespace App\Repository;

use App\Database\PdoConnectionFactory;
use DateTimeImmutable;
use Throwable;

final class AdminDashboardRepository
{
    private const FEATURED_SELECTION_LIMIT = 6;
    private const TRAVEL_PACKAGE_AI_LIMIT = 3;
    private const MAP_SELECTION_LIMIT = 5;
    private const ATMOSPHERE_AI_LIMIT = 10;
    private bool $packagesAdminSchemaEnsured = false;
    private bool $featuredDestinationSchemaEnsured = false;
    private bool $atmosphereSchemaEnsured = false;
    private bool $mapSchemaEnsured = false;
    private bool $sponsorSchemaEnsured = false;

    public function __construct(
        private readonly PdoConnectionFactory $connectionFactory,
        private readonly UserRepository $userRepository,
    ) {
    }

    public function getOverviewSnapshot(): array
    {
        $this->ensurePackagesAdminSchema();
        $this->ensureFeaturedDestinationSchema();
        $this->ensureAtmosphereSchema();
        $this->ensureMapDestinationSchema();
        $users = $this->getUserDirectory();
        $customerUsers = array_values(array_filter(
            $this->userRepository->getAllUsers(),
            static fn (array $user): bool => strtoupper((string) ($user['role'] ?? 'USER')) === 'USER'
        ));
        $allPackages = $this->fetchAllOrEmpty('SELECT * FROM packages ORDER BY date_reservation DESC');
        $allPayments = $this->fetchAllOrEmpty('SELECT * FROM paiements ORDER BY date_paiement DESC');
        $destinationsById = $this->loadDestinationsById();
        $featuredDestinations = $this->loadFeaturedDestinations();
        $travelPackages = $this->loadTravelPackagesForAdmin();
        $mapEntries = $this->loadMapDestinationsForAdmin();
        $atmosphereEntries = $this->loadAtmospheresForAdmin();
        $sponsors = $this->loadSponsorsForAdmin();
        $destinationsAdmin = $this->buildDestinationsAdminData($allPackages, $featuredDestinations, $destinationsById);
        $packagesAdmin = $this->buildPackagesAdminData($travelPackages, $allPackages, $destinationsById);
        $mapAdmin = $this->buildMapAdminData($mapEntries);
        $atmospheresAdmin = $this->buildAtmospheresAdminData($atmosphereEntries);
        $reservationsAdmin = $this->buildReservationsAdminData($allPackages, $allPayments, $destinationsById);
        $revenueEvents = $this->buildDashboardRevenueEvents($allPackages, $allPayments, $destinationsById);
        $dashboardOverview = $this->buildDashboardOverviewData($users, $customerUsers, $allPackages, $allPayments, $destinationsById);
        $recentDays = $this->buildRecentDays(6);
        $dailyRevenueSeries = array_map(
            fn (string $dayKey): float => $this->sumRevenueForDay($revenueEvents, $dayKey),
            $recentDays
        );
        $totalRevenue = array_reduce(
            $revenueEvents,
            static fn (float $total, array $event): float => $total + max(0.0, (float) ($event['amount'] ?? 0.0)),
            0.0
        );
        $todayRevenue = $dailyRevenueSeries !== []
            ? (float) ($dailyRevenueSeries[count($dailyRevenueSeries) - 1] ?? 0.0)
            : 0.0;
        $yesterdayRevenue = count($dailyRevenueSeries) >= 2
            ? (float) ($dailyRevenueSeries[count($dailyRevenueSeries) - 2] ?? 0.0)
            : 0.0;

        $totalPackages = (int) $this->fetchScalar('SELECT COUNT(*) FROM packages', 0);
        $pendingPackages = (int) $this->fetchScalar(
            "SELECT COUNT(*) FROM packages WHERE UPPER(statut) LIKE '%ATTENTE%'",
            0
        );
        $confirmedPackages = (int) $this->fetchScalar(
            "SELECT COUNT(*) FROM packages WHERE UPPER(statut) LIKE '%CONFIRM%'",
            0
        );
        $cancelledPackages = (int) $this->fetchScalar(
            "SELECT COUNT(*) FROM packages WHERE UPPER(statut) REGEXP 'ANNUL|REFUS|REMBOURS'",
            0
        );
        $totalPayments = (int) $this->fetchScalar('SELECT COUNT(*) FROM paiements', 0);
        $paidPayments = (int) $this->fetchScalar(
            "SELECT COUNT(*) FROM paiements WHERE UPPER(statut) = 'PAYE'",
            0
        );
        $totalTravelPackages = (int) $this->fetchScalar('SELECT COUNT(*) FROM travel_packages', 0);
        $activeTravelPackages = (int) $this->fetchScalar(
            'SELECT COUNT(*) FROM travel_packages WHERE COALESCE(is_active, 1) = 1',
            0
        );
        $totalMapDestinations = (int) $this->fetchScalar('SELECT COUNT(*) FROM map_destinations', 0);
        $activeMapDestinations = (int) $this->fetchScalar(
            'SELECT COUNT(*) FROM map_destinations WHERE COALESCE(is_active, 1) = 1',
            0
        );
        $totalAtmospheres = (int) $this->fetchScalar('SELECT COUNT(*) FROM atmosphere_destinations', 0);
        $activeAtmospheres = (int) $this->fetchScalar(
            'SELECT COUNT(*) FROM atmosphere_destinations WHERE COALESCE(is_active, 1) = 1',
            0
        );
        $totalFeaturedDestinations = (int) $this->fetchScalar('SELECT COUNT(*) FROM featured_destinations', 0);
        $visibleFeaturedDestinations = (int) $this->fetchScalar(
            'SELECT COUNT(*) FROM featured_destinations WHERE COALESCE(is_featured, 1) = 1',
            0
        );
        $totalSponsors = (int) $this->fetchScalar('SELECT COUNT(*) FROM sponsor', 0);
        $activeSponsors = (int) $this->fetchScalar(
            'SELECT COUNT(*) FROM sponsor WHERE COALESCE(est_actif, 1) = 1',
            0
        );
        $destinationCountries = (int) $this->fetchScalar('SELECT COUNT(DISTINCT pays) FROM destinations', 0);

        $reservationFlowPercent = $totalPackages === 0
            ? 100.0
            : (($totalPackages - $pendingPackages) * 100.0) / $totalPackages;
        $paymentCollectionPercent = $totalPayments === 0
            ? 100.0
            : ($paidPayments * 100.0) / $totalPayments;
        $performancePercent = $this->clamp(
            ($reservationFlowPercent * 0.55) + ($paymentCollectionPercent * 0.45),
            0.0,
            100.0
        );

        return [
            'counts' => [
                'users' => count($users),
                'active_users' => count(array_filter(
                    $users,
                    static fn (array $user): bool => in_array(($user['status_key'] ?? ''), ['active', 'agent'], true)
                )),
                'pending_users' => count(array_filter($users, static fn (array $user): bool => ($user['status_key'] ?? '') === 'pending')),
                'destinations' => (int) $this->fetchScalar('SELECT COUNT(*) FROM destinations', 0),
                'destination_countries' => $destinationCountries,
                'activites' => (int) $this->fetchScalar('SELECT COUNT(*) FROM activites', 0),
                'packages' => $totalPackages,
                'pending_packages' => $pendingPackages,
                'confirmed_packages' => $confirmedPackages,
                'cancelled_packages' => $cancelledPackages,
                'travel_packages' => $totalTravelPackages,
                'active_travel_packages' => $activeTravelPackages,
                'map_destinations' => $totalMapDestinations,
                'active_map_destinations' => $activeMapDestinations,
                'atmospheres' => $totalAtmospheres,
                'active_atmospheres' => $activeAtmospheres,
                'featured_destinations' => $totalFeaturedDestinations,
                'visible_featured_destinations' => $visibleFeaturedDestinations,
                'sponsors' => $totalSponsors,
                'active_sponsors' => $activeSponsors,
                'paiements' => $totalPayments,
                'reclamations' => (int) $this->fetchScalar('SELECT COUNT(*) FROM reclamation', 0),
                'reponses' => (int) $this->fetchScalar('SELECT COUNT(*) FROM reponse', 0),
            ],
            'revenue' => [
                'total' => $totalRevenue,
                'today' => $todayRevenue,
                'today_change_percent' => $this->computePercentageChange((float) $todayRevenue, (float) $yesterdayRevenue),
                'daily_series' => $dailyRevenueSeries,
            ],
            'performance' => [
                'percent' => $performancePercent,
                'copy' => $this->buildPerformanceCopy($performancePercent, $pendingPackages, $paidPayments, $totalPayments),
            ],
            'pending_validations' => array_slice(
                array_values(array_filter($users, static fn (array $user): bool => ($user['status_key'] ?? '') === 'pending')),
                0,
                4
            ),
            'latest_reservations' => $this->fetchAllOrEmpty(
                'SELECT p.*, d.nom AS destination_nom, d.pays AS destination_pays
                 FROM packages p
                 LEFT JOIN destinations d ON d.id = p.destination_id
                 ORDER BY p.date_reservation DESC
                 LIMIT 4'
            ),
            'latest_payments' => $this->fetchAllOrEmpty(
                'SELECT *
                 FROM paiements
                 ORDER BY date_paiement DESC
                 LIMIT 4'
            ),
            'latest_destinations' => $this->fetchAllOrEmpty(
                'SELECT *
                 FROM destinations
                 ORDER BY id DESC
                 LIMIT 4'
            ),
            'latest_activites' => $this->fetchAllOrEmpty(
                'SELECT a.*, d.nom AS destination_nom
                 FROM activites a
                 LEFT JOIN destinations d ON d.id = a.destination_id
                 ORDER BY a.id DESC
                 LIMIT 4'
            ),
            'travel_packages' => $travelPackages,
            'map_destinations' => array_slice($mapEntries, 0, 4),
            'atmospheres' => array_slice($atmosphereEntries, 0, 4),
            'featured_destinations' => $featuredDestinations,
            'sponsors' => $sponsors,
            'latest_reclamations' => $this->fetchAllOrEmpty(
                'SELECT r.*, u.prenom, u.nom, u.email
                 FROM reclamation r
                 LEFT JOIN `user` u ON u.id = r.user_id
                 ORDER BY r.created_at DESC
                 LIMIT 4'
            ),
            'dashboard_overview' => $dashboardOverview,
            'destinations_admin' => $destinationsAdmin,
            'packages_admin' => $packagesAdmin,
            'map_admin' => $mapAdmin,
            'atmospheres_admin' => $atmospheresAdmin,
            'reservations_admin' => $reservationsAdmin,
        ];
    }

    public function getUserDirectory(string $search = '', string $filter = 'all'): array
    {
        $packageStats = $this->loadPackageStatsByEmail();
        $users = [];

        foreach ($this->userRepository->getAllUsers() as $user) {
            if (strtoupper((string) ($user['role'] ?? 'USER')) === 'ADMIN') {
                continue;
            }

            $email = strtolower(trim((string) ($user['email'] ?? '')));
            $stats = $packageStats[$email] ?? [
                'reservation_count' => 0,
                'total_reserved' => 0.0,
                'total_paid' => 0.0,
                'last_reservation_at' => null,
            ];

            $reservationCount = (int) ($stats['reservation_count'] ?? 0);
            $totalReserved = (float) ($stats['total_reserved'] ?? 0);
            $totalPaid = (float) ($stats['total_paid'] ?? 0);
            $segment = $this->resolveSegment($reservationCount, $totalPaid, $user);
            $status = $this->resolveStatus($user);

            $users[] = [
                ...$user,
                'reservation_count' => $reservationCount,
                'total_reserved' => $totalReserved,
                'total_paid' => $totalPaid,
                'last_reservation_at' => $stats['last_reservation_at'] ?? null,
                'country' => $this->extractCountry((string) ($user['adresse'] ?? '')),
                'segment' => $segment,
                'status_label' => $status['label'],
                'status_key' => $status['key'],
                'status_tone' => $status['tone'],
            ];
        }

        $search = strtolower(trim($search));
        $filter = strtolower(trim($filter));

        return array_values(array_filter($users, function (array $user) use ($search, $filter): bool {
            $name = strtolower(trim((string) ($user['display_name'] ?? '')));
            $email = strtolower(trim((string) ($user['email'] ?? '')));
            $segment = strtolower(trim((string) ($user['segment'] ?? '')));
            $statusKey = strtolower(trim((string) ($user['status_key'] ?? '')));

            $matchesSearch = $search === ''
                || str_contains($name, $search)
                || str_contains($email, $search);

            $matchesFilter = match ($filter) {
                'active' => $statusKey === 'active',
                'pending' => $statusKey === 'pending',
                'premium' => $segment === 'premium',
                default => true,
            };

            return $matchesSearch && $matchesFilter;
        }));
    }

    public function refreshFeaturedDestinationsWithLocalSuggestions(int $adminId): bool
    {
        $this->ensureFeaturedDestinationSchema();
        $connection = $this->connectionFactory->getConnection();

        try {
            $packages = $this->fetchAllOrEmpty('SELECT * FROM packages ORDER BY date_reservation DESC');
            $destinationsById = $this->loadDestinationsById();
            $suggestions = $this->buildFeaturedSuggestionsFromReservations($packages, $destinationsById, self::FEATURED_SELECTION_LIMIT);
            if ($suggestions === []) {
                return false;
            }

            $connection->beginTransaction();
            $connection->exec('DELETE FROM featured_destinations');

            $summaryNote = count($suggestions).' suggestion(s) preparee(s) pour la vitrine Home.';
            foreach ($suggestions as $index => $entry) {
                $entryId = $this->insertFeaturedDestinationEntry($connection, $entry);
                $action = $index === 0 ? 'AI_REFRESH' : 'AI_SELECTED';
                $note = $index === 0
                    ? $summaryNote
                    : 'Selection IA appliquee au slot #'.($index + 1).'.';
                $this->logFeaturedHistory(
                    $connection,
                    $entryId,
                    $action,
                    (string) ($entry['destination_name'] ?? ''),
                    (float) ($entry['ai_score'] ?? 0.0),
                    $note,
                    $adminId
                );
            }

            $connection->commit();

            return true;
        } catch (Throwable) {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }

            return false;
        }
    }

    public function updateFeaturedDestination(int $id, array $payload, int $adminId): bool
    {
        $this->ensureFeaturedDestinationSchema();
        $connection = $this->connectionFactory->getConnection();
        $currentEntry = $this->loadFeaturedDestinationById($id);
        if ($currentEntry === null) {
            return false;
        }

        $updatedEntry = [
            ...$currentEntry,
            'destination_name' => trim((string) ($payload['destination_name'] ?? $currentEntry['destination_name'] ?? '')),
            'country' => trim((string) ($payload['country'] ?? $currentEntry['country'] ?? '')),
            'continent' => trim((string) ($payload['continent'] ?? $currentEntry['continent'] ?? '')),
            'description' => trim((string) ($payload['description'] ?? $currentEntry['description'] ?? '')),
            'video_path' => trim((string) ($payload['video_path'] ?? $currentEntry['video_path'] ?? '')),
            'avg_price' => $this->parseNumericValue($payload['avg_price'] ?? $currentEntry['avg_price'] ?? 0.0),
            'best_season' => trim((string) ($payload['best_season'] ?? $currentEntry['best_season'] ?? '')),
            'travel_types' => trim((string) ($payload['travel_types'] ?? $currentEntry['travel_types'] ?? '')),
            'interests' => trim((string) ($payload['interests'] ?? $currentEntry['interests'] ?? '')),
            'is_featured' => !empty($payload['is_featured']),
        ];

        try {
            $connection->beginTransaction();
            if (!$this->updateFeaturedDestinationEntry($connection, $id, $updatedEntry)) {
                $connection->rollBack();

                return false;
            }

            $this->logFeaturedHistory(
                $connection,
                $id,
                'MANUAL_UPDATE',
                (string) ($updatedEntry['destination_name'] ?? ''),
                (float) ($updatedEntry['ai_score'] ?? 0.0),
                'Destination vedette modifiee manuellement.',
                $adminId
            );
            $connection->commit();

            return true;
        } catch (Throwable) {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }

            return false;
        }
    }

    public function replaceFeaturedDestinationWithDestination(int $featuredId, int $destinationId, int $adminId): bool
    {
        $this->ensureFeaturedDestinationSchema();
        $connection = $this->connectionFactory->getConnection();
        $currentEntry = $this->loadFeaturedDestinationById($featuredId);
        $destinationsById = $this->loadDestinationsById();
        $destination = $destinationsById[$destinationId] ?? null;
        if ($currentEntry === null || $destination === null) {
            return false;
        }

        $reservationStats = $this->buildFeaturedReservationStats(
            $this->fetchAllOrEmpty('SELECT * FROM packages ORDER BY date_reservation DESC'),
            $destinationsById
        );
        $replacementEntry = $this->createFeaturedEntryFromDestination(
            $destination,
            $reservationStats,
            (int) ($currentEntry['display_order'] ?? 1),
            !empty($currentEntry['is_featured'])
        );
        $replacementEntry['id'] = $featuredId;

        try {
            $connection->beginTransaction();
            if (!$this->updateFeaturedDestinationEntry($connection, $featuredId, $replacementEntry)) {
                $connection->rollBack();

                return false;
            }

            $this->logFeaturedHistory(
                $connection,
                $featuredId,
                'REPLACED',
                (string) ($replacementEntry['destination_name'] ?? ''),
                (float) ($replacementEntry['ai_score'] ?? 0.0),
                'Destination remplacee manuellement depuis la liste des destinations.',
                $adminId
            );
            $connection->commit();

            return true;
        } catch (Throwable) {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }

            return false;
        }
    }

    public function moveFeaturedDestination(int $featuredId, int $direction, int $adminId): bool
    {
        $this->ensureFeaturedDestinationSchema();
        $entries = $this->loadFeaturedDestinations();
        $currentIndex = null;
        foreach ($entries as $index => $entry) {
            if ((int) ($entry['id'] ?? 0) === $featuredId) {
                $currentIndex = $index;
                break;
            }
        }

        if ($currentIndex === null) {
            return false;
        }

        $targetIndex = $currentIndex + ($direction < 0 ? -1 : 1);
        if (!isset($entries[$targetIndex])) {
            return false;
        }

        $currentEntry = $entries[$currentIndex];
        $targetEntry = $entries[$targetIndex];
        $connection = $this->connectionFactory->getConnection();

        try {
            $connection->beginTransaction();
            $currentOrder = (int) ($currentEntry['display_order'] ?? ($currentIndex + 1));
            $targetOrder = (int) ($targetEntry['display_order'] ?? ($targetIndex + 1));
            $this->updateFeaturedDisplayOrder($connection, (int) ($currentEntry['id'] ?? 0), $targetOrder);
            $this->updateFeaturedDisplayOrder($connection, (int) ($targetEntry['id'] ?? 0), $currentOrder);
            $this->logFeaturedHistory(
                $connection,
                (int) ($currentEntry['id'] ?? 0),
                'MANUAL_UPDATE',
                (string) ($currentEntry['destination_name'] ?? ''),
                (float) ($currentEntry['ai_score'] ?? 0.0),
                $direction < 0 ? 'Destination remontee dans la liste Home.' : 'Destination descendue dans la liste Home.',
                $adminId
            );
            $connection->commit();

            return true;
        } catch (Throwable) {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }

            return false;
        }
    }

    public function generateTravelPackagesWithLocalSuggestions(array $criteria): int
    {
        $connection = $this->connectionFactory->getConnection();
        $packages = $this->fetchAllOrEmpty('SELECT * FROM packages ORDER BY date_reservation DESC');
        $destinationsById = $this->loadDestinationsById();
        $suggestions = $this->buildTravelPackageSuggestions($criteria, $packages, $destinationsById);
        if ($suggestions === []) {
            return 0;
        }

        $nextDisplayOrder = $this->findNextTravelPackageDisplayOrder($connection);
        $created = 0;

        foreach ($suggestions as $index => $suggestion) {
            $suggestion['display_order'] = $nextDisplayOrder + $index;

            try {
                $created += $this->insertTravelPackageEntry($connection, $suggestion) > 0 ? 1 : 0;
            } catch (Throwable) {
            }
        }

        return $created;
    }

    public function updateTravelPackage(int $id, array $payload): bool
    {
        $connection = $this->connectionFactory->getConnection();
        $currentEntry = $this->loadTravelPackageById($id);
        if ($currentEntry === null) {
            return false;
        }

        $updatedEntry = [
            ...$currentEntry,
            'package_name' => trim((string) ($payload['package_name'] ?? $currentEntry['package_name'] ?? '')),
            'destinations' => trim((string) ($payload['destinations'] ?? $currentEntry['destinations'] ?? '')),
            'continent' => trim((string) ($payload['continent'] ?? $currentEntry['continent'] ?? '')),
            'duration_days' => max(1, (int) round($this->parseNumericValue($payload['duration_days'] ?? $currentEntry['duration_days'] ?? 1, 1.0))),
            'price_from' => max(0.0, $this->parseNumericValue($payload['price_from'] ?? $currentEntry['price_from'] ?? 0.0)),
            'price_to' => 0.0,
            'badge' => trim((string) ($payload['badge'] ?? $currentEntry['badge'] ?? 'Nouveau')),
            'description' => trim((string) ($payload['description'] ?? $currentEntry['description'] ?? '')),
            'travel_type' => trim((string) ($payload['travel_type'] ?? $currentEntry['travel_type'] ?? 'couple')),
            'interests' => trim((string) ($payload['interests'] ?? $currentEntry['interests'] ?? '')),
            'includes' => trim((string) ($payload['includes'] ?? $currentEntry['includes'] ?? '')),
            'best_period' => trim((string) ($payload['best_period'] ?? $currentEntry['best_period'] ?? '')),
            'is_active' => !empty($payload['is_active']),
        ];
        $updatedEntry['price_to'] = max(
            $updatedEntry['price_from'],
            $this->parseNumericValue($payload['price_to'] ?? $currentEntry['price_to'] ?? $updatedEntry['price_from'], $updatedEntry['price_from'])
        );

        try {
            return $this->updateTravelPackageEntry($connection, $id, $updatedEntry);
        } catch (Throwable) {
            return false;
        }
    }

    public function createMapDestination(array $payload): bool
    {
        $this->ensureMapDestinationSchema();
        $connection = $this->connectionFactory->getConnection();
        $entry = $this->sanitizeMapDestinationEntry($payload);

        try {
            if ((int) ($entry['display_order'] ?? 0) <= 0) {
                $entry['display_order'] = $this->findNextMapDestinationDisplayOrder($connection);
            }

            return $this->insertMapDestinationEntry($connection, $entry) > 0;
        } catch (Throwable) {
            return false;
        }
    }

    public function updateMapDestination(int $id, array $payload): bool
    {
        $this->ensureMapDestinationSchema();
        $connection = $this->connectionFactory->getConnection();
        $currentEntry = $this->loadMapDestinationById($id);
        if ($currentEntry === null) {
            return false;
        }

        $updatedEntry = [
            ...$currentEntry,
            'city' => trim((string) ($payload['city'] ?? $currentEntry['city'] ?? '')),
            'country' => trim((string) ($payload['country'] ?? $currentEntry['country'] ?? '')),
            'continent' => trim((string) ($payload['continent'] ?? $currentEntry['continent'] ?? '')),
            'package_name' => trim((string) ($payload['package_name'] ?? $currentEntry['package_name'] ?? '')),
            'duration' => trim((string) ($payload['duration'] ?? $currentEntry['duration'] ?? '')),
            'price' => trim((string) ($payload['price'] ?? $currentEntry['price'] ?? '')),
            'original_price' => trim((string) ($payload['original_price'] ?? $currentEntry['original_price'] ?? '')),
            'image_path' => trim((string) ($payload['image_path'] ?? $currentEntry['image_path'] ?? '')),
            'description' => trim((string) ($payload['description'] ?? $currentEntry['description'] ?? '')),
            'best_period' => trim((string) ($payload['best_period'] ?? $currentEntry['best_period'] ?? '')),
            'includes' => trim((string) ($payload['includes'] ?? $currentEntry['includes'] ?? '')),
            'highlight_1' => trim((string) ($payload['highlight_1'] ?? $currentEntry['highlight_1'] ?? '')),
            'highlight_2' => trim((string) ($payload['highlight_2'] ?? $currentEntry['highlight_2'] ?? '')),
            'highlight_3' => trim((string) ($payload['highlight_3'] ?? $currentEntry['highlight_3'] ?? '')),
            'x_percent' => $this->parseNumericValue($payload['x_percent'] ?? $currentEntry['x_percent'] ?? 0.0, (float) ($currentEntry['x_percent'] ?? 0.0)),
            'y_percent' => $this->parseNumericValue($payload['y_percent'] ?? $currentEntry['y_percent'] ?? 0.0, (float) ($currentEntry['y_percent'] ?? 0.0)),
            'ai_score' => $this->parseNumericValue($payload['ai_score'] ?? $currentEntry['ai_score'] ?? 0.0, (float) ($currentEntry['ai_score'] ?? 0.0)),
            'ai_recommended' => array_key_exists('ai_recommended', $payload) ? !empty($payload['ai_recommended']) : !empty($currentEntry['ai_recommended']),
            'is_active' => array_key_exists('is_active', $payload) ? !empty($payload['is_active']) : !empty($currentEntry['is_active']),
            'display_order' => max(
                1,
                (int) round($this->parseNumericValue($payload['display_order'] ?? $currentEntry['display_order'] ?? 1, (float) ($currentEntry['display_order'] ?? 1)))
            ),
        ];

        try {
            return $this->updateMapDestinationEntry($connection, $id, $updatedEntry);
        } catch (Throwable) {
            return false;
        }
    }

    public function optimizeMapDestinationsWithLocalSuggestions(): int
    {
        $this->ensureMapDestinationSchema();
        $connection = $this->connectionFactory->getConnection();
        $destinationsById = $this->loadDestinationsById();
        $travelPackages = $this->loadTravelPackagesForAdmin();
        $packages = $this->fetchAllOrEmpty('SELECT * FROM packages ORDER BY date_reservation DESC');
        $suggestions = $this->buildMapSuggestions($destinationsById, $travelPackages, $packages);
        if ($suggestions === []) {
            return 0;
        }

        $persisted = 0;
        foreach ($suggestions as $index => $suggestion) {
            $suggestion['ai_recommended'] = true;
            $suggestion['is_active'] = true;
            $suggestion['display_order'] = $index + 1;
            $existing = $this->findMapDestinationByCityCountry($connection, (string) ($suggestion['city'] ?? ''), (string) ($suggestion['country'] ?? ''));

            try {
                if ($existing !== null) {
                    $persisted += $this->updateMapDestinationEntry($connection, (int) ($existing['id'] ?? 0), [
                        ...$existing,
                        ...$suggestion,
                        'id' => (int) ($existing['id'] ?? 0),
                    ]) ? 1 : 0;
                } else {
                    $persisted += $this->insertMapDestinationEntry($connection, $suggestion) > 0 ? 1 : 0;
                }
            } catch (Throwable) {
            }
        }

        return $persisted;
    }

    public function generateAtmospheresWithLocalSuggestions(): int
    {
        $this->ensureAtmosphereSchema();
        $connection = $this->connectionFactory->getConnection();
        $entries = $this->loadAtmospheresForAdmin();
        if ($entries === []) {
            return 0;
        }

        $destinations = array_values($this->loadDestinationsById());
        $updated = 0;
        foreach ($entries as $entry) {
            $entryId = (int) ($entry['id'] ?? 0);
            if ($entryId <= 0) {
                continue;
            }

            try {
                $updated += $this->updateAtmosphereEntry(
                    $connection,
                    $entryId,
                    $this->enrichAtmosphereWithLocalSuggestions($entry, $destinations)
                ) ? 1 : 0;
            } catch (Throwable) {
            }
        }

        $this->normalizeAtmosphereDisplayOrder($connection);

        return $updated;
    }

    public function updateAtmosphere(int $id, array $payload): bool
    {
        $this->ensureAtmosphereSchema();
        $connection = $this->connectionFactory->getConnection();
        $currentEntry = $this->loadAtmosphereById($id);
        if ($currentEntry === null) {
            return false;
        }

        $updatedEntry = [
            ...$currentEntry,
            'title' => trim((string) ($payload['title'] ?? $currentEntry['title'] ?? '')),
            'description' => trim((string) ($payload['description'] ?? $currentEntry['description'] ?? '')),
            'video_path' => trim((string) ($payload['video_path'] ?? $currentEntry['video_path'] ?? '')),
            'ai_interest_tags' => trim((string) ($payload['ai_interest_tags'] ?? $currentEntry['ai_interest_tags'] ?? '')),
            'ai_suggested_destinations' => trim((string) ($payload['ai_suggested_destinations'] ?? $currentEntry['ai_suggested_destinations'] ?? '')),
            'ai_suggested_countries' => trim((string) ($payload['ai_suggested_countries'] ?? $currentEntry['ai_suggested_countries'] ?? '')),
            'ai_suggested_continents' => trim((string) ($payload['ai_suggested_continents'] ?? $currentEntry['ai_suggested_continents'] ?? '')),
            'ai_featured_payload' => trim((string) ($payload['ai_featured_payload'] ?? $currentEntry['ai_featured_payload'] ?? '')),
            'ai_score' => $this->parseNumericValue($payload['ai_score'] ?? $currentEntry['ai_score'] ?? 0.0, (float) ($currentEntry['ai_score'] ?? 0.0)),
            'avg_price' => $this->parseNumericValue($payload['avg_price'] ?? $currentEntry['avg_price'] ?? 0.0, (float) ($currentEntry['avg_price'] ?? 0.0)),
            'is_active' => array_key_exists('is_active', $payload) ? !empty($payload['is_active']) : !empty($currentEntry['is_active']),
            'display_order' => max(
                1,
                (int) round($this->parseNumericValue($payload['display_order'] ?? $currentEntry['display_order'] ?? 1, (float) ($currentEntry['display_order'] ?? 1)))
            ),
        ];

        try {
            $updated = $this->updateAtmosphereEntry($connection, $id, $updatedEntry);
            if ($updated) {
                $this->normalizeAtmosphereDisplayOrder($connection);
            }

            return $updated;
        } catch (Throwable) {
            return false;
        }
    }

    public function moveAtmosphere(int $id, int $direction): bool
    {
        $this->ensureAtmosphereSchema();
        $connection = $this->connectionFactory->getConnection();
        $entries = $this->loadAtmospheresForAdmin();
        $currentIndex = null;
        foreach ($entries as $index => $entry) {
            if ((int) ($entry['id'] ?? 0) === $id) {
                $currentIndex = $index;
                break;
            }
        }

        if ($currentIndex === null) {
            return false;
        }

        $targetIndex = $currentIndex + ($direction < 0 ? -1 : 1);
        if ($targetIndex < 0 || $targetIndex >= count($entries)) {
            return false;
        }

        $currentEntry = $entries[$currentIndex];
        $targetEntry = $entries[$targetIndex];

        try {
            $connection->beginTransaction();
            $currentOrder = (int) ($currentEntry['display_order'] ?? ($currentIndex + 1));
            $targetOrder = (int) ($targetEntry['display_order'] ?? ($targetIndex + 1));
            if (
                !$this->updateAtmosphereDisplayOrder($connection, (int) ($currentEntry['id'] ?? 0), $targetOrder)
                || !$this->updateAtmosphereDisplayOrder($connection, (int) ($targetEntry['id'] ?? 0), $currentOrder)
            ) {
                $connection->rollBack();

                return false;
            }

            $this->normalizeAtmosphereDisplayOrder($connection);
            $connection->commit();

            return true;
        } catch (Throwable) {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }

            return false;
        }
    }

    public function createAtmosphere(array $payload): int
    {
        $this->ensureAtmosphereSchema();
        $connection = $this->connectionFactory->getConnection();
        $title = trim((string) ($payload['title'] ?? ''));
        $type = strtoupper(trim((string) ($payload['atmosphere_type'] ?? '')));

        if ($title === '' && $type === '') {
            return 0;
        }

        if ($type === '') {
            $normalizedTitle = strtoupper($this->transliterate($title));
            $type = trim((string) preg_replace('/[^A-Z0-9]+/', '_', $normalizedTitle), '_');
        }

        try {
            $nextOrder = (int) ($connection->query('SELECT COALESCE(MAX(display_order), 0) + 1 FROM atmosphere_destinations')->fetchColumn() ?: 1);
            $entry = [
                ...$payload,
                'atmosphere_type' => $type,
                'title' => $title !== '' ? $title : $type,
                'is_active' => array_key_exists('is_active', $payload) ? !empty($payload['is_active']) : true,
                'display_order' => max(1, (int) ($payload['display_order'] ?? $nextOrder)),
                'created_by_admin' => 1,
            ];
            $id = $this->insertAtmosphereEntry($connection, $entry);
            $this->normalizeAtmosphereDisplayOrder($connection);

            return $id;
        } catch (Throwable) {
            return 0;
        }
    }

    public function deleteAtmosphere(int $id): bool
    {
        if ($id <= 0) {
            return false;
        }

        $this->ensureAtmosphereSchema();
        $connection = $this->connectionFactory->getConnection();

        try {
            $statement = $connection->prepare('DELETE FROM atmosphere_destinations WHERE id = :id');
            $deleted = $statement->execute(['id' => $id]);
            if ($deleted) {
                $this->normalizeAtmosphereDisplayOrder($connection);
            }

            return $deleted;
        } catch (Throwable) {
            return false;
        }
    }

    public function createReservationDraft(array $payload): int
    {
        $this->ensurePackagesAdminSchema();
        $connection = $this->connectionFactory->getConnection();
        $draft = $this->sanitizeReservationDraftPayload($payload);
        if ((int) ($draft['destination_id'] ?? 0) <= 0) {
            return 0;
        }

        try {
            $statement = $connection->prepare(
                'INSERT INTO packages (
                    destination_id, client_nom, client_email, date_debut, date_fin,
                    nb_adultes, nb_enfants, prix_total, statut, montant_paye,
                    methode_paiement, reference_paiement, montant_bloque,
                    admin_validation_note, validated_by_admin_email, validated_at, created_via_admin
                 ) VALUES (
                    :destination_id, :client_nom, :client_email, :date_debut, :date_fin,
                    :nb_adultes, :nb_enfants, :prix_total, :statut, :montant_paye,
                    :methode_paiement, :reference_paiement, :montant_bloque,
                    :admin_validation_note, :validated_by_admin_email, :validated_at, :created_via_admin
                 )'
            );
            $statement->execute([
                'destination_id' => (int) $draft['destination_id'],
                'client_nom' => (string) $draft['client_nom'],
                'client_email' => (string) $draft['client_email'],
                'date_debut' => (string) $draft['date_debut'],
                'date_fin' => (string) $draft['date_fin'],
                'nb_adultes' => (int) $draft['nb_adultes'],
                'nb_enfants' => (int) $draft['nb_enfants'],
                'prix_total' => (float) $draft['prix_total'],
                'statut' => 'EN_ATTENTE',
                'montant_paye' => 0.0,
                'methode_paiement' => 'ADMIN',
                'reference_paiement' => (string) $draft['reference_paiement'],
                'montant_bloque' => (float) $draft['prix_total'],
                'admin_validation_note' => 'Brouillon cree depuis le dashboard admin.',
                'validated_by_admin_email' => null,
                'validated_at' => null,
                'created_via_admin' => 1,
            ]);

            return (int) $connection->lastInsertId();
        } catch (Throwable) {
            return 0;
        }
    }

    public function acceptReservation(int $id, string $adminEmail = ''): bool
    {
        $this->ensurePackagesAdminSchema();
        $connection = $this->connectionFactory->getConnection();
        $reservation = $this->loadReservationById($id);
        if ($reservation === null) {
            return false;
        }

        $destination = $this->loadDestinationsById()[(int) ($reservation['destination_id'] ?? 0)] ?? null;
        $destinationLabel = trim((string) ($destination['nom'] ?? 'Reservation admin'));
        $reference = trim((string) ($reservation['reference_paiement'] ?? ''));
        if ($reference === '') {
            $reference = 'ADM-RES-'.$id.'-'.(new DateTimeImmutable('now'))->format('YmdHis');
        }

        try {
            $connection->beginTransaction();

            $updatePackage = $connection->prepare(
                'UPDATE packages
                 SET statut = :statut,
                     montant_paye = :montant_paye,
                     montant_bloque = 0,
                     methode_paiement = :methode_paiement,
                     reference_paiement = :reference_paiement,
                     admin_validation_note = :admin_validation_note,
                     validated_by_admin_email = :validated_by_admin_email,
                     validated_at = NOW()
                 WHERE id = :id'
            );
            $updatePackage->execute([
                'statut' => 'CONFIRMEE',
                'montant_paye' => (float) ($reservation['prix_total'] ?? 0.0),
                'methode_paiement' => 'ADMIN',
                'reference_paiement' => $reference,
                'admin_validation_note' => 'Reservation acceptee depuis le dashboard admin.',
                'validated_by_admin_email' => trim($adminEmail) !== '' ? trim($adminEmail) : null,
                'id' => $id,
            ]);

            $paymentId = (int) $this->fetchScalar(
                'SELECT id FROM paiements WHERE package_id = :package_id ORDER BY id DESC LIMIT 1',
                0,
                ['package_id' => $id]
            );

            if ($paymentId > 0) {
                $paymentStatement = $connection->prepare(
                    'UPDATE paiements
                     SET client_nom = :client_nom,
                         destination = :destination,
                         montant = :montant,
                         date_paiement = NOW(),
                         statut = :statut,
                         reference_transaction = :reference_transaction,
                         type_voyage = :type_voyage
                     WHERE id = :id'
                );
                $paymentStatement->execute([
                    'client_nom' => trim((string) ($reservation['client_nom'] ?? 'Client')),
                    'destination' => $destinationLabel,
                    'montant' => (float) ($reservation['prix_total'] ?? 0.0),
                    'statut' => 'PAYE',
                    'reference_transaction' => $reference,
                    'type_voyage' => 'Reservation admin',
                    'id' => $paymentId,
                ]);
            } else {
                $paymentStatement = $connection->prepare(
                    'INSERT INTO paiements (
                        client_nom, destination, montant, date_paiement, statut,
                        reference_transaction, package_id, numero_carte_masque, type_voyage
                     ) VALUES (
                        :client_nom, :destination, :montant, NOW(), :statut,
                        :reference_transaction, :package_id, :numero_carte_masque, :type_voyage
                     )'
                );
                $paymentStatement->execute([
                    'client_nom' => trim((string) ($reservation['client_nom'] ?? 'Client')),
                    'destination' => $destinationLabel,
                    'montant' => (float) ($reservation['prix_total'] ?? 0.0),
                    'statut' => 'PAYE',
                    'reference_transaction' => $reference,
                    'package_id' => $id,
                    'numero_carte_masque' => 'ADMIN',
                    'type_voyage' => 'Reservation admin',
                ]);
            }

            $connection->commit();

            return true;
        } catch (Throwable) {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }

            return false;
        }
    }

    public function refuseReservation(int $id, string $adminEmail = '', string $reason = ''): bool
    {
        $this->ensurePackagesAdminSchema();
        $connection = $this->connectionFactory->getConnection();
        $reservation = $this->loadReservationById($id);
        if ($reservation === null) {
            return false;
        }

        $note = trim($reason) !== '' ? trim($reason) : 'Refusee par l administration.';

        try {
            $connection->beginTransaction();

            $statement = $connection->prepare(
                'UPDATE packages
                 SET statut = :statut,
                     montant_bloque = 0,
                     admin_validation_note = :admin_validation_note,
                     validated_by_admin_email = :validated_by_admin_email,
                     validated_at = NOW()
                 WHERE id = :id'
            );
            $statement->execute([
                'statut' => 'ANNULEE',
                'admin_validation_note' => $note,
                'validated_by_admin_email' => trim($adminEmail) !== '' ? trim($adminEmail) : null,
                'id' => $id,
            ]);

            $paymentId = (int) $this->fetchScalar(
                'SELECT id FROM paiements WHERE package_id = :package_id ORDER BY id DESC LIMIT 1',
                0,
                ['package_id' => $id]
            );
            if ($paymentId > 0) {
                $paymentStatement = $connection->prepare(
                    'UPDATE paiements
                     SET statut = :statut,
                         date_paiement = NOW(),
                         reference_transaction = :reference_transaction
                     WHERE id = :id'
                );
                $paymentStatement->execute([
                    'statut' => 'REMBOURSE',
                    'reference_transaction' => 'ADM-REFUSED-'.$id,
                    'id' => $paymentId,
                ]);
            }

            $connection->commit();

            return true;
        } catch (Throwable) {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }

            return false;
        }
    }

    public function getReservationExportRows(int $limit = 8): array
    {
        $packages = $this->fetchAllOrEmpty('SELECT * FROM packages ORDER BY date_reservation DESC');
        $destinationsById = $this->loadDestinationsById();

        return array_map(
            fn (array $reservation): array => $this->decorateReservationEntry($reservation, $destinationsById),
            array_slice($packages, 0, max(1, $limit))
        );
    }

    public function createSponsor(array $payload): bool
    {
        $name = trim((string) ($payload['nom'] ?? ''));
        if ($name === '') {
            return false;
        }

        try {
            $this->ensureSponsorSchema();
            $statement = $this->connectionFactory->getConnection()->prepare(
                'INSERT INTO sponsor (nom, logo_url, logo_blob, logo_mime_type, description, site_web, type, montant_sponsoring, date_debut, date_fin, est_actif)
                 VALUES (:nom, :logo_url, :logo_blob, :logo_mime_type, :description, :site_web, :type, :montant_sponsoring, :date_debut, :date_fin, :est_actif)'
            );
            $statement->bindValue('nom', $name);
            $statement->bindValue('logo_url', trim((string) ($payload['logo_url'] ?? '')));
            $statement->bindValue('logo_blob', $payload['logo_blob'] ?? null, \PDO::PARAM_LOB);
            $statement->bindValue('logo_mime_type', trim((string) ($payload['logo_mime_type'] ?? '')));
            $statement->bindValue('description', trim((string) ($payload['description'] ?? 'Sponsor officiel EasyTravel.')));
            $statement->bindValue('site_web', trim((string) ($payload['site_web'] ?? '')));
            $statement->bindValue('type', trim((string) ($payload['type'] ?? 'Partenaire')));
            $statement->bindValue('montant_sponsoring', $this->parseNumericValue($payload['montant_sponsoring'] ?? 0.0));
            $statement->bindValue('date_debut', $this->normalizeNullableDate($payload['date_debut'] ?? null));
            $statement->bindValue('date_fin', $this->normalizeNullableDate($payload['date_fin'] ?? null));
            $statement->bindValue('est_actif', !empty($payload['est_actif']) ? 1 : 0, \PDO::PARAM_INT);

            return $statement->execute();
        } catch (Throwable) {
            return false;
        }
    }

    public function deleteSponsor(int $id): bool
    {
        if ($id < 0) {
            return false;
        }

        try {
            $statement = $this->connectionFactory->getConnection()->prepare('DELETE FROM sponsor WHERE id = :id');

            return $statement->execute(['id' => $id]);
        } catch (Throwable) {
            return false;
        }
    }

    public function findSponsorById(int $id): ?array
    {
        if ($id < 0) {
            return null;
        }

        try {
            $this->ensureSponsorSchema();
            $statement = $this->connectionFactory->getConnection()->prepare('SELECT * FROM sponsor WHERE id = :id LIMIT 1');
            $statement->execute(['id' => $id]);
            $entry = $statement->fetch();
            if (!is_array($entry)) {
                return null;
            }

            $entry['logo_display_url'] = $this->buildSponsorDisplayUrl($entry);

            return $entry;
        } catch (Throwable) {
            return null;
        }
    }

    public function findActiveSponsorsForHome(int $limit = 8): array
    {
        try {
            $this->ensureSponsorSchema();
            $statement = $this->connectionFactory->getConnection()->prepare(
                'SELECT *
                 FROM sponsor
                 WHERE COALESCE(est_actif, 1) = 1
                   AND (date_debut IS NULL OR date_debut = "" OR date_debut <= CURDATE())
                   AND (date_fin IS NULL OR date_fin = "" OR date_fin >= CURDATE())
                 ORDER BY montant_sponsoring DESC, nom ASC, id ASC
                 LIMIT :limit'
            );
            $statement->bindValue('limit', max(1, $limit), \PDO::PARAM_INT);
            $statement->execute();

            return array_map(function (array $entry): array {
                $entry['logo_display_url'] = $this->buildSponsorDisplayUrl($entry);

                return $entry;
            }, $statement->fetchAll());
        } catch (Throwable) {
            return [];
        }
    }

    public function findActiveAtmospheresForHome(int $limit = 4): array
    {
        try {
            $this->ensureAtmosphereSchema();
            $statement = $this->connectionFactory->getConnection()->prepare(
                'SELECT *
                 FROM atmosphere_destinations
                 WHERE COALESCE(is_active, 1) = 1
                 ORDER BY CASE WHEN COALESCE(display_order, 0) <= 0 THEN 999999 ELSE display_order END ASC, id ASC
                 LIMIT :limit'
            );
            $statement->bindValue('limit', max(1, $limit), \PDO::PARAM_INT);
            $statement->execute();

            $entries = array_map(
                fn (array $entry): array => $this->sanitizeAtmosphereEntry($entry),
                $statement->fetchAll()
            );
            $destinations = array_values($this->loadDestinationsById());

            return array_map(function (array $entry) use ($destinations): array {
                $hasSuggestions = trim((string) ($entry['ai_suggested_destinations'] ?? '')) !== ''
                    || trim((string) ($entry['ai_suggested_countries'] ?? '')) !== ''
                    || (float) ($entry['ai_score'] ?? 0.0) > 0.0;

                return $hasSuggestions
                    ? $entry
                    : $this->enrichAtmosphereWithLocalSuggestions($entry, $destinations);
            }, $entries);
        } catch (Throwable) {
            return [];
        }
    }

    public function findActiveMapDestinationsForHome(int $limit = self::MAP_SELECTION_LIMIT): array
    {
        $limit = max(1, $limit);

        try {
            $this->ensureMapDestinationSchema();
            $statement = $this->connectionFactory->getConnection()->prepare(
                'SELECT *
                 FROM map_destinations
                 WHERE COALESCE(is_active, 1) = 1
                 ORDER BY CASE WHEN COALESCE(display_order, 0) <= 0 THEN 999999 ELSE display_order END ASC, id ASC
                 LIMIT :limit'
            );
            $statement->bindValue('limit', $limit, \PDO::PARAM_INT);
            $statement->execute();

            $entries = array_map(
                fn (array $entry): array => $this->sanitizeMapDestinationEntry($entry),
                $statement->fetchAll()
            );

            if ($entries !== []) {
                return $entries;
            }
        } catch (Throwable) {
        }

        return array_slice(
            array_filter($this->buildFallbackMapEntries(), static fn (array $entry): bool => !empty($entry['is_active'])),
            0,
            $limit
        );
    }

    public function findFeaturedDestinationsForHome(int $limit = self::FEATURED_SELECTION_LIMIT): array
    {
        try {
            $this->ensureFeaturedDestinationSchema();
            $statement = $this->connectionFactory->getConnection()->prepare(
                'SELECT *
                 FROM featured_destinations
                 WHERE COALESCE(is_featured, 1) = 1
                 ORDER BY CASE WHEN COALESCE(display_order, 0) <= 0 THEN 999999 ELSE display_order END ASC, id ASC
                 LIMIT :limit'
            );
            $statement->bindValue('limit', max(1, $limit), \PDO::PARAM_INT);
            $statement->execute();

            return array_map(
                fn (array $entry): array => [
                    ...$entry,
                    'video_path' => trim((string) ($entry['video_path'] ?? '')) !== ''
                        ? trim((string) ($entry['video_path'] ?? ''))
                        : $this->inferFeaturedVideoPath(
                            (string) ($entry['destination_name'] ?? ''),
                            (string) ($entry['continent'] ?? '')
                        ),
                ],
                $statement->fetchAll()
            );
        } catch (Throwable) {
            return [];
        }
    }

    private function loadPackageStatsByEmail(): array
    {
        $statement = $this->connectionFactory->getConnection()->query(
            'SELECT LOWER(client_email) AS email,
                    COUNT(*) AS reservation_count,
                    COALESCE(SUM(prix_total), 0) AS total_reserved,
                    COALESCE(SUM(montant_paye), 0) AS total_paid,
                    MAX(date_reservation) AS last_reservation_at
             FROM packages
             GROUP BY LOWER(client_email)'
        );

        $stats = [];
        foreach ($statement->fetchAll() as $row) {
            $stats[(string) $row['email']] = $row;
        }

        return $stats;
    }

    private function resolveSegment(int $reservationCount, float $totalPaid, array $user): string
    {
        $role = strtoupper(trim((string) ($user['role'] ?? 'USER')));
        if ($role === 'AGENT') {
            return 'Agent';
        }

        if ($totalPaid >= 5000 || $reservationCount >= 3) {
            return 'Premium';
        }

        if (($user['is_pending_validation'] ?? false) === true || $reservationCount === 0) {
            return 'Nouveau';
        }

        return 'Standard';
    }

    private function resolveStatus(array $user): array
    {
        if (($user['is_active'] ?? true) !== true) {
            return ['label' => 'Suspendu', 'key' => 'suspended', 'tone' => 'red'];
        }

        if (($user['is_pending_validation'] ?? false) === true) {
            return ['label' => 'En attente', 'key' => 'pending', 'tone' => 'orange'];
        }

        if (strtoupper((string) ($user['role'] ?? 'USER')) === 'AGENT') {
            return ['label' => 'Agent', 'key' => 'agent', 'tone' => 'blue'];
        }

        return ['label' => 'Actif', 'key' => 'active', 'tone' => 'green'];
    }

    private function extractCountry(string $address): string
    {
        $address = trim($address);
        if ($address === '') {
            return 'Tunisie';
        }

        $parts = array_values(array_filter(array_map('trim', explode(',', $address))));
        if ($parts === []) {
            return 'Tunisie';
        }

        return $parts[count($parts) - 1];
    }

    private function fetchScalar(string $sql, int|float|string $fallback, array $params = []): int|float|string
    {
        try {
            if ($params === []) {
                $value = $this->connectionFactory->getConnection()->query($sql)->fetchColumn();

                return $value !== false && $value !== null ? $value : $fallback;
            }

            $statement = $this->connectionFactory->getConnection()->prepare($sql);
            $statement->execute($params);
            $value = $statement->fetchColumn();

            return $value !== false && $value !== null ? $value : $fallback;
        } catch (\Throwable) {
            return $fallback;
        }
    }

    private function fetchAllOrEmpty(string $sql): array
    {
        try {
            return $this->connectionFactory->getConnection()->query($sql)->fetchAll();
        } catch (\Throwable) {
            return [];
        }
    }

    private function loadFeaturedDestinations(): array
    {
        $this->ensureFeaturedDestinationSchema();

        return $this->fetchAllOrEmpty(
            'SELECT *
             FROM featured_destinations
             ORDER BY COALESCE(display_order, id) ASC, id ASC'
        );
    }

    private function loadTravelPackagesForAdmin(): array
    {
        return $this->fetchAllOrEmpty(
            'SELECT *
             FROM travel_packages
             ORDER BY COALESCE(display_order, id) ASC, id ASC'
        );
    }

    private function loadMapDestinationsForAdmin(): array
    {
        $this->ensureMapDestinationSchema();

        $entries = $this->fetchAllOrEmpty(
            'SELECT *
             FROM map_destinations
             ORDER BY CASE WHEN COALESCE(display_order, 0) <= 0 THEN 999999 ELSE display_order END ASC, id ASC'
        );
        if ($entries === []) {
            return $this->buildFallbackMapEntries();
        }

        return array_map(fn (array $entry): array => $this->sanitizeMapDestinationEntry($entry), $entries);
    }

    private function loadAtmospheresForAdmin(): array
    {
        $this->ensureAtmosphereSchema();

        $entries = $this->fetchAllOrEmpty(
            'SELECT *
             FROM atmosphere_destinations
             ORDER BY CASE WHEN COALESCE(display_order, 0) <= 0 THEN 999999 ELSE display_order END ASC, id ASC'
        );
        if ($entries === []) {
            return $this->buildAtmosphereSeedEntries();
        }

        return array_map(fn (array $entry): array => $this->sanitizeAtmosphereEntry($entry), $entries);
    }

    private function loadSponsorsForAdmin(): array
    {
        $this->ensureSponsorSchema();
        $entries = $this->fetchAllOrEmpty(
            'SELECT *
             FROM sponsor
             ORDER BY nom ASC, id ASC'
        );

        return array_map(function (array $entry): array {
            $entry['logo_display_url'] = $this->buildSponsorDisplayUrl($entry);

            return $entry;
        }, $entries);
    }

    private function loadFeaturedDestinationHistory(int $limit = 8): array
    {
        try {
            $statement = $this->connectionFactory->getConnection()->prepare(
                'SELECT *
                 FROM featured_destination_history
                 ORDER BY created_at DESC, id DESC
                 LIMIT :limit'
            );
            $statement->bindValue('limit', max(1, $limit), \PDO::PARAM_INT);
            $statement->execute();

            return $statement->fetchAll();
        } catch (Throwable) {
            return [];
        }
    }

    private function loadDestinationsById(): array
    {
        $destinations = [];

        foreach ($this->fetchAllOrEmpty('SELECT * FROM destinations ORDER BY nom ASC, pays ASC, id ASC') as $destination) {
            $destinationId = (int) ($destination['id'] ?? 0);
            if ($destinationId <= 0) {
                continue;
            }

            $destinations[$destinationId] = $destination;
        }

        return $destinations;
    }

    private function buildDestinationsAdminData(array $packages, array $featuredDestinations, array $destinationsById): array
    {
        $historyEntries = $this->loadFeaturedDestinationHistory(8);
        $reservationStats = $this->buildFeaturedReservationStats($packages, $destinationsById);
        $allDestinations = array_values($destinationsById);
        $decoratedEntries = [];
        $visibleCount = 0;
        $linkedReservations = 0;
        $totalAiScore = 0.0;
        $totalSatisfaction = 0.0;

        foreach ($featuredDestinations as $index => $entry) {
            $stats = $this->resolveFeaturedReservationStats($entry, $reservationStats);
            $decoratedEntry = $entry;
            $decoratedEntry['slot_number'] = $index + 1;
            $decoratedEntry['source_type'] = trim((string) ($entry['updated_from_ai_at'] ?? '')) !== '' ? 'IA' : 'MANUAL';
            $decoratedEntry['visual_tone'] = $this->resolveFeaturedVisualTone($entry, $index);
            $decoratedEntry['initials'] = $this->buildFeaturedInitials($entry);
            $decoratedEntry['matched_destination_id'] = $this->findMatchingDestinationId($allDestinations, $entry);
            $decoratedEntry['reservation_stats'] = $stats;
            $decoratedEntry['reservation_copy'] = $this->buildFeaturedReservationCopy($entry, $stats);
            $decoratedEntries[] = $decoratedEntry;

            $visibleCount += !empty($entry['is_featured']) ? 1 : 0;
            $linkedReservations += (int) ($stats['reservation_count'] ?? 0);
            $totalAiScore += (float) ($entry['ai_score'] ?? 0.0);
            $totalSatisfaction += (float) ($entry['satisfaction_score'] ?? 0.0);
        }

        return [
            'entries' => $decoratedEntries,
            'history_entries' => $historyEntries,
            'all_destinations' => $allDestinations,
            'visible_count' => $visibleCount,
            'linked_reservations' => $linkedReservations,
            'average_ai_score' => $decoratedEntries === [] ? 0.0 : $totalAiScore / count($decoratedEntries),
            'average_satisfaction' => $decoratedEntries === [] ? 0.0 : $totalSatisfaction / count($decoratedEntries),
            'home_slots' => self::FEATURED_SELECTION_LIMIT,
            'last_refresh_meta' => $this->formatFeaturedHistoryMeta($historyEntries[0] ?? null),
        ];
    }

    private function buildPackagesAdminData(array $travelPackages, array $packages, array $destinationsById): array
    {
        $activeCount = 0;
        $aiCount = 0;
        $totalAiScore = 0.0;
        $decoratedEntries = [];

        foreach ($travelPackages as $index => $entry) {
            $reservationStats = $this->resolveTravelPackageReservationStats($entry, $packages, $destinationsById);
            $decoratedEntries[] = [
                ...$entry,
                'badge_tone' => $this->resolveTravelPackageBadgeTone((string) ($entry['badge'] ?? '')),
                'source_tone' => !empty($entry['ai_generated']) ? 'blue' : 'violet',
                'reservation_stats' => $reservationStats,
                'package_initials' => $this->buildTravelPackageInitials($entry, $index),
                'ranking_tone' => $this->resolveTravelPackageRankingTone((string) ($entry['continent'] ?? ''), $index),
            ];

            $activeCount += !empty($entry['is_active']) ? 1 : 0;
            $aiCount += !empty($entry['ai_generated']) ? 1 : 0;
            $totalAiScore += (float) ($entry['ai_score'] ?? 0.0);
        }

        return [
            'entries' => $decoratedEntries,
            'active_count' => $activeCount,
            'ai_count' => $aiCount,
            'average_ai_score' => $decoratedEntries === [] ? 0.0 : $totalAiScore / count($decoratedEntries),
            'top_reserved' => $this->buildTopReservedPackages($travelPackages, $packages, $destinationsById, 4),
        ];
    }

    private function buildMapAdminData(array $entries): array
    {
        $decoratedEntries = [];
        $activeCount = 0;
        $aiCount = 0;
        $totalAiScore = 0.0;

        foreach ($entries as $index => $entry) {
            $sanitizedEntry = $this->sanitizeMapDestinationEntry($entry);
            $continent = strtolower(trim((string) ($sanitizedEntry['continent'] ?? '')));
            $sanitizedEntry['visual_tone'] = match ($continent) {
                'amerique' => 'blue',
                'asie' => 'indigo',
                'europe' => 'violet',
                'afrique' => 'orange',
                'oceanie' => 'green',
                default => match ($index % 4) {
                    0 => 'blue',
                    1 => 'indigo',
                    2 => 'violet',
                    default => 'orange',
                },
            };
            $sanitizedEntry['position_copy'] = ((float) ($sanitizedEntry['x_percent'] ?? 0.0) > 0.0 && (float) ($sanitizedEntry['y_percent'] ?? 0.0) > 0.0)
                ? 'Positionnee'
                : 'A placer';
            $decoratedEntries[] = $sanitizedEntry;
            $activeCount += !empty($sanitizedEntry['is_active']) ? 1 : 0;
            $aiCount += !empty($sanitizedEntry['ai_recommended']) ? 1 : 0;
            $totalAiScore += (float) ($sanitizedEntry['ai_score'] ?? 0.0);
        }

        return [
            'entries' => $decoratedEntries,
            'active_count' => $activeCount,
            'ai_count' => $aiCount,
            'average_ai_score' => $decoratedEntries === [] ? 0.0 : $totalAiScore / count($decoratedEntries),
            'source_copy' => 'map_destinations',
            'edit_copy' => 'clic + drag & drop',
            'sync_copy' => 'synchronisee',
        ];
    }

    private function buildAtmospheresAdminData(array $entries): array
    {
        $decoratedEntries = [];
        $activeCount = 0;

        foreach ($entries as $index => $entry) {
            $sanitizedEntry = $this->sanitizeAtmosphereEntry($entry);
            $type = strtoupper(trim((string) ($sanitizedEntry['atmosphere_type'] ?? 'ATMOSPHERE')));
            $sanitizedEntry['type_tone'] = match ($type) {
                'SAFARI' => 'orange',
                'URBAIN' => 'blue',
                'PLAGE' => 'green',
                'MONTAGNE' => 'violet',
                default => 'blue',
            };
            $sanitizedEntry['order_label'] = 'Ordre #'.max(1, (int) ($sanitizedEntry['display_order'] ?? ($index + 1)));
            $sanitizedEntry['visibility_label'] = !empty($sanitizedEntry['is_active']) ? 'Visible sur Home' : 'Masquee';
            $sanitizedEntry['preview_destinations'] = trim((string) ($sanitizedEntry['ai_suggested_destinations'] ?? '')) !== ''
                ? trim((string) ($sanitizedEntry['ai_suggested_destinations'] ?? ''))
                : 'Aucune suggestion visible';
            $sanitizedEntry['preview_countries'] = trim((string) ($sanitizedEntry['ai_suggested_countries'] ?? '')) !== ''
                ? trim((string) ($sanitizedEntry['ai_suggested_countries'] ?? ''))
                : 'Non renseignes';
            $sanitizedEntry['preview_continents'] = trim((string) ($sanitizedEntry['ai_suggested_continents'] ?? '')) !== ''
                ? trim((string) ($sanitizedEntry['ai_suggested_continents'] ?? ''))
                : 'Non renseignes';
            $sanitizedEntry['preview_score_text'] = (float) ($sanitizedEntry['ai_score'] ?? 0.0) > 0.0
                ? 'Score IA '.$this->formatScore((float) ($sanitizedEntry['ai_score'] ?? 0.0))
                : 'Score IA indisponible';
            $sanitizedEntry['preview_price_text'] = (float) ($sanitizedEntry['avg_price'] ?? 0.0) > 0.0
                ? 'Prix moyen '.$this->formatCurrencyCompact((float) ($sanitizedEntry['avg_price'] ?? 0.0))
                : 'Prix moyen indisponible';
            $updatedAt = $this->parseDateTime($sanitizedEntry['updated_from_ai_at'] ?? null);
            $sanitizedEntry['preview_update_text'] = $updatedAt instanceof DateTimeImmutable
                ? 'MAJ '.$updatedAt->format('d/m H:i')
                : 'MAJ en attente';
            $decoratedEntries[] = $sanitizedEntry;
            $activeCount += !empty($sanitizedEntry['is_active']) ? 1 : 0;
        }

        return [
            'entries' => $decoratedEntries,
            'active_count' => $activeCount,
            'sync_label' => 'LIVE',
        ];
    }

    private function buildReservationsAdminData(array $packages, array $payments, array $destinationsById): array
    {
        usort($packages, fn (array $left, array $right): int => $this->compareDates(
            $this->parseDateTime($right['date_reservation'] ?? null),
            $this->parseDateTime($left['date_reservation'] ?? null)
        ));

        $stats = $this->computeReservationDashboardStats($packages);
        $pipelineEntries = [];
        $validationEntries = [];
        $recentEntries = [];

        foreach ($packages as $package) {
            $entry = $this->decorateReservationEntry($package, $destinationsById);
            $recentEntries[] = $entry;

            if (strtoupper((string) ($package['statut'] ?? 'CONFIRMEE')) !== 'ANNULEE' && count($pipelineEntries) < 3) {
                $pipelineEntries[] = $entry;
            }

            if (strtoupper((string) ($package['statut'] ?? 'CONFIRMEE')) === 'EN_ATTENTE' && count($validationEntries) < 3) {
                $entry['validation_rank'] = str_pad((string) (count($validationEntries) + 1), 2, '0', STR_PAD_LEFT);
                $entry['validation_copy'] = $entry['title'].' | dossier '.$entry['id'].' | '.$entry['amount_compact'];
                $validationEntries[] = $entry;
            }

            if (count($recentEntries) >= 8 && count($pipelineEntries) >= 3 && count($validationEntries) >= 3) {
                break;
            }
        }

        $vipCount = count(array_filter($packages, fn (array $package): bool => $this->isVipReservationArray($package)));
        $recentPaidPayments = count(array_filter(
            $payments,
            function (array $payment): bool {
                if (!$this->isPaidPayment($payment)) {
                    return false;
                }

                $paymentDate = $this->parseDateTime($payment['date_paiement'] ?? null);
                if (!$paymentDate instanceof DateTimeImmutable) {
                    return false;
                }

                return $paymentDate >= new DateTimeImmutable('-1 day');
            }
        ));

        return [
            'stats' => $stats,
            'urgent_copy' => $this->buildUrgentReservationCopy($stats['pending_count'], $recentPaidPayments, $vipCount),
            'vip_count' => $vipCount,
            'recent_paid_payments' => $recentPaidPayments,
            'pipeline_entries' => $pipelineEntries,
            'validation_entries' => $validationEntries,
            'recent_entries' => array_slice($recentEntries, 0, 8),
            'forum_href' => '/admin/dashboard?section=reclamations',
        ];
    }

    private function computeReservationDashboardStats(array $packages): array
    {
        $today = new DateTimeImmutable('today');
        $startOfWeek = $today->modify('-'.((int) $today->format('N') - 1).' days');
        $currentMonth = $today->format('Y-m');
        $todayCount = 0;
        $weekCount = 0;
        $monthCount = 0;
        $pendingCount = 0;

        foreach ($packages as $package) {
            $status = strtoupper(trim((string) ($package['statut'] ?? 'CONFIRMEE')));
            if ($status === 'EN_ATTENTE') {
                $pendingCount++;
            }

            $referenceDate = $this->parseDateTime($package['date_reservation'] ?? null);
            if (!$referenceDate instanceof DateTimeImmutable) {
                continue;
            }

            if ($referenceDate->format('Y-m-d') === $today->format('Y-m-d')) {
                $todayCount++;
            }
            if ($referenceDate >= $startOfWeek && $referenceDate <= $today->setTime(23, 59, 59)) {
                $weekCount++;
            }
            if ($referenceDate->format('Y-m') === $currentMonth) {
                $monthCount++;
            }
        }

        return [
            'today_count' => $todayCount,
            'week_count' => $weekCount,
            'month_count' => $monthCount,
            'pending_count' => $pendingCount,
        ];
    }

    private function decorateReservationEntry(array $package, array $destinationsById): array
    {
        $status = strtoupper(trim((string) ($package['statut'] ?? 'CONFIRMEE')));
        $reservationDate = $this->parseDateTime($package['date_reservation'] ?? null);
        $travelDate = $this->parseDateTime($package['date_debut'] ?? null);
        $travellers = max(1, (int) ($package['nb_adultes'] ?? 0) + (int) ($package['nb_enfants'] ?? 0));
        $clientName = trim((string) ($package['client_nom'] ?? 'Client'));
        $destinationName = $this->resolveReservationDestinationName($package, $destinationsById);
        $code = $this->buildReservationCodeFromArray($package, $destinationsById);

        return [
            ...$package,
            'id' => (int) ($package['id'] ?? 0),
            'destination_name' => $destinationName !== '' ? $destinationName : 'Reservation',
            'client_name' => $clientName !== '' ? $clientName : 'Client',
            'travellers' => $travellers,
            'amount_compact' => $this->formatCurrencyCompact((float) ($package['prix_total'] ?? 0.0)),
            'time_badge' => $reservationDate instanceof DateTimeImmutable ? $reservationDate->format('H:i') : $code,
            'code' => $code,
            'title' => $destinationName !== '' ? $destinationName : 'Reservation #'.(int) ($package['id'] ?? 0),
            'subtitle' => 'Client '.($clientName !== '' ? $clientName : 'Client')
                .' | '.($travelDate instanceof DateTimeImmutable ? $travelDate->format('d/m/Y') : 'date a definir')
                .' | '.$travellers.' voyageur'.($travellers > 1 ? 's' : ''),
            'recent_subtitle' => ($clientName !== '' ? $clientName : 'Client')
                .' | '.($destinationName !== '' ? $destinationName : 'Destination inconnue')
                .' | '.$this->formatCurrencyCompact((float) ($package['prix_total'] ?? 0.0))
                .' | '.($reservationDate instanceof DateTimeImmutable ? $reservationDate->format('d/m/Y H:i') : 'horodatage indisponible'),
            'status_text' => $this->buildReservationStatusTextFromArray($package),
            'status_tone_class' => $this->resolveReservationStatusToneClass($package),
            'code_tone_class' => $this->resolveReservationCodeToneClass($package),
            'progress_percent' => $this->resolveReservationProgress($package),
            'progress_tone_class' => $this->resolveReservationProgressToneClass($package),
            'is_pending' => $status === 'EN_ATTENTE',
            'is_vip' => $this->isVipReservationArray($package),
        ];
    }

    private function buildUrgentReservationCopy(int $pendingCount, int $paidRecent, int $vipCount): string
    {
        return $pendingCount.' dossiers a verifier, '
            .$paidRecent.' paiements recents et '
            .$vipCount.' reservation(s) VIP.';
    }

    private function buildReservationCodeFromArray(array $package, array $destinationsById): string
    {
        $source = $this->resolveReservationDestinationName($package, $destinationsById);
        if ($source === '') {
            $source = trim((string) ($package['client_nom'] ?? 'RS'));
        }

        $normalized = preg_replace('/[^A-Za-z0-9 ]/', ' ', $this->transliterate($source)) ?? '';
        $normalized = trim($normalized);
        if ($normalized === '') {
            return 'RS';
        }

        $parts = preg_split('/\s+/', $normalized) ?: [];
        if (count($parts) <= 1) {
            return strtoupper(substr($parts[0] ?? $normalized, 0, 2));
        }

        return strtoupper(substr($parts[0], 0, 1).substr($parts[1], 0, 1));
    }

    private function resolveReservationDestinationName(array $package, array $destinationsById): string
    {
        return trim((string) (($destinationsById[(int) ($package['destination_id'] ?? 0)]['nom'] ?? '')));
    }

    private function buildReservationStatusTextFromArray(array $package): string
    {
        return match (strtoupper(trim((string) ($package['statut'] ?? 'CONFIRMEE')))) {
            'EN_ATTENTE' => 'A revoir',
            'ANNULEE' => 'Annulee',
            'CONFIRMEE' => $this->isVipReservationArray($package) ? 'VIP' : 'Paye',
            default => strtoupper(trim((string) ($package['statut'] ?? 'CONFIRMEE'))),
        };
    }

    private function resolveReservationProgress(array $package): int
    {
        return match (strtoupper(trim((string) ($package['statut'] ?? 'CONFIRMEE')))) {
            'EN_ATTENTE' => 58,
            'ANNULEE' => 12,
            'CONFIRMEE' => $this->isVipReservationArray($package) ? 88 : 82,
            default => 64,
        };
    }

    private function resolveReservationCodeToneClass(array $package): string
    {
        return match (strtoupper(trim((string) ($package['statut'] ?? 'CONFIRMEE')))) {
            'EN_ATTENTE' => 'admin-booking-code-orange',
            'ANNULEE' => 'admin-booking-code-violet',
            default => $this->isVipReservationArray($package) ? 'admin-booking-code-violet' : 'admin-booking-code-blue',
        };
    }

    private function resolveReservationStatusToneClass(array $package): string
    {
        return match (strtoupper(trim((string) ($package['statut'] ?? 'CONFIRMEE')))) {
            'EN_ATTENTE' => 'admin-status-pill-orange',
            'ANNULEE' => 'admin-status-pill-red',
            default => $this->isVipReservationArray($package) ? 'admin-status-pill-violet' : 'admin-status-pill-green',
        };
    }

    private function resolveReservationProgressToneClass(array $package): string
    {
        return match (strtoupper(trim((string) ($package['statut'] ?? 'CONFIRMEE')))) {
            'EN_ATTENTE' => 'admin-progress-fill-orange',
            'ANNULEE' => 'admin-progress-fill-violet',
            default => $this->isVipReservationArray($package) ? 'admin-progress-fill-violet' : 'admin-progress-fill-blue',
        };
    }

    private function isVipReservationArray(array $package): bool
    {
        return (float) ($package['prix_total'] ?? 0.0) >= 2500.0
            || ((int) ($package['nb_adultes'] ?? 0) + (int) ($package['nb_enfants'] ?? 0)) >= 5;
    }

    private function loadReservationById(int $id): ?array
    {
        $statement = $this->connectionFactory->getConnection()->prepare('SELECT * FROM packages WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $id]);
        $reservation = $statement->fetch();

        return is_array($reservation) ? $reservation : null;
    }

    private function sanitizeReservationDraftPayload(array $payload): array
    {
        $today = new DateTimeImmutable('today');
        $startDate = trim((string) ($payload['date_debut'] ?? ''));
        $endDate = trim((string) ($payload['date_fin'] ?? ''));
        if ($startDate === '') {
            $startDate = $today->modify('+14 days')->format('Y-m-d');
        }
        if ($endDate === '') {
            $endDate = $today->modify('+21 days')->format('Y-m-d');
        }

        $nbAdultes = max(1, (int) ($payload['nb_adultes'] ?? 2));
        $nbEnfants = max(0, (int) ($payload['nb_enfants'] ?? 0));
        $prixTotal = max(0.0, $this->parseNumericValue($payload['prix_total'] ?? 0.0, 0.0));

        return [
            'destination_id' => max(0, (int) ($payload['destination_id'] ?? 0)),
            'client_nom' => trim((string) ($payload['client_nom'] ?? 'Reservation admin')),
            'client_email' => trim((string) ($payload['client_email'] ?? 'admin-reservation@easytravel.local')),
            'date_debut' => $startDate,
            'date_fin' => $endDate,
            'nb_adultes' => $nbAdultes,
            'nb_enfants' => $nbEnfants,
            'prix_total' => $prixTotal,
            'reference_paiement' => 'ADM-DRAFT-'.(new DateTimeImmutable('now'))->format('YmdHis'),
        ];
    }

    private function buildFeaturedReservationStats(array $packages, array $destinationsById): array
    {
        $stats = [];

        foreach ($packages as $package) {
            $destinationId = (int) ($package['destination_id'] ?? 0);
            $destination = $destinationsById[$destinationId] ?? null;
            if ($destination === null) {
                continue;
            }

            $key = $this->buildFeaturedDestinationKey(
                (string) ($destination['nom'] ?? ''),
                (string) ($destination['pays'] ?? '')
            );
            $nameOnlyKey = $this->buildFeaturedDestinationKey((string) ($destination['nom'] ?? ''), '');
            $stats[$key] ??= [
                'reservation_count' => 0,
                'total_amount' => 0.0,
                'last_reservation_at' => null,
            ];

            $stats[$key]['reservation_count']++;
            $stats[$key]['total_amount'] += max(0.0, (float) ($package['prix_total'] ?? 0.0));
            $packageDate = $this->resolvePackageActivityDate($package);
            if (
                $packageDate instanceof DateTimeImmutable
                && (!$stats[$key]['last_reservation_at'] instanceof DateTimeImmutable
                    || $packageDate > $stats[$key]['last_reservation_at'])
            ) {
                $stats[$key]['last_reservation_at'] = $packageDate;
            }

            $stats[$nameOnlyKey] = $stats[$key];
        }

        return $stats;
    }

    private function resolveFeaturedReservationStats(array $entry, array $reservationStats): array
    {
        if ($reservationStats === []) {
            return [
                'reservation_count' => 0,
                'total_amount' => 0.0,
                'last_reservation_at' => null,
            ];
        }

        $key = $this->buildFeaturedDestinationKey(
            (string) ($entry['destination_name'] ?? $entry['name'] ?? ''),
            (string) ($entry['country'] ?? '')
        );
        if (isset($reservationStats[$key])) {
            return $reservationStats[$key];
        }

        return $reservationStats[$this->buildFeaturedDestinationKey((string) ($entry['destination_name'] ?? $entry['name'] ?? ''), '')]
            ?? [
                'reservation_count' => 0,
                'total_amount' => 0.0,
                'last_reservation_at' => null,
            ];
    }

    private function buildFeaturedSuggestionsFromReservations(array $packages, array $destinationsById, int $limit): array
    {
        $reservationStats = $this->buildFeaturedReservationStats($packages, $destinationsById);
        $rankings = [];

        foreach ($destinationsById as $destinationId => $destination) {
            $stats = $this->resolveFeaturedReservationStats([
                'destination_name' => (string) ($destination['nom'] ?? ''),
                'country' => (string) ($destination['pays'] ?? ''),
            ], $reservationStats);

            $rankings[] = [
                'destination_id' => $destinationId,
                'reservation_count' => (int) ($stats['reservation_count'] ?? 0),
                'total_amount' => (float) ($stats['total_amount'] ?? 0.0),
                'last_reservation_at' => $stats['last_reservation_at'] ?? null,
            ];
        }

        usort($rankings, function (array $left, array $right): int {
            $reservationCompare = ($right['reservation_count'] ?? 0) <=> ($left['reservation_count'] ?? 0);
            if ($reservationCompare !== 0) {
                return $reservationCompare;
            }

            $amountCompare = ($right['total_amount'] ?? 0.0) <=> ($left['total_amount'] ?? 0.0);
            if ($amountCompare !== 0) {
                return $amountCompare;
            }

            $leftTime = $left['last_reservation_at'] instanceof DateTimeImmutable ? $left['last_reservation_at']->getTimestamp() : 0;
            $rightTime = $right['last_reservation_at'] instanceof DateTimeImmutable ? $right['last_reservation_at']->getTimestamp() : 0;

            return $rightTime <=> $leftTime;
        });

        $suggestions = [];
        foreach (array_slice($rankings, 0, max(1, $limit)) as $index => $ranking) {
            $destination = $destinationsById[(int) ($ranking['destination_id'] ?? 0)] ?? null;
            if ($destination === null) {
                continue;
            }

            $suggestions[] = $this->createFeaturedEntryFromDestination(
                $destination,
                $reservationStats,
                $index + 1,
                true
            );
        }

        return $suggestions;
    }

    private function buildTravelPackageSuggestions(array $criteria, array $packages, array $destinationsById): array
    {
        $travelType = strtolower(trim((string) ($criteria['travel_type'] ?? 'couple')));
        $travelType = $travelType !== '' ? $travelType : 'couple';
        $budgetMin = max(200.0, $this->parseNumericValue($criteria['budget_min'] ?? 1200.0, 1200.0));
        $budgetMax = max($budgetMin, $this->parseNumericValue($criteria['budget_max'] ?? max(2400.0, $budgetMin + 600.0), max(2400.0, $budgetMin + 600.0)));
        $continent = trim((string) ($criteria['continent'] ?? 'Tous'));
        $durationDays = max(3, (int) round($this->parseNumericValue($criteria['duration_days'] ?? 7, 7.0)));
        $reservationCountByDestination = [];

        foreach ($packages as $reservation) {
            $destinationId = (int) ($reservation['destination_id'] ?? 0);
            if ($destinationId > 0) {
                $reservationCountByDestination[$destinationId] = (int) ($reservationCountByDestination[$destinationId] ?? 0) + 1;
            }
        }

        $suggestions = [];
        foreach ($destinationsById as $destinationId => $destination) {
            $destinationContinent = trim((string) ($destination['continent'] ?? ''));
            if (
                $continent !== ''
                && strtolower($continent) !== 'tous'
                && $this->normalizeFeaturedValue($destinationContinent) !== $this->normalizeFeaturedValue($continent)
            ) {
                continue;
            }

            $estimatedPrice = (float) ($destination['prix_base'] ?? 0.0);
            if ($estimatedPrice <= 0.0) {
                $estimatedPrice = ($budgetMin + $budgetMax) / 2.0;
            }

            $targetBudget = max($estimatedPrice, ($budgetMin + $budgetMax) / 2.0);
            $budgetGap = abs($estimatedPrice - $targetBudget);
            $budgetFit = $targetBudget <= 0.0 ? 15.0 : max(0.0, 24.0 - (($budgetGap / $targetBudget) * 22.0));
            $reservationBoost = min(24.0, ((int) ($reservationCountByDestination[$destinationId] ?? 0)) * 6.5);
            $keywordBoost = $this->keywordBoostForTravelPackage(
                (string) (($destination['description'] ?? '').' '.($destination['nom'] ?? '').' '.($destinationContinent ?: '')),
                $travelType
            );
            $aiScore = round($this->clamp(58.0 + $budgetFit + $reservationBoost + $keywordBoost, 56.0, 97.0), 1);

            $suggestions[] = $this->sanitizeTravelPackageEntry([
                'package_name' => $this->buildGeneratedTravelPackageName($travelType, (string) ($destination['nom'] ?? 'Destination')),
                'destinations' => trim((string) ($destination['nom'] ?? 'Destination')).(trim((string) ($destination['pays'] ?? '')) !== '' ? ', '.trim((string) ($destination['pays'] ?? '')) : ''),
                'continent' => $destinationContinent,
                'duration_days' => $durationDays,
                'price_from' => max(0.0, $estimatedPrice > 0.0 ? $estimatedPrice : $budgetMin),
                'price_to' => max(
                    max(0.0, $estimatedPrice > 0.0 ? $estimatedPrice : $budgetMin) + 180.0,
                    $estimatedPrice > 0.0 ? $estimatedPrice * 1.15 : $budgetMax
                ),
                'badge' => $this->resolveGeneratedTravelPackageBadge($aiScore, count($suggestions)),
                'image_path' => $this->resolveTravelPackageImagePath((string) ($destination['nom'] ?? ''), $destinationContinent),
                'description' => trim((string) ($destination['description'] ?? '')) !== ''
                    ? trim((string) ($destination['description'] ?? ''))
                    : $this->buildGeneratedTravelPackageDescription(
                        $this->buildGeneratedTravelPackageName($travelType, (string) ($destination['nom'] ?? 'Destination')),
                        (string) ($destination['nom'] ?? 'Destination'),
                        $travelType,
                        $destinationContinent
                    ),
                'travel_type' => $travelType,
                'interests' => implode(',', $this->resolveTravelPackageInterests($travelType)),
                'ai_generated' => 1,
                'ai_score' => $aiScore,
                'includes' => $this->resolveTravelPackageIncludes($travelType),
                'best_period' => $this->inferTravelPackageBestPeriod($destinationContinent),
                'is_active' => 1,
            ]);
        }

        usort($suggestions, fn (array $left, array $right): int => (($right['ai_score'] ?? 0.0) <=> ($left['ai_score'] ?? 0.0)));
        $suggestions = array_slice($suggestions, 0, self::TRAVEL_PACKAGE_AI_LIMIT);
        if ($suggestions !== []) {
            return $suggestions;
        }

        return $this->buildFallbackTravelPackageSuggestions($travelType, $budgetMin, $budgetMax, $continent, $durationDays);
    }

    private function createFeaturedEntryFromDestination(array $destination, array $reservationStats, int $displayOrder, bool $isFeatured): array
    {
        $stats = $this->resolveFeaturedReservationStats([
            'destination_name' => (string) ($destination['nom'] ?? ''),
            'country' => (string) ($destination['pays'] ?? ''),
        ], $reservationStats);
        $reservationCount = (int) ($stats['reservation_count'] ?? 0);
        $avgAmount = $reservationCount > 0
            ? ((float) ($stats['total_amount'] ?? 0.0)) / $reservationCount
            : (float) ($destination['prix_base'] ?? 0.0);
        $aiScore = $this->computeFeaturedAiScore($reservationCount, (float) ($stats['total_amount'] ?? 0.0));
        $continent = trim((string) ($destination['continent'] ?? ''));

        return [
            'destination_name' => trim((string) ($destination['nom'] ?? 'Destination')),
            'country' => trim((string) ($destination['pays'] ?? 'Pays')),
            'continent' => $continent,
            'description' => trim((string) ($destination['description'] ?? '')) !== ''
                ? trim((string) ($destination['description'] ?? ''))
                : 'Recommendation IA',
            'video_path' => $this->inferFeaturedVideoPath((string) ($destination['nom'] ?? ''), $continent),
            'ai_score' => $aiScore,
            'satisfaction_score' => $this->computeFeaturedSatisfactionScore($aiScore),
            'avg_price' => max(0.0, $avgAmount),
            'best_season' => $this->inferBestSeason((string) ($destination['nom'] ?? ''), $continent),
            'travel_types' => $this->inferTravelTypes((string) ($destination['description'] ?? ''), $continent),
            'interests' => $this->inferInterests((string) ($destination['description'] ?? ''), $continent),
            'is_featured' => $isFeatured ? 1 : 0,
            'display_order' => max(1, $displayOrder),
            'updated_from_ai_at' => (new DateTimeImmutable('now'))->format('Y-m-d H:i:s'),
        ];
    }

    private function loadFeaturedDestinationById(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        try {
            $statement = $this->connectionFactory->getConnection()->prepare(
                'SELECT * FROM featured_destinations WHERE id = :id'
            );
            $statement->execute(['id' => $id]);
            $entry = $statement->fetch();

            return is_array($entry) ? $entry : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function insertFeaturedDestinationEntry(object $connection, array $entry): int
    {
        $statement = $connection->prepare(
            'INSERT INTO featured_destinations
             (destination_name, country, continent, description, video_path, ai_score, satisfaction_score, avg_price, best_season, travel_types, interests, is_featured, display_order, updated_from_ai_at)
             VALUES (:destination_name, :country, :continent, :description, :video_path, :ai_score, :satisfaction_score, :avg_price, :best_season, :travel_types, :interests, :is_featured, :display_order, :updated_from_ai_at)'
        );
        $statement->execute($this->normalizeFeaturedEntryPayload($entry));

        return (int) $connection->lastInsertId();
    }

    private function updateFeaturedDestinationEntry(object $connection, int $id, array $entry): bool
    {
        $statement = $connection->prepare(
            'UPDATE featured_destinations
             SET destination_name = :destination_name,
                 country = :country,
                 continent = :continent,
                 description = :description,
                 video_path = :video_path,
                 ai_score = :ai_score,
                 satisfaction_score = :satisfaction_score,
                 avg_price = :avg_price,
                 best_season = :best_season,
                 travel_types = :travel_types,
                 interests = :interests,
                 is_featured = :is_featured,
                 display_order = :display_order,
                 updated_from_ai_at = :updated_from_ai_at
             WHERE id = :id'
        );

        return $statement->execute([
            ...$this->normalizeFeaturedEntryPayload($entry),
            'id' => $id,
        ]);
    }

    private function updateFeaturedDisplayOrder(object $connection, int $id, int $displayOrder): bool
    {
        if ($id <= 0) {
            return false;
        }

        $statement = $connection->prepare(
            'UPDATE featured_destinations SET display_order = :display_order WHERE id = :id'
        );

        return $statement->execute([
            'display_order' => max(1, $displayOrder),
            'id' => $id,
        ]);
    }

    private function logFeaturedHistory(
        object $connection,
        int $featuredId,
        string $actionType,
        string $destinationName,
        float $aiScore,
        string $note,
        int $adminId
    ): void {
        try {
            $statement = $connection->prepare(
                'INSERT INTO featured_destination_history
                 (featured_destination_id, action_type, destination_name, ai_score, note, created_by_admin)
                 VALUES (:featured_destination_id, :action_type, :destination_name, :ai_score, :note, :created_by_admin)'
            );
            $statement->execute([
                'featured_destination_id' => max(0, $featuredId),
                'action_type' => trim($actionType),
                'destination_name' => trim($destinationName),
                'ai_score' => max(0.0, $aiScore),
                'note' => trim($note),
                'created_by_admin' => max(0, $adminId),
            ]);
        } catch (Throwable) {
        }
    }

    private function normalizeFeaturedEntryPayload(array $entry): array
    {
        return [
            'destination_name' => trim((string) ($entry['destination_name'] ?? $entry['name'] ?? '')),
            'country' => trim((string) ($entry['country'] ?? '')),
            'continent' => trim((string) ($entry['continent'] ?? '')),
            'description' => trim((string) ($entry['description'] ?? '')),
            'video_path' => trim((string) ($entry['video_path'] ?? '')),
            'ai_score' => max(0.0, $this->parseNumericValue($entry['ai_score'] ?? 0.0)),
            'satisfaction_score' => max(0.0, $this->parseNumericValue($entry['satisfaction_score'] ?? 0.0)),
            'avg_price' => max(0.0, $this->parseNumericValue($entry['avg_price'] ?? $entry['price_base'] ?? 0.0)),
            'best_season' => trim((string) ($entry['best_season'] ?? '')),
            'travel_types' => trim((string) ($entry['travel_types'] ?? '')),
            'interests' => trim((string) ($entry['interests'] ?? '')),
            'is_featured' => !empty($entry['is_featured']) ? 1 : 0,
            'display_order' => max(1, (int) ($entry['display_order'] ?? 1)),
            'updated_from_ai_at' => trim((string) ($entry['updated_from_ai_at'] ?? (new DateTimeImmutable('now'))->format('Y-m-d H:i:s'))),
        ];
    }

    private function loadTravelPackageById(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        try {
            $statement = $this->connectionFactory->getConnection()->prepare('SELECT * FROM travel_packages WHERE id = :id LIMIT 1');
            $statement->execute(['id' => $id]);
            $entry = $statement->fetch();

            return is_array($entry) ? $entry : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function insertTravelPackageEntry(object $connection, array $entry): int
    {
        $statement = $connection->prepare(
            'INSERT INTO travel_packages
             (package_name, destinations, continent, duration_days, price_from, price_to, badge, image_path, description, travel_type, interests, ai_generated, ai_score, includes, best_period, is_active, display_order)
             VALUES (:package_name, :destinations, :continent, :duration_days, :price_from, :price_to, :badge, :image_path, :description, :travel_type, :interests, :ai_generated, :ai_score, :includes, :best_period, :is_active, :display_order)'
        );
        $statement->execute($this->normalizeTravelPackagePayload($entry));

        return (int) $connection->lastInsertId();
    }

    private function updateTravelPackageEntry(object $connection, int $id, array $entry): bool
    {
        $statement = $connection->prepare(
            'UPDATE travel_packages
             SET package_name = :package_name,
                 destinations = :destinations,
                 continent = :continent,
                 duration_days = :duration_days,
                 price_from = :price_from,
                 price_to = :price_to,
                 badge = :badge,
                 image_path = :image_path,
                 description = :description,
                 travel_type = :travel_type,
                 interests = :interests,
                 ai_generated = :ai_generated,
                 ai_score = :ai_score,
                 includes = :includes,
                 best_period = :best_period,
                 is_active = :is_active,
                 display_order = :display_order
             WHERE id = :id'
        );

        return $statement->execute([
            ...$this->normalizeTravelPackagePayload($entry),
            'id' => $id,
        ]);
    }

    private function findNextTravelPackageDisplayOrder(object $connection): int
    {
        try {
            $value = $connection->query('SELECT COALESCE(MAX(display_order), 0) FROM travel_packages')->fetchColumn();

            return max(1, ((int) $value) + 1);
        } catch (Throwable) {
            return 1;
        }
    }

    private function normalizeTravelPackagePayload(array $entry): array
    {
        $sanitized = $this->sanitizeTravelPackageEntry($entry);

        return [
            'package_name' => trim((string) ($sanitized['package_name'] ?? '')),
            'destinations' => trim((string) ($sanitized['destinations'] ?? '')),
            'continent' => trim((string) ($sanitized['continent'] ?? '')),
            'duration_days' => max(1, (int) ($sanitized['duration_days'] ?? 1)),
            'price_from' => max(0.0, $this->parseNumericValue($sanitized['price_from'] ?? 0.0)),
            'price_to' => max(
                max(0.0, $this->parseNumericValue($sanitized['price_from'] ?? 0.0)),
                $this->parseNumericValue($sanitized['price_to'] ?? $sanitized['price_from'] ?? 0.0)
            ),
            'badge' => trim((string) ($sanitized['badge'] ?? 'Nouveau')),
            'image_path' => trim((string) ($sanitized['image_path'] ?? '')),
            'description' => trim((string) ($sanitized['description'] ?? '')),
            'travel_type' => strtolower(trim((string) ($sanitized['travel_type'] ?? 'couple'))),
            'interests' => trim((string) ($sanitized['interests'] ?? '')),
            'ai_generated' => !empty($sanitized['ai_generated']) ? 1 : 0,
            'ai_score' => round($this->clamp($this->parseNumericValue($sanitized['ai_score'] ?? 0.0), 0.0, 99.0), 1),
            'includes' => trim((string) ($sanitized['includes'] ?? '')),
            'best_period' => trim((string) ($sanitized['best_period'] ?? '')),
            'is_active' => !empty($sanitized['is_active']) ? 1 : 0,
            'display_order' => max(1, (int) ($sanitized['display_order'] ?? 1)),
        ];
    }

    private function sanitizeTravelPackageEntry(array $entry): array
    {
        $travelType = strtolower(trim((string) ($entry['travel_type'] ?? 'couple')));
        $travelType = $travelType !== '' ? $travelType : 'couple';
        $packageName = trim((string) ($entry['package_name'] ?? ''));
        if ($packageName === '') {
            $packageName = 'Package signature';
        }

        $destinations = trim((string) ($entry['destinations'] ?? ''));
        if ($destinations === '') {
            $destinations = $packageName;
        }

        $continent = trim((string) ($entry['continent'] ?? ''));
        if ($continent === '') {
            $continent = 'Monde';
        }

        $badge = trim((string) ($entry['badge'] ?? ''));
        if ($badge === '') {
            $badge = 'Nouveau';
        }

        $description = trim((string) ($entry['description'] ?? ''));
        if ($description === '') {
            $description = $this->buildGeneratedTravelPackageDescription($packageName, $destinations, $travelType, $continent);
        }

        $interests = trim((string) ($entry['interests'] ?? ''));
        if ($interests === '') {
            $interests = implode(',', $this->resolveTravelPackageInterests($travelType));
        }

        $includes = trim((string) ($entry['includes'] ?? ''));
        if ($includes === '') {
            $includes = $this->resolveTravelPackageIncludes($travelType);
        }

        $bestPeriod = trim((string) ($entry['best_period'] ?? ''));
        if ($bestPeriod === '') {
            $bestPeriod = $this->inferTravelPackageBestPeriod($continent);
        }

        $imagePath = trim((string) ($entry['image_path'] ?? ''));
        if ($imagePath === '') {
            $imagePath = $this->resolveTravelPackageImagePath($destinations, $continent);
        }

        return [
            ...$entry,
            'package_name' => $packageName,
            'destinations' => $destinations,
            'continent' => $continent,
            'duration_days' => max(1, (int) round($this->parseNumericValue($entry['duration_days'] ?? 1, 1.0))),
            'price_from' => max(0.0, $this->parseNumericValue($entry['price_from'] ?? 0.0)),
            'price_to' => max(
                max(0.0, $this->parseNumericValue($entry['price_from'] ?? 0.0)),
                $this->parseNumericValue($entry['price_to'] ?? $entry['price_from'] ?? 0.0)
            ),
            'badge' => $badge,
            'image_path' => $imagePath,
            'description' => $description,
            'travel_type' => $travelType,
            'interests' => $interests,
            'ai_generated' => !empty($entry['ai_generated']) ? 1 : 0,
            'ai_score' => round($this->clamp($this->parseNumericValue($entry['ai_score'] ?? 0.0), 0.0, 99.0), 1),
            'includes' => $includes,
            'best_period' => $bestPeriod,
            'is_active' => !empty($entry['is_active']) ? 1 : 0,
            'display_order' => max(1, (int) ($entry['display_order'] ?? 1)),
        ];
    }

    private function loadMapDestinationById(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $this->ensureMapDestinationSchema();

        try {
            $statement = $this->connectionFactory->getConnection()->prepare('SELECT * FROM map_destinations WHERE id = :id LIMIT 1');
            $statement->execute(['id' => $id]);
            $entry = $statement->fetch();

            return is_array($entry) ? $this->sanitizeMapDestinationEntry($entry) : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function insertMapDestinationEntry(object $connection, array $entry): int
    {
        $statement = $connection->prepare(
            'INSERT INTO map_destinations
             (city, country, continent, package_name, duration, price, original_price, image_path, description, best_period, includes, highlight_1, highlight_2, highlight_3, x_percent, y_percent, ai_score, ai_recommended, is_active, display_order)
             VALUES (:city, :country, :continent, :package_name, :duration, :price, :original_price, :image_path, :description, :best_period, :includes, :highlight_1, :highlight_2, :highlight_3, :x_percent, :y_percent, :ai_score, :ai_recommended, :is_active, :display_order)'
        );
        $statement->execute($this->normalizeMapDestinationPayload($entry));

        return (int) $connection->lastInsertId();
    }

    private function updateMapDestinationEntry(object $connection, int $id, array $entry): bool
    {
        $statement = $connection->prepare(
            'UPDATE map_destinations
             SET city = :city,
                 country = :country,
                 continent = :continent,
                 package_name = :package_name,
                 duration = :duration,
                 price = :price,
                 original_price = :original_price,
                 image_path = :image_path,
                 description = :description,
                 best_period = :best_period,
                 includes = :includes,
                 highlight_1 = :highlight_1,
                 highlight_2 = :highlight_2,
                 highlight_3 = :highlight_3,
                 x_percent = :x_percent,
                 y_percent = :y_percent,
                 ai_score = :ai_score,
                 ai_recommended = :ai_recommended,
                 is_active = :is_active,
                 display_order = :display_order
             WHERE id = :id'
        );

        return $statement->execute([
            ...$this->normalizeMapDestinationPayload($entry),
            'id' => $id,
        ]);
    }

    private function findNextMapDestinationDisplayOrder(object $connection): int
    {
        try {
            $value = $connection->query('SELECT COALESCE(MAX(display_order), 0) FROM map_destinations')->fetchColumn();

            return max(1, ((int) $value) + 1);
        } catch (Throwable) {
            return 1;
        }
    }

    private function normalizeMapDestinationPayload(array $entry): array
    {
        $sanitized = $this->sanitizeMapDestinationEntry($entry);

        return [
            'city' => trim((string) ($sanitized['city'] ?? '')),
            'country' => trim((string) ($sanitized['country'] ?? '')),
            'continent' => trim((string) ($sanitized['continent'] ?? '')),
            'package_name' => trim((string) ($sanitized['package_name'] ?? '')),
            'duration' => trim((string) ($sanitized['duration'] ?? '')),
            'price' => trim((string) ($sanitized['price'] ?? '')),
            'original_price' => trim((string) ($sanitized['original_price'] ?? '')),
            'image_path' => trim((string) ($sanitized['image_path'] ?? '')),
            'description' => trim((string) ($sanitized['description'] ?? '')),
            'best_period' => trim((string) ($sanitized['best_period'] ?? '')),
            'includes' => trim((string) ($sanitized['includes'] ?? '')),
            'highlight_1' => trim((string) ($sanitized['highlight_1'] ?? '')),
            'highlight_2' => trim((string) ($sanitized['highlight_2'] ?? '')),
            'highlight_3' => trim((string) ($sanitized['highlight_3'] ?? '')),
            'x_percent' => round($this->clamp((float) ($sanitized['x_percent'] ?? 0.5), 0.02, 0.98), 3),
            'y_percent' => round($this->clamp((float) ($sanitized['y_percent'] ?? 0.5), 0.05, 0.95), 3),
            'ai_score' => round($this->clamp((float) ($sanitized['ai_score'] ?? 0.0), 0.0, 99.0), 1),
            'ai_recommended' => !empty($sanitized['ai_recommended']) ? 1 : 0,
            'is_active' => !empty($sanitized['is_active']) ? 1 : 0,
            'display_order' => max(1, (int) ($sanitized['display_order'] ?? 1)),
        ];
    }

    private function sanitizeMapDestinationEntry(array $entry): array
    {
        $city = trim((string) ($entry['city'] ?? ''));
        if ($city === '') {
            $city = 'Nouvelle destination';
        }

        $country = trim((string) ($entry['country'] ?? ''));
        if ($country === '') {
            $country = 'Pays';
        }

        $continent = trim((string) ($entry['continent'] ?? ''));
        if ($continent === '') {
            $continent = 'Monde';
        }

        $packageName = trim((string) ($entry['package_name'] ?? ''));
        if ($packageName === '') {
            $packageName = 'Package signature';
        }

        $duration = trim((string) ($entry['duration'] ?? ''));
        if ($duration === '') {
            $duration = '7 jours / 6 nuits';
        }

        $price = trim((string) ($entry['price'] ?? ''));
        if ($price === '') {
            $price = '1490 EUR';
        }

        $originalPrice = trim((string) ($entry['original_price'] ?? ''));
        if ($originalPrice === '') {
            $originalPrice = $price;
        }

        $description = trim((string) ($entry['description'] ?? ''));
        if ($description === '') {
            $description = 'Destination mise en avant pour la carte interactive Home.';
        }

        $bestPeriod = trim((string) ($entry['best_period'] ?? ''));
        if ($bestPeriod === '') {
            $bestPeriod = $this->inferMapBestPeriod($continent);
        }

        $includes = trim((string) ($entry['includes'] ?? ''));
        if ($includes === '') {
            $includes = 'Vol,Hotel,Guide';
        }

        $highlight1 = trim((string) ($entry['highlight_1'] ?? ''));
        if ($highlight1 === '') {
            $highlight1 = 'Experience signature';
        }

        $highlight2 = trim((string) ($entry['highlight_2'] ?? ''));
        if ($highlight2 === '') {
            $highlight2 = 'Budget bien calibre';
        }

        $highlight3 = trim((string) ($entry['highlight_3'] ?? ''));
        if ($highlight3 === '') {
            $highlight3 = 'Suggestion IA';
        }

        $imagePath = trim((string) ($entry['image_path'] ?? ''));
        if ($imagePath === '') {
            $imagePath = $this->resolveMapImagePath($city, $continent);
        }

        return [
            ...$entry,
            'city' => $city,
            'country' => $country,
            'continent' => $continent,
            'package_name' => $packageName,
            'duration' => $duration,
            'price' => $price,
            'original_price' => $originalPrice,
            'image_path' => $imagePath,
            'description' => $description,
            'best_period' => $bestPeriod,
            'includes' => $includes,
            'highlight_1' => $highlight1,
            'highlight_2' => $highlight2,
            'highlight_3' => $highlight3,
            'x_percent' => $this->clamp($this->parseNumericValue($entry['x_percent'] ?? 0.5, 0.5), 0.02, 0.98),
            'y_percent' => $this->clamp($this->parseNumericValue($entry['y_percent'] ?? 0.5, 0.5), 0.05, 0.95),
            'ai_score' => $this->clamp($this->parseNumericValue($entry['ai_score'] ?? 0.0, 0.0), 0.0, 99.0),
            'ai_recommended' => !empty($entry['ai_recommended']) ? 1 : 0,
            'is_active' => array_key_exists('is_active', $entry) ? (!empty($entry['is_active']) ? 1 : 0) : 1,
            'display_order' => max(1, (int) round($this->parseNumericValue($entry['display_order'] ?? 1, 1.0))),
        ];
    }

    private function buildMapSuggestions(array $destinationsById, array $travelPackages, array $packages): array
    {
        $reservationCountByDestinationId = [];
        foreach ($packages as $package) {
            $destinationId = (int) ($package['destination_id'] ?? 0);
            if ($destinationId > 0) {
                $reservationCountByDestinationId[$destinationId] = ($reservationCountByDestinationId[$destinationId] ?? 0) + 1;
            }
        }

        $candidates = [];
        foreach ($destinationsById as $destinationId => $destination) {
            $candidate = $this->buildMapSuggestionCandidate(
                $destination,
                $travelPackages,
                (int) ($reservationCountByDestinationId[(int) $destinationId] ?? 0),
                (int) $destinationId
            );
            if ($candidate !== null) {
                $candidates[] = $candidate;
            }
        }

        if ($candidates === []) {
            return array_slice($this->buildFallbackMapEntries(), 0, self::MAP_SELECTION_LIMIT);
        }

        usort($candidates, static fn (array $left, array $right): int => ((float) ($right['ai_score'] ?? 0.0)) <=> ((float) ($left['ai_score'] ?? 0.0)));

        return $this->selectDiverseMapSuggestions($candidates);
    }

    private function buildMapSuggestionCandidate(array $destination, array $travelPackages, int $reservationCount, int $destinationId): ?array
    {
        $city = trim((string) ($destination['nom'] ?? ''));
        if ($city === '') {
            return null;
        }

        $continent = trim((string) ($destination['continent'] ?? ''));
        $linkedPackage = $this->findBestTravelPackageForMapDestination($destination, $travelPackages);
        $linkedPrice = $linkedPackage !== null
            ? max(0.0, $this->parseNumericValue($linkedPackage['price_from'] ?? 0.0))
            : max(0.0, $this->parseNumericValue($destination['prix_base'] ?? 0.0));

        $reservationBoost = min(18.0, $reservationCount * 4.0);
        $packageBoost = $linkedPackage !== null ? ((float) ($linkedPackage['ai_score'] ?? 0.0) * 0.18) : 8.0;
        $priceBalance = $linkedPrice > 0.0 ? 16.0 - min(12.0, $linkedPrice / 300.0) : 6.0;
        $diversityBoost = match (strtolower(trim($continent))) {
            'afrique', 'oceanie' => 12.0,
            'amerique' => 10.0,
            default => 8.0,
        };
        $aiScore = $this->clamp(58.0 + $reservationBoost + $packageBoost + $priceBalance + $diversityBoost, 60.0, 98.0);
        $seed = abs(crc32(strtolower($continent.'-'.$destinationId)));
        [$xPercent, $yPercent] = $this->resolveMapCoordinates($city, $continent, $seed);
        $durationDays = max(1, (int) round($this->parseNumericValue($linkedPackage['duration_days'] ?? 7, 7.0)));

        return $this->sanitizeMapDestinationEntry([
            'city' => $city,
            'country' => trim((string) ($destination['pays'] ?? '')),
            'continent' => $continent,
            'package_name' => $linkedPackage !== null
                ? trim((string) ($linkedPackage['package_name'] ?? ''))
                : 'Pack '.$city,
            'duration' => $linkedPackage !== null
                ? $durationDays.' jours / '.max(0, $durationDays - 1).' nuits'
                : '7 jours / 6 nuits',
            'price' => $this->formatMapPrice($linkedPrice > 0.0 ? $linkedPrice : 1490.0),
            'original_price' => $this->formatMapPrice(($linkedPrice > 0.0 ? $linkedPrice : 1490.0) * 1.15),
            'image_path' => $linkedPackage !== null
                ? trim((string) ($linkedPackage['image_path'] ?? ''))
                : $this->resolveMapImagePath($city, $continent),
            'description' => trim((string) ($linkedPackage['description'] ?? '')) !== ''
                ? trim((string) ($linkedPackage['description'] ?? ''))
                : $this->buildMapDestinationDescription($destination),
            'best_period' => trim((string) ($linkedPackage['best_period'] ?? '')) !== ''
                ? trim((string) ($linkedPackage['best_period'] ?? ''))
                : $this->inferMapBestPeriod($continent),
            'includes' => trim((string) ($linkedPackage['includes'] ?? '')) !== ''
                ? trim((string) ($linkedPackage['includes'] ?? ''))
                : 'Vol,Hotel,Guide',
            'highlight_1' => 'Top satisfaction voyageurs',
            'highlight_2' => 'Budget moyen compatible',
            'highlight_3' => 'Diversite geographique forte',
            'x_percent' => $xPercent,
            'y_percent' => $yPercent,
            'ai_score' => round($aiScore, 1),
            'ai_recommended' => 1,
            'is_active' => 1,
        ]);
    }

    private function selectDiverseMapSuggestions(array $ranked): array
    {
        $selected = [];
        $usedContinents = [];

        foreach ($ranked as $entry) {
            $continent = strtolower(trim((string) ($entry['continent'] ?? '')));
            if (!isset($usedContinents[$continent])) {
                $selected[] = $entry;
                $usedContinents[$continent] = true;
            }
            if (count($selected) >= self::MAP_SELECTION_LIMIT) {
                break;
            }
        }

        foreach ($ranked as $entry) {
            if (count($selected) >= self::MAP_SELECTION_LIMIT) {
                break;
            }

            $alreadySelected = array_filter($selected, fn (array $item): bool => $this->buildMapLookupKey((string) ($item['city'] ?? ''), (string) ($item['country'] ?? '')) === $this->buildMapLookupKey((string) ($entry['city'] ?? ''), (string) ($entry['country'] ?? '')));
            if ($alreadySelected === []) {
                $selected[] = $entry;
            }
        }

        foreach ($selected as $index => &$entry) {
            $entry['display_order'] = $index + 1;
        }
        unset($entry);

        return $selected;
    }

    private function findBestTravelPackageForMapDestination(array $destination, array $travelPackages): ?array
    {
        $city = strtolower(trim($this->transliterate((string) ($destination['nom'] ?? ''))));
        $country = strtolower(trim($this->transliterate((string) ($destination['pays'] ?? ''))));
        $continent = strtolower(trim($this->transliterate((string) ($destination['continent'] ?? ''))));
        $fallback = null;

        foreach ($travelPackages as $travelPackage) {
            $packageText = strtolower(trim($this->transliterate((string) (($travelPackage['destinations'] ?? '').' '.($travelPackage['package_name'] ?? '')))));
            if (($city !== '' && str_contains($packageText, $city)) || ($country !== '' && str_contains($packageText, $country))) {
                return $travelPackage;
            }

            if ($fallback === null && $continent !== '' && str_contains(strtolower(trim($this->transliterate((string) ($travelPackage['continent'] ?? '')))), $continent)) {
                $fallback = $travelPackage;
            }
        }

        return $fallback;
    }

    private function buildFallbackMapEntries(): array
    {
        $entries = [
            $this->createFallbackMapEntry('Washington', 'Etats-Unis', 'Amerique', 'Pack Capital Escape', '5 jours / 4 nuits', '2490 EUR', '2890 EUR', '80281906250b49a80467292e998492eb.jpg', 'Une escapade urbaine premium entre monuments iconiques, musees et rooftops confidentiels.', 'Mars a juin', 'Vol, hotel, city pass, transferts', 'Visite guidee de la Maison Blanche', 'Croisiere coucher de soleil sur le Potomac', 'Selection gourmande et shopping', 0.165, 0.313, 91.0),
            $this->createFallbackMapEntry('Bogota', 'Colombie', 'Amerique', 'Pack Andes Panorama', '7 jours / 6 nuits', '1690 EUR', '1990 EUR', 'da89f34fb5595d60358fcefe64fc6659.jpg', 'Un pack vibrant entre art de rue, haute gastronomie et paysages andins a couper le souffle.', 'Decembre a mars', 'Vol, hotel boutique, guide local, excursions', 'Decouverte du quartier La Candelaria', 'Excursion privee a Monserrate', 'Atelier cafe et degustation locale', 0.189, 0.517, 86.0),
            $this->createFallbackMapEntry('Paris', 'France', 'Europe', 'Pack City Lights', '4 jours / 3 nuits', '1890 EUR', '2140 EUR', '3fddde5acc7047afabbb1d9dd69301cd.jpg', 'Le pack ideal pour vivre Paris avec elegance, entre adresses signatures et experiences romantiques.', 'Avril a octobre', 'Hotel 4*, petit-dejeuner, croisiere, transferts', 'Billets coupe-file pour les incontournables', 'Dinner croisiere sur la Seine', 'Guide quartier mode et art de vivre', 0.413, 0.225, 95.0),
            $this->createFallbackMapEntry('Tokyo', 'Japon', 'Asie', 'Pack Neo Tokyo', '8 jours / 7 nuits', '2190 EUR', '2620 EUR', 'bac4bce325c9a10f6fb77f30682cc7fa.jpg', 'Une immersion entre modernite japonaise, temples, quartiers futuristes et experiences food exclusives.', 'Mars a mai', 'Vol, hotel central, JR pass, experiences food', 'Shibuya, Asakusa et teamLab inclus', 'Journee libre a Hakone ou Nikko', 'Selection de restaurants et rooftops', 0.862, 0.325, 93.0),
            $this->createFallbackMapEntry('Sydney', 'Australie', 'Oceanie', 'Pack Harbour Signature', '9 jours / 8 nuits', '3190 EUR', '3690 EUR', 'vaa-720x480-sydney-vivid-sydney-2024-guide.jpg', 'Un grand voyage lifestyle entre baie mythique, plages iconiques et experiences premium au soleil.', 'Septembre a novembre', 'Vol, hotel vue baie, transferts, activites', 'Opera House et harbour cruise', 'Journee a Bondi et Blue Mountains', 'Conciergerie et programme sur mesure', 0.820, 0.734, 88.0),
        ];

        foreach ($entries as $index => &$entry) {
            $entry['display_order'] = $index + 1;
        }
        unset($entry);

        return $entries;
    }

    private function createFallbackMapEntry(
        string $city,
        string $country,
        string $continent,
        string $packageName,
        string $duration,
        string $price,
        string $originalPrice,
        string $imagePath,
        string $description,
        string $bestPeriod,
        string $includes,
        string $highlight1,
        string $highlight2,
        string $highlight3,
        float $xPercent,
        float $yPercent,
        float $aiScore
    ): array {
        return $this->sanitizeMapDestinationEntry([
            'city' => $city,
            'country' => $country,
            'continent' => $continent,
            'package_name' => $packageName,
            'duration' => $duration,
            'price' => $price,
            'original_price' => $originalPrice,
            'image_path' => $imagePath,
            'description' => $description,
            'best_period' => $bestPeriod,
            'includes' => $includes,
            'highlight_1' => $highlight1,
            'highlight_2' => $highlight2,
            'highlight_3' => $highlight3,
            'x_percent' => $xPercent,
            'y_percent' => $yPercent,
            'ai_score' => $aiScore,
            'ai_recommended' => 1,
            'is_active' => 1,
        ]);
    }

    private function findMapDestinationByCityCountry(object $connection, string $city, string $country): ?array
    {
        try {
            $statement = $connection->prepare(
                'SELECT *
                 FROM map_destinations
                 WHERE LOWER(TRIM(city)) = :city AND LOWER(TRIM(country)) = :country
                 LIMIT 1'
            );
            $statement->execute([
                'city' => strtolower(trim($city)),
                'country' => strtolower(trim($country)),
            ]);
            $entry = $statement->fetch();

            return is_array($entry) ? $this->sanitizeMapDestinationEntry($entry) : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function buildMapLookupKey(string $city, string $country): string
    {
        return strtolower(trim($this->transliterate($city))).'|'.strtolower(trim($this->transliterate($country)));
    }

    private function resolveMapCoordinates(string $city, string $continent, int $seed): array
    {
        $normalizedCity = strtolower(trim($this->transliterate($city)));
        if (str_contains($normalizedCity, 'washington') || str_contains($normalizedCity, 'new york')) {
            return [0.165, 0.313];
        }
        if (str_contains($normalizedCity, 'bogota')) {
            return [0.189, 0.517];
        }
        if (str_contains($normalizedCity, 'paris')) {
            return [0.413, 0.225];
        }
        if (str_contains($normalizedCity, 'tokyo')) {
            return [0.862, 0.325];
        }
        if (str_contains($normalizedCity, 'sydney')) {
            return [0.820, 0.734];
        }

        $base = match (strtolower(trim($continent))) {
            'amerique' => [0.180, 0.360],
            'europe' => [0.430, 0.230],
            'afrique' => [0.500, 0.520],
            'asie' => [0.790, 0.320],
            'oceanie' => [0.830, 0.720],
            default => [0.500, 0.400],
        };

        $xOffset = (($seed % 5) - 2) * 0.018;
        $yOffset = (((int) floor($seed / 5) % 5) - 2) * 0.018;

        return [
            $this->clamp((float) $base[0] + $xOffset, 0.05, 0.95),
            $this->clamp((float) $base[1] + $yOffset, 0.10, 0.90),
        ];
    }

    private function inferMapContinentFromPoint(float $xPercent, float $yPercent): string
    {
        if ($xPercent < 0.28) {
            return 'Amerique';
        }
        if ($xPercent < 0.55) {
            return $yPercent > 0.38 ? 'Afrique' : 'Europe';
        }
        if ($xPercent > 0.74 && $yPercent > 0.60) {
            return 'Oceanie';
        }

        return 'Asie';
    }

    private function buildMapDestinationDescription(array $destination): string
    {
        return trim((string) ($destination['nom'] ?? 'Destination')).' devient un hotspot premium pour la carte interactive, avec un bon equilibre entre desirabilite, budget et satisfaction.';
    }

    private function inferMapBestPeriod(string $continent): string
    {
        return match (strtolower(trim($continent))) {
            'europe' => 'Avril a octobre',
            'asie' => 'Mars a mai',
            'afrique' => 'Juin a octobre',
            'amerique' => 'Septembre a novembre',
            'oceanie' => 'Septembre a novembre',
            default => 'Toute l annee',
        };
    }

    private function resolveMapImagePath(string $destinationLike, string $continent): string
    {
        $normalized = strtolower(trim($this->transliterate($destinationLike)));
        if (str_contains($normalized, 'paris')) {
            return '3fddde5acc7047afabbb1d9dd69301cd.jpg';
        }
        if (str_contains($normalized, 'tokyo')) {
            return 'bac4bce325c9a10f6fb77f30682cc7fa.jpg';
        }
        if (str_contains($normalized, 'bogota') || str_contains($normalized, 'vietnam') || str_contains($normalized, 'thailande')) {
            return 'da89f34fb5595d60358fcefe64fc6659.jpg';
        }
        if (str_contains($normalized, 'washington') || str_contains($normalized, 'usa')) {
            return '80281906250b49a80467292e998492eb.jpg';
        }
        if (str_contains($normalized, 'sydney') || str_contains($normalized, 'australie')) {
            return 'vaa-720x480-sydney-vivid-sydney-2024-guide.jpg';
        }

        return match (strtolower(trim($continent))) {
            'asie' => 'bac4bce325c9a10f6fb77f30682cc7fa.jpg',
            'amerique' => '80281906250b49a80467292e998492eb.jpg',
            'oceanie' => 'vaa-720x480-sydney-vivid-sydney-2024-guide.jpg',
            default => '3fddde5acc7047afabbb1d9dd69301cd.jpg',
        };
    }

    private function formatMapPrice(float $amount): string
    {
        return number_format(max(0.0, $amount), 0, '.', '').' EUR';
    }

    private function ensureMapDestinationSchema(): void
    {
        if ($this->mapSchemaEnsured) {
            return;
        }

        $connection = $this->connectionFactory->getConnection();
        try {
            $connection->exec(
                'CREATE TABLE IF NOT EXISTS map_destinations (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    city VARCHAR(100) NULL,
                    country VARCHAR(100) NULL,
                    continent VARCHAR(50) NULL,
                    package_name VARCHAR(150) NULL,
                    duration VARCHAR(50) NULL,
                    price VARCHAR(50) NULL,
                    original_price VARCHAR(50) NULL,
                    image_path VARCHAR(255) NULL,
                    description TEXT NULL,
                    best_period VARCHAR(100) NULL,
                    includes TEXT NULL,
                    highlight_1 TEXT NULL,
                    highlight_2 TEXT NULL,
                    highlight_3 TEXT NULL,
                    x_percent DECIMAL(5,3) NULL,
                    y_percent DECIMAL(5,3) NULL,
                    ai_score DECIMAL(5,2) DEFAULT 0,
                    ai_recommended TINYINT(1) DEFAULT 0,
                    is_active TINYINT(1) DEFAULT 1,
                    display_order INT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )'
            );
            $this->seedMapDestinationsIfEmpty($connection);
            $this->mapSchemaEnsured = true;
        } catch (Throwable) {
        }
    }

    private function seedMapDestinationsIfEmpty(object $connection): void
    {
        try {
            $count = (int) ($connection->query('SELECT COUNT(*) FROM map_destinations')->fetchColumn() ?: 0);
            if ($count > 0) {
                return;
            }

            foreach ($this->buildFallbackMapEntries() as $entry) {
                $this->insertMapDestinationEntry($connection, $entry);
            }
        } catch (Throwable) {
        }
    }

    private function loadAtmosphereById(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $this->ensureAtmosphereSchema();

        try {
            $statement = $this->connectionFactory->getConnection()->prepare('SELECT * FROM atmosphere_destinations WHERE id = :id LIMIT 1');
            $statement->execute(['id' => $id]);
            $entry = $statement->fetch();

            return is_array($entry) ? $this->sanitizeAtmosphereEntry($entry) : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function insertAtmosphereEntry(object $connection, array $entry): int
    {
        $statement = $connection->prepare(
            'INSERT INTO atmosphere_destinations
             (atmosphere_type, title, description, video_path, ai_interest_tags, ai_suggested_destinations, ai_suggested_countries, ai_suggested_continents, ai_featured_payload, ai_score, avg_price, is_active, display_order, created_by_admin, updated_from_ai_at)
             VALUES (:atmosphere_type, :title, :description, :video_path, :ai_interest_tags, :ai_suggested_destinations, :ai_suggested_countries, :ai_suggested_continents, :ai_featured_payload, :ai_score, :avg_price, :is_active, :display_order, :created_by_admin, :updated_from_ai_at)'
        );
        $statement->execute($this->normalizeAtmospherePayload($entry));

        return (int) $connection->lastInsertId();
    }

    private function updateAtmosphereEntry(object $connection, int $id, array $entry): bool
    {
        $statement = $connection->prepare(
            'UPDATE atmosphere_destinations
             SET atmosphere_type = :atmosphere_type,
                 title = :title,
                 description = :description,
                 video_path = :video_path,
                 ai_interest_tags = :ai_interest_tags,
                 ai_suggested_destinations = :ai_suggested_destinations,
                 ai_suggested_countries = :ai_suggested_countries,
                 ai_suggested_continents = :ai_suggested_continents,
                 ai_featured_payload = :ai_featured_payload,
                 ai_score = :ai_score,
                 avg_price = :avg_price,
                 is_active = :is_active,
                 display_order = :display_order,
                 created_by_admin = :created_by_admin,
                 updated_from_ai_at = :updated_from_ai_at
             WHERE id = :id'
        );

        return $statement->execute([
            ...$this->normalizeAtmospherePayload($entry),
            'id' => $id,
        ]);
    }

    private function updateAtmosphereDisplayOrder(object $connection, int $id, int $displayOrder): bool
    {
        if ($id <= 0) {
            return false;
        }

        $statement = $connection->prepare(
            'UPDATE atmosphere_destinations SET display_order = :display_order WHERE id = :id'
        );

        return $statement->execute([
            'display_order' => max(1, $displayOrder),
            'id' => $id,
        ]);
    }

    private function normalizeAtmosphereDisplayOrder(object $connection): void
    {
        try {
            $entries = $connection->query(
                'SELECT id
                 FROM atmosphere_destinations
                 ORDER BY CASE WHEN COALESCE(display_order, 0) <= 0 THEN 999999 ELSE display_order END ASC, id ASC'
            )->fetchAll();
        } catch (Throwable) {
            return;
        }

        $statement = $connection->prepare('UPDATE atmosphere_destinations SET display_order = :display_order WHERE id = :id');
        foreach ($entries as $index => $entry) {
            $statement->execute([
                'display_order' => $index + 1,
                'id' => (int) ($entry['id'] ?? 0),
            ]);
        }
    }

    private function normalizeAtmospherePayload(array $entry): array
    {
        $sanitized = $this->sanitizeAtmosphereEntry($entry);
        $updatedFromAiAt = trim((string) ($sanitized['updated_from_ai_at'] ?? ''));

        return [
            'atmosphere_type' => trim((string) ($sanitized['atmosphere_type'] ?? 'ATMOSPHERE')),
            'title' => trim((string) ($sanitized['title'] ?? '')),
            'description' => trim((string) ($sanitized['description'] ?? '')),
            'video_path' => trim((string) ($sanitized['video_path'] ?? '')),
            'ai_interest_tags' => trim((string) ($sanitized['ai_interest_tags'] ?? '')),
            'ai_suggested_destinations' => trim((string) ($sanitized['ai_suggested_destinations'] ?? '')),
            'ai_suggested_countries' => trim((string) ($sanitized['ai_suggested_countries'] ?? '')),
            'ai_suggested_continents' => trim((string) ($sanitized['ai_suggested_continents'] ?? '')),
            'ai_featured_payload' => trim((string) ($sanitized['ai_featured_payload'] ?? '')),
            'ai_score' => $this->clamp($this->parseNumericValue($sanitized['ai_score'] ?? 0.0), 0.0, 99.0),
            'avg_price' => max(0.0, $this->parseNumericValue($sanitized['avg_price'] ?? 0.0)),
            'is_active' => !empty($sanitized['is_active']) ? 1 : 0,
            'display_order' => max(1, (int) ($sanitized['display_order'] ?? 1)),
            'created_by_admin' => max(0, (int) ($sanitized['created_by_admin'] ?? 0)),
            'updated_from_ai_at' => $updatedFromAiAt !== '' ? $updatedFromAiAt : null,
        ];
    }

    private function sanitizeAtmosphereEntry(array $entry): array
    {
        $type = strtoupper(trim((string) ($entry['atmosphere_type'] ?? 'ATMOSPHERE')));
        $seed = $this->resolveAtmosphereSeed($type);

        $title = trim((string) ($entry['title'] ?? ''));
        if ($title === '') {
            $title = (string) ($seed['title'] ?? $type);
        }

        $description = trim((string) ($entry['description'] ?? ''));
        if ($description === '') {
            $description = (string) ($seed['description'] ?? '');
        }

        $videoPath = trim((string) ($entry['video_path'] ?? ''));
        if ($videoPath === '') {
            $videoPath = (string) ($seed['video_path'] ?? '');
        }

        $tags = trim((string) ($entry['ai_interest_tags'] ?? ''));
        if ($tags === '') {
            $tags = (string) ($seed['ai_interest_tags'] ?? '');
        }

        return [
            ...$entry,
            'atmosphere_type' => $type,
            'title' => $title,
            'description' => $description,
            'video_path' => $videoPath,
            'ai_interest_tags' => $tags,
            'ai_suggested_destinations' => trim((string) ($entry['ai_suggested_destinations'] ?? '')),
            'ai_suggested_countries' => trim((string) ($entry['ai_suggested_countries'] ?? '')),
            'ai_suggested_continents' => trim((string) ($entry['ai_suggested_continents'] ?? '')),
            'ai_featured_payload' => trim((string) ($entry['ai_featured_payload'] ?? '')),
            'ai_score' => $this->clamp($this->parseNumericValue($entry['ai_score'] ?? 0.0, 0.0), 0.0, 99.0),
            'avg_price' => max(0.0, $this->parseNumericValue($entry['avg_price'] ?? 0.0, 0.0)),
            'is_active' => array_key_exists('is_active', $entry) ? !empty($entry['is_active']) : true,
            'display_order' => max(1, (int) round($this->parseNumericValue($entry['display_order'] ?? ($seed['display_order'] ?? 1), (float) ($seed['display_order'] ?? 1)))),
            'created_by_admin' => max(0, (int) ($entry['created_by_admin'] ?? 0)),
            'updated_from_ai_at' => trim((string) ($entry['updated_from_ai_at'] ?? '')),
        ];
    }

    private function enrichAtmosphereWithLocalSuggestions(array $entry, array $destinations): array
    {
        $sanitizedEntry = $this->sanitizeAtmosphereEntry($entry);
        $type = strtoupper(trim((string) ($sanitizedEntry['atmosphere_type'] ?? 'ATMOSPHERE')));
        $interests = $this->resolveAtmosphereInterestTags($sanitizedEntry);
        $preferredContinents = $this->resolveAtmospherePreferredContinents($type);
        $rankedSuggestions = $this->buildRankedAtmosphereSuggestions($interests, $preferredContinents, $destinations);
        $suggestions = $rankedSuggestions !== []
            ? array_slice($rankedSuggestions, 0, self::ATMOSPHERE_AI_LIMIT)
            : $this->buildAtmosphereFallbackSuggestions($type);

        $suggestionNames = [];
        $suggestionCountries = [];
        $suggestionContinents = [];
        $totalPrice = 0.0;
        $priceCount = 0;
        foreach ($suggestions as $suggestion) {
            $destinationName = trim((string) ($suggestion['destination'] ?? ''));
            if ($destinationName !== '') {
                $suggestionNames[] = $destinationName;
            }
            $country = trim((string) ($suggestion['country'] ?? ''));
            if ($country !== '') {
                $suggestionCountries[] = $country;
            }
            $continent = trim((string) ($suggestion['continent'] ?? ''));
            if ($continent !== '') {
                $suggestionContinents[] = $continent;
            }
            $price = max(0.0, $this->parseNumericValue($suggestion['price'] ?? 0.0, 0.0));
            if ($price > 0.0) {
                $totalPrice += $price;
                $priceCount++;
            }
        }

        $uniqueDestinations = array_values(array_unique(array_filter($suggestionNames, static fn (string $value): bool => trim($value) !== '')));
        $uniqueCountries = array_values(array_unique(array_filter(
            $suggestionCountries,
            static fn (string $value): bool => trim($value) !== ''
        )));
        $uniqueContinents = array_values(array_unique(array_filter(
            $suggestionContinents,
            static fn (string $value): bool => trim($value) !== ''
        )));
        $typeBoost = match ($type) {
            'PLAGE' => 15.0,
            'SAFARI' => 13.0,
            'MONTAGNE' => 11.0,
            default => 10.0,
        };
        $matchBoost = 0.0;
        if ($rankedSuggestions !== []) {
            foreach ($rankedSuggestions as $suggestion) {
                $matchBoost += max(0.0, (float) ($suggestion['match_score'] ?? 0.0));
            }
            $matchBoost = min(10.0, $matchBoost / max(1.0, count($rankedSuggestions)));
        }
        $aiScore = $uniqueDestinations === []
            ? 0.0
            : round($this->clamp(68.0 + (count($uniqueDestinations) * 2.4) + $typeBoost + $matchBoost, 72.0, 96.0), 1);

        return $this->sanitizeAtmosphereEntry([
            ...$sanitizedEntry,
            'title' => trim((string) ($sanitizedEntry['title'] ?? '')) !== ''
                ? trim((string) ($sanitizedEntry['title'] ?? ''))
                : $type,
            'description' => $this->buildAtmosphereDescription($type, $uniqueDestinations),
            'ai_interest_tags' => implode(',', $interests),
            'ai_suggested_destinations' => implode(', ', $uniqueDestinations),
            'ai_suggested_countries' => implode(', ', $uniqueCountries),
            'ai_suggested_continents' => implode(', ', $uniqueContinents),
            'ai_featured_payload' => $this->buildAtmosphereAiPayload($suggestions, $interests),
            'ai_score' => $aiScore,
            'avg_price' => $priceCount > 0 ? $totalPrice / $priceCount : 0.0,
            'updated_from_ai_at' => (new DateTimeImmutable('now'))->format('Y-m-d H:i:s'),
        ]);
    }

    private function buildRankedAtmosphereSuggestions(array $interests, array $preferredContinents, array $destinations): array
    {
        $ranked = [];
        foreach ($destinations as $destination) {
            $destinationName = trim((string) ($destination['nom'] ?? ''));
            if ($destinationName === '') {
                continue;
            }

            $haystack = $this->normalizeFeaturedValue(
                (string) ($destination['nom'] ?? '')
                .' '.(string) ($destination['pays'] ?? '')
                .' '.(string) ($destination['continent'] ?? '')
                .' '.(string) ($destination['description'] ?? '')
            );

            $score = 0.0;
            foreach ($interests as $interest) {
                if ($interest !== '' && str_contains($haystack, $interest)) {
                    $score += 4.0;
                }

                foreach ($this->keywordsForAtmosphereTag($interest) as $keyword) {
                    if ($keyword !== '' && str_contains($haystack, $keyword)) {
                        $score += 2.0;
                    }
                }
            }

            $continent = trim((string) ($destination['continent'] ?? ''));
            if ($continent !== '' && in_array($this->normalizeFeaturedValue($continent), $preferredContinents, true)) {
                $score += 3.0;
            }

            if ($score <= 0.0) {
                continue;
            }

            $ranked[] = [
                'destination' => $destinationName,
                'country' => trim((string) ($destination['pays'] ?? '')),
                'continent' => $continent,
                'price' => max(0.0, $this->parseNumericValue($destination['prix_base'] ?? 0.0, 0.0)),
                'description' => trim((string) ($destination['description'] ?? '')),
                'match_score' => $score,
            ];
        }

        usort($ranked, static function (array $left, array $right): int {
            $scoreCompare = ((float) ($right['match_score'] ?? 0.0)) <=> ((float) ($left['match_score'] ?? 0.0));
            if ($scoreCompare !== 0) {
                return $scoreCompare;
            }

            return strcmp(
                (string) ($left['destination'] ?? ''),
                (string) ($right['destination'] ?? '')
            );
        });

        return $ranked;
    }

    private function buildAtmosphereFallbackSuggestions(string $type): array
    {
        return match ($type) {
            'SAFARI' => [
                ['destination' => 'Addis Ababa', 'country' => 'Ethiopie', 'continent' => 'Afrique', 'price' => 2350.0, 'description' => 'Immersion safari et lodge premium.', 'match_score' => 6.0],
                ['destination' => 'Nairobi', 'country' => 'Kenya', 'continent' => 'Afrique', 'price' => 2680.0, 'description' => 'Escapade nature entre reserves et panoramas sauvages.', 'match_score' => 5.0],
                ['destination' => 'Bali', 'country' => 'Indonesie', 'continent' => 'Asie', 'price' => 2140.0, 'description' => 'Parenthese exotique entre jungle et experiences premium.', 'match_score' => 4.0],
            ],
            'URBAIN' => [
                ['destination' => 'Tokyo', 'country' => 'Japon', 'continent' => 'Asie', 'price' => 2490.0, 'description' => 'Capitale vibrante, design et experiences signatures.', 'match_score' => 6.0],
                ['destination' => 'Paris', 'country' => 'France', 'continent' => 'Europe', 'price' => 1890.0, 'description' => 'City break premium entre adresses iconiques et art de vivre.', 'match_score' => 5.0],
                ['destination' => 'New York', 'country' => 'Etats-Unis', 'continent' => 'Amerique', 'price' => 2790.0, 'description' => 'Skyline, shopping et rooftops confidentiels.', 'match_score' => 4.0],
            ],
            'PLAGE' => [
                ['destination' => 'Bali', 'country' => 'Indonesie', 'continent' => 'Asie', 'price' => 2140.0, 'description' => 'Plages signatures et villas tropicales.', 'match_score' => 6.0],
                ['destination' => 'Maldives', 'country' => 'Maldives', 'continent' => 'Asie', 'price' => 3590.0, 'description' => 'Lagons cristallins et resorts premium.', 'match_score' => 5.0],
                ['destination' => 'Santorini', 'country' => 'Grece', 'continent' => 'Europe', 'price' => 2390.0, 'description' => 'Mer turquoise, sunsets et adresses lifestyle.', 'match_score' => 4.0],
            ],
            'MONTAGNE' => [
                ['destination' => 'Islande', 'country' => 'Islande', 'continent' => 'Europe', 'price' => 2690.0, 'description' => 'Road trip grand air et paysages volcaniques.', 'match_score' => 6.0],
                ['destination' => 'Chamonix', 'country' => 'France', 'continent' => 'Europe', 'price' => 2190.0, 'description' => 'Montagnes iconiques et sejour alpin premium.', 'match_score' => 5.0],
                ['destination' => 'Kenya', 'country' => 'Kenya', 'continent' => 'Afrique', 'price' => 2410.0, 'description' => 'Sommets, nature et aventure sur mesure.', 'match_score' => 4.0],
            ],
            default => [],
        };
    }

    private function resolveAtmosphereInterestTags(array $entry): array
    {
        $rawTags = trim((string) ($entry['ai_interest_tags'] ?? ''));
        if ($rawTags === '') {
            $rawTags = (string) ($this->resolveAtmosphereSeed((string) ($entry['atmosphere_type'] ?? 'ATMOSPHERE'))['ai_interest_tags'] ?? '');
        }

        $tags = [];
        foreach (preg_split('/[\s,;]+/', $rawTags) ?: [] as $tag) {
            $normalized = $this->normalizeFeaturedValue($tag);
            if ($normalized !== '' && !in_array($normalized, $tags, true)) {
                $tags[] = $normalized;
            }
        }

        return $tags;
    }

    private function resolveAtmospherePreferredContinents(string $type): array
    {
        return match (strtoupper(trim($type))) {
            'SAFARI' => ['afrique', 'asie'],
            'URBAIN' => ['europe', 'amerique', 'asie'],
            'PLAGE' => ['asie', 'oceanie', 'afrique', 'europe'],
            'MONTAGNE' => ['europe', 'asie', 'amerique', 'afrique'],
            default => [],
        };
    }

    private function keywordsForAtmosphereTag(string $tag): array
    {
        return match ($tag) {
            'aventure' => ['trek', 'randonnee', 'exploration', 'aventure'],
            'nature' => ['nature', 'parc', 'foret', 'sauvage', 'montagne'],
            'safari' => ['safari', 'reserve', 'faune', 'savane'],
            'culture' => ['culture', 'musee', 'historique', 'art', 'patrimoine'],
            'shopping' => ['shopping', 'boutique', 'mode', 'luxe'],
            'city', 'urbain' => ['ville', 'urbain', 'metropole', 'rooftop', 'capital'],
            'plage' => ['plage', 'lagon', 'mer', 'cote', 'balneaire'],
            'detente' => ['spa', 'detente', 'relax', 'bien-etre', 'douceur'],
            'luxe' => ['luxe', 'premium', 'haut de gamme', 'signature'],
            'montagne' => ['montagne', 'alpin', 'sommet', 'neige', 'volcan'],
            default => [$tag],
        };
    }

    private function buildAtmosphereAiPayload(array $suggestions, array $interests): string
    {
        $payload = array_map(static function (array $suggestion) use ($interests): array {
            return [
                'destination' => trim((string) ($suggestion['destination'] ?? '')),
                'pays' => trim((string) ($suggestion['country'] ?? '')),
                'continent' => trim((string) ($suggestion['continent'] ?? '')),
                'prix_total' => max(0.0, (float) ($suggestion['price'] ?? 0.0)),
                'prix_par_personne' => max(0.0, (float) ($suggestion['price'] ?? 0.0)),
                'duree' => 7,
                'description' => trim((string) ($suggestion['description'] ?? '')),
                'interets' => $interests,
            ];
        }, $suggestions);

        return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]';
    }

    private function buildAtmosphereDescription(string $type, array $destinations): string
    {
        $topDestinations = array_slice(array_values(array_filter($destinations, static fn (string $value): bool => trim($value) !== '')), 0, 3);
        if ($topDestinations === []) {
            return $this->defaultAtmosphereDescription($type);
        }

        return match (strtoupper(trim($type))) {
            'SAFARI' => 'Ambiance aventure et nature inspiree par '.implode(', ', $topDestinations).'.',
            'URBAIN' => 'Ambiance urbaine inspiree par '.implode(', ', $topDestinations).'.',
            'PLAGE' => 'Ambiance balneaire inspiree par '.implode(', ', $topDestinations).'.',
            'MONTAGNE' => 'Ambiance grand air inspiree par '.implode(', ', $topDestinations).'.',
            default => 'Atmosphere voyage inspiree par '.implode(', ', $topDestinations).'.',
        };
    }

    private function defaultAtmosphereDescription(string $type): string
    {
        return match (strtoupper(trim($type))) {
            'SAFARI' => 'Ambiance aventure et nature inspiree par Bali, Addis Ababa, Islande.',
            'URBAIN' => 'Ambiance urbaine inspiree par Tokyo, Paris, New York.',
            'PLAGE' => 'Ambiance balneaire inspiree par Bali, Maldives, Santorini.',
            'MONTAGNE' => 'Ambiance grand air inspiree par Bali, Islande, Kenya.',
            default => 'Atmosphere EasyTravel connectee a la Home.',
        };
    }

    private function resolveAtmosphereSeed(string $type): array
    {
        return match (strtoupper(trim($type))) {
            'SAFARI' => [
                'title' => 'SAFARI',
                'description' => 'Ambiance aventure et nature inspiree par Bali, Addis Ababa, Islande.',
                'video_path' => 'Safari in Africa.mp4',
                'ai_interest_tags' => 'aventure,nature,safari',
                'display_order' => 1,
            ],
            'URBAIN' => [
                'title' => 'URBAIN',
                'description' => 'Ambiance urbaine inspiree par Tokyo, Paris, New York.',
                'video_path' => 'Sky2Tours.mp4',
                'ai_interest_tags' => 'culture,shopping,city',
                'display_order' => 2,
            ],
            'PLAGE' => [
                'title' => 'PLAGE',
                'description' => 'Ambiance balneaire inspiree par Bali, Maldives, Santorini.',
                'video_path' => 'Ney Pereira.mp4',
                'ai_interest_tags' => 'plage,detente,luxe',
                'display_order' => 3,
            ],
            'MONTAGNE' => [
                'title' => 'MONTAGNE',
                'description' => 'Ambiance grand air inspiree par Bali, Islande, Kenya.',
                'video_path' => 'Anna M..mp4',
                'ai_interest_tags' => 'aventure,nature,montagne',
                'display_order' => 4,
            ],
            default => [
                'title' => strtoupper(trim($type)) !== '' ? strtoupper(trim($type)) : 'ATMOSPHERE',
                'description' => 'Atmosphere EasyTravel connectee a la Home.',
                'video_path' => '',
                'ai_interest_tags' => 'culture,nature',
                'display_order' => 1,
            ],
        };
    }

    private function buildAtmosphereSeedEntries(): array
    {
        $types = ['SAFARI', 'URBAIN', 'PLAGE', 'MONTAGNE'];
        $entries = [];
        foreach ($types as $index => $type) {
            $seed = $this->resolveAtmosphereSeed($type);
            $entries[] = $this->sanitizeAtmosphereEntry([
                'atmosphere_type' => $type,
                'title' => $seed['title'],
                'description' => $seed['description'],
                'video_path' => $seed['video_path'],
                'ai_interest_tags' => $seed['ai_interest_tags'],
                'is_active' => 1,
                'display_order' => $index + 1,
                'created_by_admin' => 0,
            ]);
        }

        return $entries;
    }

    private function ensureFeaturedDestinationSchema(): void
    {
        if ($this->featuredDestinationSchemaEnsured) {
            return;
        }

        $connection = $this->connectionFactory->getConnection();
        try {
            $connection->exec(
                'CREATE TABLE IF NOT EXISTS featured_destinations (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    destination_name VARCHAR(100) NULL,
                    country VARCHAR(100) NULL,
                    continent VARCHAR(50) NULL,
                    description TEXT NULL,
                    video_path VARCHAR(255) NULL,
                    ai_score DECIMAL(5,2) DEFAULT 0,
                    satisfaction_score DECIMAL(3,2) DEFAULT 0,
                    avg_price DECIMAL(10,2) DEFAULT 0,
                    best_season VARCHAR(100) NULL,
                    travel_types TEXT NULL,
                    interests TEXT NULL,
                    is_featured TINYINT(1) DEFAULT 1,
                    display_order INT NULL,
                    updated_from_ai_at TIMESTAMP NULL DEFAULT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )'
            );
            $connection->exec(
                'CREATE TABLE IF NOT EXISTS featured_destination_history (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    featured_destination_id INT DEFAULT 0,
                    action_type VARCHAR(40) NULL,
                    destination_name VARCHAR(120) NULL,
                    ai_score DOUBLE DEFAULT 0,
                    note TEXT NULL,
                    created_by_admin INT DEFAULT 0,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )'
            );

            $this->addFeaturedDestinationColumnIfMissing($connection, 'destination_name', 'ALTER TABLE featured_destinations ADD COLUMN destination_name VARCHAR(100) NULL AFTER id');
            $this->addFeaturedDestinationColumnIfMissing($connection, 'country', 'ALTER TABLE featured_destinations ADD COLUMN country VARCHAR(100) NULL AFTER destination_name');
            $this->addFeaturedDestinationColumnIfMissing($connection, 'continent', 'ALTER TABLE featured_destinations ADD COLUMN continent VARCHAR(50) NULL AFTER country');
            $this->addFeaturedDestinationColumnIfMissing($connection, 'description', 'ALTER TABLE featured_destinations ADD COLUMN description TEXT NULL AFTER continent');
            $this->addFeaturedDestinationColumnIfMissing($connection, 'video_path', 'ALTER TABLE featured_destinations ADD COLUMN video_path VARCHAR(255) NULL AFTER description');
            $this->addFeaturedDestinationColumnIfMissing($connection, 'ai_score', 'ALTER TABLE featured_destinations ADD COLUMN ai_score DECIMAL(5,2) DEFAULT 0 AFTER video_path');
            $this->addFeaturedDestinationColumnIfMissing($connection, 'satisfaction_score', 'ALTER TABLE featured_destinations ADD COLUMN satisfaction_score DECIMAL(3,2) DEFAULT 0 AFTER ai_score');
            $this->addFeaturedDestinationColumnIfMissing($connection, 'avg_price', 'ALTER TABLE featured_destinations ADD COLUMN avg_price DECIMAL(10,2) DEFAULT 0 AFTER satisfaction_score');
            $this->addFeaturedDestinationColumnIfMissing($connection, 'best_season', 'ALTER TABLE featured_destinations ADD COLUMN best_season VARCHAR(100) NULL AFTER avg_price');
            $this->addFeaturedDestinationColumnIfMissing($connection, 'travel_types', 'ALTER TABLE featured_destinations ADD COLUMN travel_types TEXT NULL AFTER best_season');
            $this->addFeaturedDestinationColumnIfMissing($connection, 'interests', 'ALTER TABLE featured_destinations ADD COLUMN interests TEXT NULL AFTER travel_types');
            $this->addFeaturedDestinationColumnIfMissing($connection, 'is_featured', 'ALTER TABLE featured_destinations ADD COLUMN is_featured TINYINT(1) DEFAULT 1 AFTER interests');
            $this->addFeaturedDestinationColumnIfMissing($connection, 'display_order', 'ALTER TABLE featured_destinations ADD COLUMN display_order INT NULL AFTER is_featured');
            $this->addFeaturedDestinationColumnIfMissing($connection, 'updated_from_ai_at', 'ALTER TABLE featured_destinations ADD COLUMN updated_from_ai_at TIMESTAMP NULL DEFAULT NULL AFTER display_order');

            $this->migrateFeaturedDestinationLegacyData($connection);
            $this->seedFeaturedDestinationDefaults($connection);
            $this->normalizeFeaturedDestinationDisplayOrder($connection);
            $this->featuredDestinationSchemaEnsured = true;
        } catch (Throwable) {
        }
    }

    private function migrateFeaturedDestinationLegacyData(object $connection): void
    {
        try {
            if ($this->featuredDestinationColumnExists($connection, 'name')) {
                $connection->exec(
                    "UPDATE featured_destinations
                     SET destination_name = name
                     WHERE (destination_name IS NULL OR destination_name = '') AND name IS NOT NULL AND name <> ''"
                );
            }

            if ($this->featuredDestinationColumnExists($connection, 'price_base')) {
                $connection->exec(
                    'UPDATE featured_destinations
                     SET avg_price = price_base
                     WHERE (avg_price IS NULL OR avg_price = 0) AND price_base IS NOT NULL'
                );
            }

            if ($this->featuredDestinationColumnExists($connection, 'is_active')) {
                $connection->exec('UPDATE featured_destinations SET is_featured = is_active');
            }
        } catch (Throwable) {
        }
    }

    private function seedFeaturedDestinationDefaults(object $connection): void
    {
        try {
            $count = (int) ($connection->query('SELECT COUNT(*) FROM featured_destinations')->fetchColumn() ?: 0);
            if ($count >= self::FEATURED_SELECTION_LIMIT) {
                return;
            }

            $destinationsById = $this->loadDestinationsById();
            $suggestions = $destinationsById !== []
                ? $this->buildFeaturedSuggestionsFromReservations(
                    $this->fetchAllOrEmpty('SELECT * FROM packages ORDER BY date_reservation DESC'),
                    $destinationsById,
                    self::FEATURED_SELECTION_LIMIT
                )
                : [];
            if ($suggestions === []) {
                $suggestions = $this->buildFallbackFeaturedDestinationEntries();
            }

            $existingRows = $connection->query('SELECT destination_name, country FROM featured_destinations')->fetchAll();
            $existingKeys = [];
            foreach ($existingRows as $row) {
                $existingKeys[$this->buildFeaturedDestinationKey((string) ($row['destination_name'] ?? ''), (string) ($row['country'] ?? ''))] = true;
            }

            $nextOrder = $count + 1;
            foreach ($suggestions as $entry) {
                if ($nextOrder > self::FEATURED_SELECTION_LIMIT) {
                    break;
                }

                $key = $this->buildFeaturedDestinationKey((string) ($entry['destination_name'] ?? ''), (string) ($entry['country'] ?? ''));
                if (isset($existingKeys[$key])) {
                    continue;
                }

                $entry['display_order'] = $nextOrder++;
                $entry['is_featured'] = 1;
                $this->insertFeaturedDestinationEntry($connection, $entry);
                $existingKeys[$key] = true;
            }
        } catch (Throwable) {
        }
    }

    private function normalizeFeaturedDestinationDisplayOrder(object $connection): void
    {
        try {
            $entries = $connection->query(
                'SELECT id
                 FROM featured_destinations
                 ORDER BY CASE WHEN COALESCE(display_order, 0) <= 0 THEN 999999 ELSE display_order END ASC, id ASC'
            )->fetchAll();
            $statement = $connection->prepare('UPDATE featured_destinations SET display_order = :display_order WHERE id = :id');
            foreach ($entries as $index => $entry) {
                $statement->execute([
                    'display_order' => $index + 1,
                    'id' => (int) ($entry['id'] ?? 0),
                ]);
            }
        } catch (Throwable) {
        }
    }

    private function addFeaturedDestinationColumnIfMissing(object $connection, string $columnName, string $sql): void
    {
        if ($this->featuredDestinationColumnExists($connection, $columnName)) {
            return;
        }

        $connection->exec($sql);
    }

    private function featuredDestinationColumnExists(object $connection, string $columnName): bool
    {
        try {
            $statement = $connection->prepare('SHOW COLUMNS FROM featured_destinations LIKE :columnName');
            $statement->execute(['columnName' => $columnName]);

            return (bool) $statement->fetch();
        } catch (Throwable) {
            return false;
        }
    }

    private function buildFallbackFeaturedDestinationEntries(): array
    {
        return [
            $this->normalizeFeaturedEntryPayload([
                'destination_name' => 'Paris',
                'country' => 'France',
                'continent' => 'Europe',
                'description' => 'Escapade iconique entre culture, mode et city break premium.',
                'video_path' => 'An Qiang.mp4',
                'ai_score' => 92.0,
                'satisfaction_score' => 4.82,
                'avg_price' => 1200.0,
                'best_season' => 'Avril - Octobre',
                'travel_types' => 'couple,solo,famille',
                'interests' => 'culture,shopping,gastronomie',
                'is_featured' => 1,
                'display_order' => 1,
            ]),
            $this->normalizeFeaturedEntryPayload([
                'destination_name' => 'Tokyo',
                'country' => 'Japon',
                'continent' => 'Asie',
                'description' => 'Metropole ultra-desirable pour voyageurs urbains, foodies et amateurs de contraste.',
                'video_path' => 'M.mp4',
                'ai_score' => 89.0,
                'satisfaction_score' => 4.76,
                'avg_price' => 1650.0,
                'best_season' => 'Mars - Mai',
                'travel_types' => 'solo,couple,famille',
                'interests' => 'culture,gastronomie,urbain',
                'is_featured' => 1,
                'display_order' => 2,
            ]),
            $this->normalizeFeaturedEntryPayload([
                'destination_name' => 'Bali',
                'country' => 'Indonesie',
                'continent' => 'Asie',
                'description' => 'Destination detente et plage avec forte demande pour couples et familles.',
                'video_path' => 'Luke Cameron.mp4',
                'ai_score' => 86.0,
                'satisfaction_score' => 4.74,
                'avg_price' => 980.0,
                'best_season' => 'Avril - Octobre',
                'travel_types' => 'couple,famille,solo',
                'interests' => 'plage,bien-etre,nature',
                'is_featured' => 1,
                'display_order' => 3,
            ]),
            $this->normalizeFeaturedEntryPayload([
                'destination_name' => 'New York',
                'country' => 'USA',
                'continent' => 'Amerique',
                'description' => 'Grande ville a fort attrait shopping, culture et sejour premium.',
                'video_path' => 'Marina.NewyorkCity.mp4',
                'ai_score' => 84.0,
                'satisfaction_score' => 4.67,
                'avg_price' => 1780.0,
                'best_season' => 'Septembre - Novembre',
                'travel_types' => 'solo,couple,business',
                'interests' => 'urbain,shopping,culture',
                'is_featured' => 1,
                'display_order' => 4,
            ]),
            $this->normalizeFeaturedEntryPayload([
                'destination_name' => 'Marrakech',
                'country' => 'Maroc',
                'continent' => 'Afrique',
                'description' => 'Destination sensorielle tres performante pour courts sejours et soleil.',
                'video_path' => 'Sky2Tours.mp4',
                'ai_score' => 82.0,
                'satisfaction_score' => 4.64,
                'avg_price' => 890.0,
                'best_season' => 'Octobre - Avril',
                'travel_types' => 'couple,famille,solo',
                'interests' => 'culture,gastronomie,soleil',
                'is_featured' => 1,
                'display_order' => 5,
            ]),
            $this->normalizeFeaturedEntryPayload([
                'destination_name' => 'Islande',
                'country' => 'Islande',
                'continent' => 'Europe',
                'description' => 'Experience nature tres forte autour des paysages, road trips et aurores.',
                'video_path' => 'A beautifull Sceneary.mp4',
                'ai_score' => 80.0,
                'satisfaction_score' => 4.71,
                'avg_price' => 1490.0,
                'best_season' => 'Juin - Septembre',
                'travel_types' => 'solo,couple,aventure',
                'interests' => 'nature,aventure,photographie',
                'is_featured' => 1,
                'display_order' => 6,
            ]),
        ];
    }

    private function ensurePackagesAdminSchema(): void
    {
        if ($this->packagesAdminSchemaEnsured) {
            return;
        }

        $connection = $this->connectionFactory->getConnection();
        try {
            $this->addPackageColumnIfMissing($connection, 'admin_validation_note', 'ALTER TABLE packages ADD COLUMN admin_validation_note TEXT NULL AFTER montant_bloque');
            $this->addPackageColumnIfMissing($connection, 'validated_by_admin_email', 'ALTER TABLE packages ADD COLUMN validated_by_admin_email VARCHAR(255) NULL AFTER admin_validation_note');
            $this->addPackageColumnIfMissing($connection, 'validated_at', 'ALTER TABLE packages ADD COLUMN validated_at DATETIME NULL AFTER validated_by_admin_email');
            $this->addPackageColumnIfMissing($connection, 'created_via_admin', 'ALTER TABLE packages ADD COLUMN created_via_admin TINYINT(1) DEFAULT 0 AFTER validated_at');
            $this->packagesAdminSchemaEnsured = true;
        } catch (Throwable) {
        }
    }

    private function ensureAtmosphereSchema(): void
    {
        if ($this->atmosphereSchemaEnsured) {
            return;
        }

        $connection = $this->connectionFactory->getConnection();
        try {
            $connection->exec(
                'CREATE TABLE IF NOT EXISTS atmosphere_destinations (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    atmosphere_type VARCHAR(50) NULL,
                    title VARCHAR(100) NULL,
                    description TEXT NULL,
                    video_path VARCHAR(255) NULL,
                    ai_interest_tags TEXT NULL,
                    ai_suggested_destinations TEXT NULL,
                    ai_suggested_countries TEXT NULL,
                    ai_suggested_continents TEXT NULL,
                    ai_featured_payload TEXT NULL,
                    ai_score DECIMAL(5,2) DEFAULT 0,
                    avg_price DECIMAL(10,2) DEFAULT 0,
                    is_active TINYINT(1) DEFAULT 1,
                    display_order INT NULL,
                    created_by_admin INT DEFAULT 0,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_from_ai_at TIMESTAMP NULL DEFAULT NULL
                )'
            );
            $this->addAtmosphereColumnIfMissing($connection, 'ai_suggested_destinations', 'ALTER TABLE atmosphere_destinations ADD COLUMN ai_suggested_destinations TEXT NULL AFTER ai_interest_tags');
            $this->addAtmosphereColumnIfMissing($connection, 'ai_suggested_countries', 'ALTER TABLE atmosphere_destinations ADD COLUMN ai_suggested_countries TEXT NULL AFTER ai_suggested_destinations');
            $this->addAtmosphereColumnIfMissing($connection, 'ai_suggested_continents', 'ALTER TABLE atmosphere_destinations ADD COLUMN ai_suggested_continents TEXT NULL AFTER ai_suggested_countries');
            $this->addAtmosphereColumnIfMissing($connection, 'ai_featured_payload', 'ALTER TABLE atmosphere_destinations ADD COLUMN ai_featured_payload TEXT NULL AFTER ai_suggested_continents');
            $this->addAtmosphereColumnIfMissing($connection, 'ai_score', 'ALTER TABLE atmosphere_destinations ADD COLUMN ai_score DECIMAL(5,2) DEFAULT 0 AFTER ai_featured_payload');
            $this->addAtmosphereColumnIfMissing($connection, 'avg_price', 'ALTER TABLE atmosphere_destinations ADD COLUMN avg_price DECIMAL(10,2) DEFAULT 0 AFTER ai_score');
            $this->addAtmosphereColumnIfMissing($connection, 'updated_from_ai_at', 'ALTER TABLE atmosphere_destinations ADD COLUMN updated_from_ai_at TIMESTAMP NULL DEFAULT NULL AFTER created_at');
            $this->seedMissingAtmosphereDefaults($connection);
            $this->normalizeAtmosphereDisplayOrder($connection);
            $this->atmosphereSchemaEnsured = true;
        } catch (Throwable) {
        }
    }

    private function seedMissingAtmosphereDefaults(object $connection): void
    {
        try {
            $count = (int) ($connection->query('SELECT COUNT(*) FROM atmosphere_destinations')->fetchColumn() ?: 0);
            if ($count > 0) {
                return;
            }
        } catch (Throwable) {
            return;
        }

        foreach ($this->buildAtmosphereSeedEntries() as $entry) {
            if ($this->existsAtmosphereByType($connection, (string) ($entry['atmosphere_type'] ?? ''))) {
                continue;
            }

            try {
                $this->insertAtmosphereEntry($connection, $entry);
            } catch (Throwable) {
            }
        }
    }

    private function existsAtmosphereByType(object $connection, string $type): bool
    {
        $statement = $connection->prepare('SELECT id FROM atmosphere_destinations WHERE UPPER(atmosphere_type) = :type LIMIT 1');
        $statement->execute(['type' => strtoupper(trim($type))]);

        return (bool) $statement->fetch();
    }

    private function addAtmosphereColumnIfMissing(object $connection, string $columnName, string $sql): void
    {
        $statement = $connection->prepare('SHOW COLUMNS FROM atmosphere_destinations LIKE :columnName');
        $statement->execute(['columnName' => $columnName]);
        if ($statement->fetch()) {
            return;
        }

        $connection->exec($sql);
    }

    private function addPackageColumnIfMissing(object $connection, string $columnName, string $sql): void
    {
        $statement = $connection->prepare('SHOW COLUMNS FROM packages LIKE :columnName');
        $statement->execute(['columnName' => $columnName]);
        if ($statement->fetch()) {
            return;
        }

        $connection->exec($sql);
    }

    private function buildFeaturedDestinationKey(string $destinationName, string $country): string
    {
        return $this->normalizeFeaturedValue($destinationName).'|'.$this->normalizeFeaturedValue($country);
    }

    private function normalizeFeaturedValue(string $value): string
    {
        return strtolower(trim($this->transliterate($value)));
    }

    private function resolveFeaturedVisualTone(array $entry, int $index): string
    {
        $continent = $this->normalizeFeaturedValue((string) ($entry['continent'] ?? ''));

        return match ($continent) {
            'europe' => 'blue',
            'asie' => 'indigo',
            'amerique' => 'green',
            'afrique' => 'orange',
            default => match (abs($index % 4)) {
                0 => 'orange',
                1 => 'green',
                2 => 'indigo',
                default => 'blue',
            },
        };
    }

    private function buildFeaturedInitials(array $entry): string
    {
        $source = trim((string) ($entry['country'] ?? ''));
        $source = $source !== '' ? $source : trim((string) ($entry['destination_name'] ?? 'FD'));
        $normalized = preg_replace('/[^A-Za-z ]/', ' ', $this->transliterate($source)) ?? '';
        $normalized = trim($normalized);
        if ($normalized === '') {
            return 'FD';
        }

        $parts = preg_split('/\s+/', $normalized) ?: [];
        if (count($parts) <= 1) {
            return strtoupper(substr($parts[0] ?? $normalized, 0, 2));
        }

        return strtoupper(substr($parts[0], 0, 1).substr($parts[1], 0, 1));
    }

    private function findMatchingDestinationId(array $availableDestinations, array $entry): int
    {
        $expectedKey = $this->buildFeaturedDestinationKey(
            (string) ($entry['destination_name'] ?? ''),
            (string) ($entry['country'] ?? '')
        );
        $expectedNameKey = $this->buildFeaturedDestinationKey((string) ($entry['destination_name'] ?? ''), '');

        foreach ($availableDestinations as $destination) {
            $destinationKey = $this->buildFeaturedDestinationKey(
                (string) ($destination['nom'] ?? ''),
                (string) ($destination['pays'] ?? '')
            );
            if (
                $destinationKey === $expectedKey
                || $this->buildFeaturedDestinationKey((string) ($destination['nom'] ?? ''), '') === $expectedNameKey
            ) {
                return (int) ($destination['id'] ?? 0);
            }
        }

        return 0;
    }

    private function buildFeaturedReservationCopy(array $entry, array $stats): string
    {
        $lastReservation = $stats['last_reservation_at'] instanceof DateTimeImmutable
            ? $stats['last_reservation_at']->format('d/m/Y H:i')
            : 'aucune reservation recente';
        $reservationCount = (int) ($stats['reservation_count'] ?? 0);
        $averageTicket = $reservationCount === 0
            ? $this->formatCurrencyCompact((float) ($entry['avg_price'] ?? 0.0))
            : $this->formatCurrencyCompact(((float) ($stats['total_amount'] ?? 0.0)) / $reservationCount);

        return $reservationCount
            .' reservation(s) liee(s) a '
            .trim((string) ($entry['destination_name'] ?? 'cette destination'))
            .', panier moyen '
            .$averageTicket
            .', derniere reservation '
            .$lastReservation
            .'.';
    }

    private function formatFeaturedHistoryMeta(?array $historyEntry): string
    {
        if (!is_array($historyEntry)) {
            return 'Aucun';
        }

        $timestamp = $this->parseDateTime($historyEntry['created_at'] ?? null);
        $timeText = $timestamp instanceof DateTimeImmutable ? $timestamp->format('d/m H:i') : 'sans date';

        return $timeText.' | score '.$this->formatScore((float) ($historyEntry['ai_score'] ?? 0.0));
    }

    private function buildTopReservedPackages(array $travelPackages, array $packages, array $destinationsById, int $limit): array
    {
        $stats = [];

        foreach ($travelPackages as $entry) {
            $reservationStats = $this->resolveTravelPackageReservationStats($entry, $packages, $destinationsById);
            $stats[] = [
                'travel_package' => $entry,
                'reservation_count' => (int) ($reservationStats['reservation_count'] ?? 0),
                'revenue' => (float) ($reservationStats['revenue'] ?? 0.0),
            ];
        }

        usort($stats, function (array $left, array $right): int {
            $reservationCompare = ($right['reservation_count'] ?? 0) <=> ($left['reservation_count'] ?? 0);
            if ($reservationCompare !== 0) {
                return $reservationCompare;
            }

            $revenueCompare = ($right['revenue'] ?? 0.0) <=> ($left['revenue'] ?? 0.0);
            if ($revenueCompare !== 0) {
                return $revenueCompare;
            }

            return strcmp(
                (string) ($left['travel_package']['package_name'] ?? ''),
                (string) ($right['travel_package']['package_name'] ?? '')
            );
        });

        return array_slice($stats, 0, max(1, $limit));
    }

    private function resolveTravelPackageReservationStats(array $entry, array $packages, array $destinationsById): array
    {
        $aliases = $this->buildTravelPackageAliases($entry);
        $reservationCount = 0;
        $revenue = 0.0;

        foreach ($packages as $reservation) {
            $destination = $destinationsById[(int) ($reservation['destination_id'] ?? 0)] ?? null;
            $destinationName = trim((string) ($destination['nom'] ?? ''));
            $destinationCountry = trim((string) ($destination['pays'] ?? ''));
            $combined = $this->normalizeFeaturedValue($destinationName.' '.$destinationCountry);

            $matches = false;
            foreach ($aliases as $alias) {
                if ($alias === '') {
                    continue;
                }

                if (
                    str_contains($combined, $alias)
                    || ($destinationName !== '' && str_contains($alias, $this->normalizeFeaturedValue($destinationName)))
                ) {
                    $matches = true;
                    break;
                }
            }

            if (!$matches) {
                continue;
            }

            $reservationCount++;
            $revenue += max(0.0, (float) ($reservation['prix_total'] ?? 0.0));
        }

        return [
            'reservation_count' => $reservationCount,
            'revenue' => $revenue,
        ];
    }

    private function buildTravelPackageAliases(array $entry): array
    {
        $aliases = [];
        foreach ($this->splitTravelPackageAliases((string) ($entry['destinations'] ?? '')) as $token) {
            $aliases[] = $this->normalizeFeaturedValue($token);
        }
        foreach ($this->splitTravelPackageAliases((string) ($entry['package_name'] ?? '')) as $token) {
            $aliases[] = $this->normalizeFeaturedValue($token);
        }

        return array_values(array_filter(array_unique($aliases), static fn (string $value): bool => $value !== ''));
    }

    private function splitTravelPackageAliases(string $value): array
    {
        $safeValue = str_replace(['&', '/', '|', ' et '], [',', ',', ',', ','], trim($value));
        $parts = array_map('trim', explode(',', $safeValue));

        return array_values(array_filter($parts, static fn (string $part): bool => $part !== ''));
    }

    private function buildTravelPackageInitials(array $entry, int $index): string
    {
        $source = trim((string) ($entry['continent'] ?? ''));
        $source = $source !== '' ? $source : trim((string) ($entry['package_name'] ?? 'PK'));
        $normalized = preg_replace('/[^A-Za-z ]/', ' ', $this->transliterate($source)) ?? '';
        $normalized = trim($normalized);
        if ($normalized === '') {
            return 'PK';
        }

        $parts = preg_split('/\s+/', $normalized) ?: [];
        if (count($parts) <= 1) {
            $value = strtoupper(substr($parts[0] ?? $normalized, 0, 2));

            return $value !== '' ? $value : 'P'.($index + 1);
        }

        return strtoupper(substr($parts[0], 0, 1).substr($parts[1], 0, 1));
    }

    private function resolveTravelPackageBadgeTone(string $badge): string
    {
        return match (strtoupper(trim($badge))) {
            'EXCLUSIF' => 'violet',
            'POPULAIRE' => 'orange',
            default => 'blue',
        };
    }

    private function resolveTravelPackageRankingTone(string $continent, int $index): string
    {
        return match ($this->normalizeFeaturedValue($continent)) {
            'asie' => 'blue',
            'afrique' => 'orange',
            'amerique' => 'green',
            default => match (abs($index % 4)) {
                0 => 'blue',
                1 => 'orange',
                2 => 'indigo',
                default => 'green',
            },
        };
    }

    private function buildGeneratedTravelPackageName(string $travelType, string $destinationName): string
    {
        $travelType = $this->normalizeFeaturedValue($travelType);

        return match (true) {
            str_contains($travelType, 'famille') => 'Evasion Famille '.trim($destinationName),
            str_contains($travelType, 'aventure') => 'Aventure Signature '.trim($destinationName),
            str_contains($travelType, 'business') => 'Business Escape '.trim($destinationName),
            default => 'Escapade Romantique '.trim($destinationName),
        };
    }

    private function resolveGeneratedTravelPackageBadge(float $aiScore, int $index): string
    {
        if ($aiScore >= 90.0) {
            return 'Exclusif';
        }

        if ($index === 0 || $aiScore >= 82.0) {
            return 'Populaire';
        }

        return 'Nouveau';
    }

    private function buildGeneratedTravelPackageDescription(string $packageName, string $destinations, string $travelType, string $continent): string
    {
        $travelType = $this->normalizeFeaturedValue($travelType);
        $vibe = match ($travelType) {
            'famille' => 'pense pour les familles avec un rythme confortable et des experiences partagees',
            'aventure' => 'concu pour les voyageurs qui cherchent intensite, decouverte et grands espaces',
            'business' => 'structure pour combiner efficacite, confort premium et agenda optimise',
            default => 'compose pour les voyageurs qui veulent elegance, detente et moments memorables',
        };

        return trim($packageName).' vous emmene vers '.trim($destinations).', un itineraire '.$vibe.' sur la zone '.trim($continent).'.';
    }

    private function inferTravelPackageBestPeriod(string $continent): string
    {
        return match ($this->normalizeFeaturedValue($continent)) {
            'europe' => 'Avril a octobre',
            'asie' => 'Mars a mai',
            'afrique' => 'Juin a octobre',
            'amerique' => 'Septembre a novembre',
            'oceanie' => 'Octobre a mars',
            default => 'Toute l annee',
        };
    }

    private function resolveTravelPackageImagePath(string $destinationLike, string $continent): string
    {
        $normalized = $this->normalizeFeaturedValue($destinationLike);
        if (str_contains($normalized, 'paris') || str_contains($normalized, 'provence')) {
            return '9b4f03d821c26c149892eb9b646573bc.jpg';
        }
        if (str_contains($normalized, 'tokyo') || str_contains($normalized, 'japon')) {
            return 'bac4bce325c9a10f6fb77f30682cc7fa.jpg';
        }
        if (str_contains($normalized, 'thailande') || str_contains($normalized, 'vietnam') || str_contains($normalized, 'bogota')) {
            return 'da89f34fb5595d60358fcefe64fc6659.jpg';
        }
        if (str_contains($normalized, 'kenya') || str_contains($normalized, 'tanzanie') || str_contains($normalized, 'safari')) {
            return 'b98f59bef70929b9642bc88dd2a56f11.jpg';
        }
        if (str_contains($normalized, 'sydney') || str_contains($normalized, 'australie')) {
            return 'vaa-720x480-sydney-vivid-sydney-2024-guide.jpg';
        }
        if (str_contains($normalized, 'washington') || str_contains($normalized, 'new york') || str_contains($normalized, 'usa')) {
            return '80281906250b49a80467292e998492eb.jpg';
        }

        return match ($this->normalizeFeaturedValue($continent)) {
            'asie' => 'bac4bce325c9a10f6fb77f30682cc7fa.jpg',
            'afrique' => 'b98f59bef70929b9642bc88dd2a56f11.jpg',
            'amerique' => '80281906250b49a80467292e998492eb.jpg',
            'oceanie' => 'vaa-720x480-sydney-vivid-sydney-2024-guide.jpg',
            default => '9b4f03d821c26c149892eb9b646573bc.jpg',
        };
    }

    private function keywordBoostForTravelPackage(string $text, string $travelType): float
    {
        $normalizedText = $this->normalizeFeaturedValue($text);
        $score = 0.0;
        if (str_contains($normalizedText, 'culture')) {
            $score += 5.0;
        }
        if (str_contains($normalizedText, 'plage') || str_contains($normalizedText, 'sun')) {
            $score += 4.0;
        }
        if (str_contains($normalizedText, 'nature')) {
            $score += 4.0;
        }
        if (str_contains($normalizedText, 'luxe') || str_contains($normalizedText, 'premium')) {
            $score += 5.0;
        }

        $normalizedType = $this->normalizeFeaturedValue($travelType);
        if (str_contains($normalizedType, 'aventure') && str_contains($normalizedText, 'aventure')) {
            $score += 8.0;
        }
        if (str_contains($normalizedType, 'famille') && str_contains($normalizedText, 'famille')) {
            $score += 8.0;
        }
        if (str_contains($normalizedType, 'business') && str_contains($normalizedText, 'urbain')) {
            $score += 8.0;
        }
        if (str_contains($normalizedType, 'couple') && (str_contains($normalizedText, 'romant') || str_contains($normalizedText, 'elegance'))) {
            $score += 8.0;
        }

        return $score;
    }

    private function resolveTravelPackageInterests(string $travelType): array
    {
        $travelType = $this->normalizeFeaturedValue($travelType);

        return match (true) {
            str_contains($travelType, 'famille') => ['culture', 'nature', 'detente', 'gastronomie'],
            str_contains($travelType, 'aventure') => ['aventure', 'nature', 'photographie', 'culture'],
            str_contains($travelType, 'business') => ['urbain', 'gastronomie', 'shopping'],
            default => ['romance', 'culture', 'gastronomie', 'detente'],
        };
    }

    private function resolveTravelPackageIncludes(string $travelType): string
    {
        $travelType = $this->normalizeFeaturedValue($travelType);

        return match (true) {
            str_contains($travelType, 'business') => 'Vol,Hotel,Transferts,Wifi premium',
            str_contains($travelType, 'famille') => 'Vol,Hotel,Petit-dejeuner,Activites famille',
            str_contains($travelType, 'aventure') => 'Vol,Hotel,Guide,Excursions',
            default => 'Vol,Hotel,Petit-dejeuner',
        };
    }

    private function buildFallbackTravelPackageSuggestions(string $travelType, float $budgetMin, float $budgetMax, string $continent, int $durationDays): array
    {
        return [
            $this->createFallbackTravelPackageEntry(
                $this->buildGeneratedTravelPackageName($travelType, 'Paris'),
                'Paris, France',
                $continent !== '' && strtolower($continent) !== 'tous' ? $continent : 'Europe',
                $durationDays,
                $budgetMin > 0.0 ? $budgetMin : 1890.0,
                $budgetMax > 0.0 ? $budgetMax : max(2190.0, $budgetMin + 300.0),
                'Populaire',
                '9b4f03d821c26c149892eb9b646573bc.jpg',
                'Un city break premium entre adresses iconiques, douceur de vivre et experiences sur mesure.',
                $travelType,
                88.0,
                1
            ),
            $this->createFallbackTravelPackageEntry(
                $this->buildGeneratedTravelPackageName($travelType, 'Tokyo'),
                'Tokyo, Japon',
                'Asie',
                max(7, $durationDays),
                max(1890.0, $budgetMin),
                max(2450.0, $budgetMax),
                'Exclusif',
                'bac4bce325c9a10f6fb77f30682cc7fa.jpg',
                'Une aventure urbaine intense entre quartiers futuristes, gastronomie et experiences design.',
                $travelType,
                91.0,
                2
            ),
            $this->createFallbackTravelPackageEntry(
                $this->buildGeneratedTravelPackageName($travelType, 'Kenya'),
                'Kenya, Tanzanie',
                'Afrique',
                max(8, $durationDays),
                max(2990.0, $budgetMin),
                max(4590.0, $budgetMax),
                'Nouveau',
                'b98f59bef70929b9642bc88dd2a56f11.jpg',
                'Un itineraire grand format pour vivre la nature, l evasion et les plus beaux paysages sauvages.',
                $travelType,
                84.0,
                3
            ),
        ];
    }

    private function createFallbackTravelPackageEntry(
        string $packageName,
        string $destinations,
        string $continent,
        int $durationDays,
        float $priceFrom,
        float $priceTo,
        string $badge,
        string $imagePath,
        string $description,
        string $travelType,
        float $aiScore,
        int $displayOrder
    ): array {
        return $this->sanitizeTravelPackageEntry([
            'package_name' => $packageName,
            'destinations' => $destinations,
            'continent' => $continent,
            'duration_days' => $durationDays,
            'price_from' => $priceFrom,
            'price_to' => max($priceTo, $priceFrom),
            'badge' => $badge,
            'image_path' => $imagePath,
            'description' => $description,
            'travel_type' => $travelType,
            'interests' => implode(',', $this->resolveTravelPackageInterests($travelType)),
            'ai_generated' => 1,
            'ai_score' => $aiScore,
            'includes' => $this->resolveTravelPackageIncludes($travelType),
            'best_period' => $this->inferTravelPackageBestPeriod($continent),
            'is_active' => 1,
            'display_order' => $displayOrder,
        ]);
    }

    private function resolveFeaturedHistoryTone(string $actionType): string
    {
        $actionType = strtoupper(trim($actionType));

        return match ($actionType) {
            'ACCEPTED', 'AI_SYNC' => 'green',
            'REJECTED' => 'red',
            'AI_REFRESH', 'PENDING' => 'orange',
            default => 'blue',
        };
    }

    private function computeFeaturedAiScore(int $reservationCount, float $totalAmount): float
    {
        $score = 66.0 + min(24.0, ($reservationCount * 4.2)) + min(9.0, $totalAmount / 9000.0);

        return round($this->clamp($score, 62.0, 96.5), 1);
    }

    private function computeFeaturedSatisfactionScore(float $aiScore): float
    {
        return round($this->clamp(3.8 + (($aiScore - 60.0) / 40.0), 3.9, 4.9), 1);
    }

    private function inferBestSeason(string $destinationName, string $continent): string
    {
        $continent = $this->normalizeFeaturedValue($continent);

        return match ($continent) {
            'afrique' => 'Octobre - Avril',
            'asie' => 'Novembre - Avril',
            'amerique' => 'Mars - Octobre',
            'europe' => 'Avril - Octobre',
            default => str_contains($this->normalizeFeaturedValue($destinationName), 'bali')
                ? 'Avril - Octobre'
                : 'Toute l annee',
        };
    }

    private function inferTravelTypes(string $description, string $continent): string
    {
        $description = $this->normalizeFeaturedValue($description);
        $continent = $this->normalizeFeaturedValue($continent);

        if (str_contains($description, 'aventure')) {
            return 'couple,aventure';
        }

        return in_array($continent, ['asie', 'afrique', 'amerique', 'europe'], true)
            ? 'couple,famille'
            : 'couple,decouverte';
    }

    private function inferInterests(string $description, string $continent): string
    {
        $description = $this->normalizeFeaturedValue($description);
        $continent = $this->normalizeFeaturedValue($continent);

        if (str_contains($description, 'plage') || str_contains($description, 'mer')) {
            return 'plage';
        }

        return match ($continent) {
            'asie' => 'culture,decouverte',
            'amerique' => 'citytrip,decouverte',
            default => 'culture,decouverte',
        };
    }

    private function inferFeaturedVideoPath(string $destinationName, string $continent): string
    {
        $haystack = $this->normalizeFeaturedValue($destinationName.' '.$continent);
        if (str_contains($haystack, 'paris')) {
            return 'An Qiang.mp4';
        }
        if (str_contains($haystack, 'tokyo')) {
            return 'M.mp4';
        }
        if (str_contains($haystack, 'bali')) {
            return 'Luke Cameron.mp4';
        }
        if (str_contains($haystack, 'new york') || str_contains($haystack, 'usa')) {
            return 'Marina.NewyorkCity.mp4';
        }
        if (str_contains($haystack, 'marrakech') || str_contains($haystack, 'maroc')) {
            return 'Sky2Tours.mp4';
        }
        if (str_contains($haystack, 'islande')) {
            return 'A beautifull Sceneary.mp4';
        }
        if (str_contains($haystack, 'afrique')) {
            return 'Sky2Tours.mp4';
        }
        if (str_contains($haystack, 'amerique')) {
            return 'Marina.NewyorkCity.mp4';
        }
        if (str_contains($haystack, 'asie')) {
            return 'M.mp4';
        }

        return 'Luke Cameron.mp4';
    }

    private function parseNumericValue(mixed $value, float $fallback = 0.0): float
    {
        $normalized = trim(str_replace([',', 'EUR', 'TND', ' '], ['.', '', '', ''], (string) $value));
        if ($normalized === '' || !is_numeric($normalized)) {
            return $fallback;
        }

        return (float) $normalized;
    }

    private function normalizeNullableDate(mixed $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        try {
            return (new DateTimeImmutable($value))->format('Y-m-d');
        } catch (Throwable) {
            return null;
        }
    }

    private function formatScore(float $score): string
    {
        return number_format($score, 1, '.', '');
    }

    private function buildDashboardOverviewData(
        array $userDirectory,
        array $customerUsers,
        array $packages,
        array $payments,
        array $destinationsById
    ): array {
        $recentMonths = $this->buildRecentMonths(6);
        $currentMonth = $recentMonths[count($recentMonths) - 1] ?? date('Y-m');
        $previousMonth = $recentMonths[count($recentMonths) - 2] ?? $currentMonth;
        $revenueEvents = $this->buildDashboardRevenueEvents($packages, $payments, $destinationsById);

        $userSeries = [];
        $reservationSeries = [];
        $revenueSeries = [];
        $destinationSeries = [];
        $monthlyRevenuePoints = [];

        foreach ($recentMonths as $monthKey) {
            $monthUsers = $this->countUserRegistrationsForMonth($customerUsers, $monthKey);
            $monthReservations = $this->countReservationsForMonth($packages, $monthKey);
            $monthRevenue = $this->sumRevenueForMonth($revenueEvents, $monthKey);
            $monthDestinations = $this->countUniqueBookedDestinationsForMonth($packages, $monthKey);

            $userSeries[] = (float) $monthUsers;
            $reservationSeries[] = (float) $monthReservations;
            $revenueSeries[] = $monthRevenue;
            $destinationSeries[] = (float) $monthDestinations;
            $monthlyRevenuePoints[] = [
                'label' => $this->formatMonthLabel($monthKey),
                'total' => $monthRevenue,
            ];
        }

        $topDestinations = $this->buildTopDestinationRankings(
            $packages,
            $revenueEvents,
            $destinationsById,
            $currentMonth,
            $previousMonth
        );

        return [
            'active_users' => count(array_filter(
                $userDirectory,
                static fn (array $user): bool => in_array((string) ($user['status_key'] ?? ''), ['active', 'agent'], true)
            )),
            'total_reservations' => count($packages),
            'total_revenue' => array_reduce(
                $revenueEvents,
                static fn (float $total, array $event): float => $total + (float) ($event['amount'] ?? 0.0),
                0.0
            ),
            'total_destinations' => count($destinationsById),
            'users_change_percent' => $this->computePercentageChange(
                (float) $this->countUserRegistrationsForMonth($customerUsers, $currentMonth),
                (float) $this->countUserRegistrationsForMonth($customerUsers, $previousMonth)
            ),
            'reservations_change_percent' => $this->computePercentageChange(
                (float) $this->countReservationsForMonth($packages, $currentMonth),
                (float) $this->countReservationsForMonth($packages, $previousMonth)
            ),
            'revenue_change_percent' => $this->computePercentageChange(
                $this->sumRevenueForMonth($revenueEvents, $currentMonth),
                $this->sumRevenueForMonth($revenueEvents, $previousMonth)
            ),
            'destinations_change_percent' => $this->computePercentageChange(
                (float) $this->countUniqueBookedDestinationsForMonth($packages, $currentMonth),
                (float) $this->countUniqueBookedDestinationsForMonth($packages, $previousMonth)
            ),
            'user_series' => $userSeries,
            'reservation_series' => $reservationSeries,
            'revenue_series' => $revenueSeries,
            'destination_series' => $destinationSeries,
            'monthly_revenue_points' => $monthlyRevenuePoints,
            'top_destinations' => $topDestinations,
            'activities' => $this->buildDashboardActivities($customerUsers, $packages, $payments, $destinationsById),
        ];
    }

    private function buildRecentMonths(int $count): array
    {
        $months = [];
        $currentMonth = new DateTimeImmutable('first day of this month');

        for ($offset = $count - 1; $offset >= 0; --$offset) {
            $months[] = $currentMonth->modify('-'.$offset.' months')->format('Y-m');
        }

        return $months;
    }

    private function buildRecentDays(int $count): array
    {
        $days = [];
        $currentDay = new DateTimeImmutable('today');

        for ($offset = $count - 1; $offset >= 0; --$offset) {
            $days[] = $currentDay->modify('-'.$offset.' days')->format('Y-m-d');
        }

        return $days;
    }

    private function countUserRegistrationsForMonth(array $users, string $monthKey): int
    {
        $count = 0;

        foreach ($users as $user) {
            $timestamp = $this->resolveUserActivityDate($user);
            if ($timestamp !== null && $timestamp->format('Y-m') === $monthKey) {
                ++$count;
            }
        }

        return $count;
    }

    private function countReservationsForMonth(array $packages, string $monthKey): int
    {
        $count = 0;

        foreach ($packages as $package) {
            $timestamp = $this->resolvePackageActivityDate($package);
            if ($timestamp !== null && $timestamp->format('Y-m') === $monthKey) {
                ++$count;
            }
        }

        return $count;
    }

    private function countUniqueBookedDestinationsForMonth(array $packages, string $monthKey): int
    {
        $destinationIds = [];

        foreach ($packages as $package) {
            $timestamp = $this->resolvePackageActivityDate($package);
            $destinationId = (int) ($package['destination_id'] ?? 0);
            if ($timestamp === null || $destinationId <= 0 || $timestamp->format('Y-m') !== $monthKey) {
                continue;
            }

            $destinationIds[$destinationId] = true;
        }

        return count($destinationIds);
    }

    private function buildDashboardRevenueEvents(array $packages, array $payments, array $destinationsById): array
    {
        $events = [];
        $packagesById = [];
        $destinationLookup = $this->buildDestinationLookup($destinationsById);
        $coveredPackageIds = [];

        foreach ($packages as $package) {
            $packageId = (int) ($package['id'] ?? 0);
            if ($packageId > 0) {
                $packagesById[$packageId] = $package;
            }
        }

        foreach ($payments as $payment) {
            if (!$this->isPaidPayment($payment)) {
                continue;
            }

            $timestamp = $this->parseDateTime($payment['date_paiement'] ?? null);
            $amount = (float) ($payment['montant'] ?? 0.0);
            if ($timestamp === null || $amount <= 0.0) {
                continue;
            }

            $packageId = (int) ($payment['package_id'] ?? 0);
            $relatedPackage = $packagesById[$packageId] ?? null;
            $destinationId = 0;

            if (is_array($relatedPackage)) {
                $destinationId = (int) ($relatedPackage['destination_id'] ?? 0);
                $coveredPackageIds[$packageId] = true;
            } else {
                $destinationId = $this->resolveDestinationIdFromLookup((string) ($payment['destination'] ?? ''), $destinationLookup);
            }

            $events[] = [
                'amount' => $amount,
                'timestamp' => $timestamp,
                'destination_id' => $destinationId,
            ];
        }

        foreach ($packages as $package) {
            $packageId = (int) ($package['id'] ?? 0);
            if (isset($coveredPackageIds[$packageId]) || !$this->isRevenueEligibleReservation($package)) {
                continue;
            }

            $timestamp = $this->resolvePackageActivityDate($package);
            $amount = (float) ($package['prix_total'] ?? 0.0);
            if ($timestamp === null || $amount <= 0.0) {
                continue;
            }

            $events[] = [
                'amount' => $amount,
                'timestamp' => $timestamp,
                'destination_id' => (int) ($package['destination_id'] ?? 0),
            ];
        }

        return $events;
    }

    private function sumRevenueForMonth(array $revenueEvents, string $monthKey): float
    {
        $total = 0.0;

        foreach ($revenueEvents as $event) {
            $timestamp = $event['timestamp'] ?? null;
            if (!$timestamp instanceof DateTimeImmutable || $timestamp->format('Y-m') !== $monthKey) {
                continue;
            }

            $total += max(0.0, (float) ($event['amount'] ?? 0.0));
        }

        return $total;
    }

    private function sumRevenueForDay(array $revenueEvents, string $dayKey): float
    {
        $total = 0.0;

        foreach ($revenueEvents as $event) {
            $timestamp = $event['timestamp'] ?? null;
            if (!$timestamp instanceof DateTimeImmutable || $timestamp->format('Y-m-d') !== $dayKey) {
                continue;
            }

            $total += max(0.0, (float) ($event['amount'] ?? 0.0));
        }

        return $total;
    }

    private function buildTopDestinationRankings(
        array $packages,
        array $revenueEvents,
        array $destinationsById,
        string $currentMonth,
        string $previousMonth
    ): array {
        $rankings = [];

        foreach ($packages as $package) {
            if (!$this->isRevenueEligibleReservation($package)) {
                continue;
            }

            $destinationId = (int) ($package['destination_id'] ?? 0);
            if ($destinationId <= 0) {
                continue;
            }

            $destination = $destinationsById[$destinationId] ?? null;
            $destinationName = trim((string) ($destination['nom'] ?? 'Destination #'.$destinationId));
            $countryName = trim((string) ($destination['pays'] ?? 'Pays inconnu'));
            $timestamp = $this->resolvePackageActivityDate($package);

            if (!isset($rankings[$destinationId])) {
                $rankings[$destinationId] = [
                    'destination_name' => $destinationName,
                    'country_name' => $countryName,
                    'country_code' => $this->buildCountryCode($countryName),
                    'reservation_count' => 0,
                    'total_revenue' => 0.0,
                    'current_month_reservations' => 0,
                    'previous_month_reservations' => 0,
                    'growth_percent' => 0.0,
                ];
            }

            ++$rankings[$destinationId]['reservation_count'];

            if ($timestamp instanceof DateTimeImmutable) {
                $monthKey = $timestamp->format('Y-m');
                if ($monthKey === $currentMonth) {
                    ++$rankings[$destinationId]['current_month_reservations'];
                } elseif ($monthKey === $previousMonth) {
                    ++$rankings[$destinationId]['previous_month_reservations'];
                }
            }
        }

        foreach ($revenueEvents as $event) {
            $destinationId = (int) ($event['destination_id'] ?? 0);
            if ($destinationId <= 0 || !isset($rankings[$destinationId])) {
                continue;
            }

            $rankings[$destinationId]['total_revenue'] += max(0.0, (float) ($event['amount'] ?? 0.0));
        }

        foreach ($rankings as &$ranking) {
            $ranking['growth_percent'] = $this->computePercentageChange(
                (float) ($ranking['current_month_reservations'] ?? 0),
                (float) ($ranking['previous_month_reservations'] ?? 0)
            );
        }
        unset($ranking);

        usort($rankings, static function (array $left, array $right): int {
            $reservationCompare = ((int) ($right['reservation_count'] ?? 0)) <=> ((int) ($left['reservation_count'] ?? 0));
            if ($reservationCompare !== 0) {
                return $reservationCompare;
            }

            return ((float) ($right['total_revenue'] ?? 0.0)) <=> ((float) ($left['total_revenue'] ?? 0.0));
        });

        return array_slice($rankings, 0, 4);
    }

    private function buildDashboardActivities(
        array $customerUsers,
        array $packages,
        array $payments,
        array $destinationsById
    ): array {
        $activities = [];

        usort($packages, fn (array $left, array $right): int => $this->compareDates(
            $this->resolvePackageActivityDate($right),
            $this->resolvePackageActivityDate($left)
        ));
        foreach (array_slice($packages, 0, 4) as $package) {
            $activity = $this->buildReservationActivityItem($package, $destinationsById);
            if ($activity !== null) {
                $activities[] = $activity;
            }
        }

        usort($payments, fn (array $left, array $right): int => $this->compareDates(
            $this->parseDateTime($right['date_paiement'] ?? null),
            $this->parseDateTime($left['date_paiement'] ?? null)
        ));
        foreach (array_slice($payments, 0, 4) as $payment) {
            $activity = $this->buildPaymentActivityItem($payment);
            if ($activity !== null) {
                $activities[] = $activity;
            }
        }

        usort($customerUsers, fn (array $left, array $right): int => $this->compareDates(
            $this->resolveUserActivityDate($right),
            $this->resolveUserActivityDate($left)
        ));
        foreach (array_slice($customerUsers, 0, 4) as $user) {
            $activity = $this->buildUserActivityItem($user);
            if ($activity !== null) {
                $activities[] = $activity;
            }
        }

        usort($activities, fn (array $left, array $right): int => $this->compareDates(
            $right['timestamp'] ?? null,
            $left['timestamp'] ?? null
        ));

        $activities = array_slice($activities, 0, 3);

        return array_map(function (array $activity): array {
            unset($activity['timestamp']);

            return $activity;
        }, $activities);
    }

    private function buildReservationActivityItem(array $package, array $destinationsById): ?array
    {
        $timestamp = $this->resolvePackageActivityDate($package);
        if ($timestamp === null) {
            return null;
        }

        $clientName = trim((string) ($package['client_nom'] ?? 'Client'));
        $destinationId = (int) ($package['destination_id'] ?? 0);
        $destinationName = trim((string) (($destinationsById[$destinationId]['nom'] ?? 'Destination inconnue')));
        $travellers = max(1, (int) ($package['nb_adultes'] ?? 0) + (int) ($package['nb_enfants'] ?? 0));

        return [
            'initial' => $this->getInitial($clientName),
            'tone' => 'blue',
            'title' => 'Nouvelle reservation',
            'copy' => $destinationName.' | '.$travellers.' voyageur'.($travellers > 1 ? 's' : ''),
            'time_text' => $this->formatActivityTime($timestamp),
            'timestamp' => $timestamp,
        ];
    }

    private function buildPaymentActivityItem(array $payment): ?array
    {
        $timestamp = $this->parseDateTime($payment['date_paiement'] ?? null);
        if ($timestamp === null) {
            return null;
        }

        $clientName = trim((string) ($payment['client_nom'] ?? 'Client'));
        $destinationName = trim((string) ($payment['destination'] ?? 'Destination'));
        $status = strtoupper(trim((string) ($payment['statut'] ?? 'PAYE')));

        return [
            'initial' => $this->getInitial($clientName),
            'tone' => 'green',
            'title' => $status === 'PAYE' ? 'Paiement recu' : 'Paiement '.$this->formatStatusText($status),
            'copy' => $clientName.' | '.$destinationName.' | '.$this->formatCurrencyCompact((float) ($payment['montant'] ?? 0.0)),
            'time_text' => $this->formatActivityTime($timestamp),
            'timestamp' => $timestamp,
        ];
    }

    private function buildUserActivityItem(array $user): ?array
    {
        $timestamp = $this->resolveUserActivityDate($user);
        if ($timestamp === null) {
            return null;
        }

        $displayName = trim((string) ($user['display_name'] ?? $user['email'] ?? 'Client'));
        $email = trim((string) ($user['email'] ?? 'email indisponible'));

        return [
            'initial' => $this->getInitial($displayName),
            'tone' => 'orange',
            'title' => !empty($user['is_pending_validation']) ? 'Validation utilisateur' : 'Nouveau compte client',
            'copy' => $displayName.' | '.$email,
            'time_text' => $this->formatActivityTime($timestamp),
            'timestamp' => $timestamp,
        ];
    }

    private function buildDestinationLookup(array $destinationsById): array
    {
        $lookup = [];

        foreach ($destinationsById as $destinationId => $destination) {
            $this->putDestinationLookupValue($lookup, (string) ($destination['nom'] ?? ''), (int) $destinationId);
            $this->putDestinationLookupValue($lookup, (string) ($destination['pays'] ?? ''), (int) $destinationId);
            $this->putDestinationLookupValue(
                $lookup,
                trim((string) ($destination['nom'] ?? '')).' '.trim((string) ($destination['pays'] ?? '')),
                (int) $destinationId
            );
        }

        return $lookup;
    }

    private function putDestinationLookupValue(array &$lookup, string $rawValue, int $destinationId): void
    {
        $key = $this->normalizeLookupKey($rawValue);
        if ($key === '') {
            return;
        }

        $lookup[$key] ??= $destinationId;
    }

    private function resolveDestinationIdFromLookup(string $value, array $lookup): int
    {
        $key = $this->normalizeLookupKey($value);

        return (int) ($lookup[$key] ?? 0);
    }

    private function normalizeLookupKey(string $value): string
    {
        $value = strtoupper(trim($this->transliterate($value)));
        $value = preg_replace('/[^A-Z0-9]+/', ' ', $value) ?? '';

        return trim($value);
    }

    private function transliterate(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);

        return is_string($converted) && $converted !== '' ? $converted : $value;
    }

    private function resolvePackageActivityDate(array $package): ?DateTimeImmutable
    {
        return $this->parseDateTime($package['date_reservation'] ?? null);
    }

    private function resolveUserActivityDate(array $user): ?DateTimeImmutable
    {
        return $this->parseDateTime($user['created_at'] ?? $user['date_inscription'] ?? null);
    }

    private function parseDateTime(mixed $value): ?DateTimeImmutable
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        try {
            return new DateTimeImmutable($value);
        } catch (Throwable) {
            return null;
        }
    }

    private function compareDates(?DateTimeImmutable $left, ?DateTimeImmutable $right): int
    {
        if ($left === null && $right === null) {
            return 0;
        }
        if ($left === null) {
            return -1;
        }
        if ($right === null) {
            return 1;
        }

        return $left <=> $right;
    }

    private function isPaidPayment(array $payment): bool
    {
        return strtoupper(trim((string) ($payment['statut'] ?? ''))) === 'PAYE';
    }

    private function isRevenueEligibleReservation(array $package): bool
    {
        $status = strtoupper(trim((string) ($package['statut'] ?? 'CONFIRMEE')));
        if ($status === '') {
            return true;
        }

        if (preg_match('/ANNUL|REFUS|REMBOURS|ECHEC|ECHOUE|ATTENTE/', $status) === 1) {
            return false;
        }

        return preg_match('/CONFIRM|PAY|VALIDE|ACTIF|TERMINE|COMPLETE/', $status) === 1 || !str_contains($status, 'ATTENTE');
    }

    private function buildCountryCode(string $countryName): string
    {
        $letters = preg_replace('/[^A-Z]/', '', strtoupper($this->transliterate($countryName))) ?? '';
        if ($letters === '') {
            return 'ET';
        }

        return substr($letters, 0, 2);
    }

    private function getInitial(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return 'E';
        }

        return strtoupper(substr($this->transliterate($value), 0, 1));
    }

    private function formatMonthLabel(string $monthKey): string
    {
        $map = [
            '01' => 'Janv',
            '02' => 'Fevr',
            '03' => 'Mars',
            '04' => 'Avr',
            '05' => 'Mai',
            '06' => 'Juin',
            '07' => 'Juil',
            '08' => 'Aout',
            '09' => 'Sept',
            '10' => 'Oct',
            '11' => 'Nov',
            '12' => 'Dec',
        ];

        $monthNumber = substr($monthKey, 5, 2);

        return $map[$monthNumber] ?? $monthKey;
    }

    private function formatActivityTime(DateTimeImmutable $timestamp): string
    {
        $now = new DateTimeImmutable('now');
        $difference = $now->getTimestamp() - $timestamp->getTimestamp();

        if ($difference >= 0 && $difference < 3600) {
            return 'il y a '.max(1, (int) floor($difference / 60)).' min';
        }

        if ($difference >= 3600 && $difference < 86400) {
            return 'il y a '.max(1, (int) floor($difference / 3600)).' h';
        }

        return $timestamp->format('d').' '.strtolower($this->formatMonthLabel($timestamp->format('Y-m'))).'.';
    }

    private function formatStatusText(string $status): string
    {
        return strtolower(str_replace('_', ' ', trim($status)));
    }

    private function formatCurrencyCompact(float $amount): string
    {
        if ($amount >= 1000000) {
            return 'EUR'.number_format($amount / 1000000, 1, '.', ' ').'M';
        }

        if ($amount >= 1000) {
            return 'EUR'.number_format($amount / 1000, 1, '.', ' ').'K';
        }

        return 'EUR'.number_format($amount, 0, '.', ' ');
    }

    private function computePercentageChange(float $current, float $previous): float
    {
        if ($previous <= 0.0) {
            return $current > 0.0 ? 100.0 : 0.0;
        }

        return (($current - $previous) / $previous) * 100.0;
    }

    private function clamp(float $value, float $min, float $max): float
    {
        return max($min, min($max, $value));
    }

    private function buildPerformanceCopy(float $performancePercent, int $pendingPackages, int $paidPayments, int $totalPayments): string
    {
        if ($performancePercent >= 90.0) {
            return $pendingPackages === 0
                ? 'Flux admin tres stable, aucun dossier en attente.'
                : $pendingPackages.' dossier(s) en attente, mais le flux reste tres sain.';
        }

        if ($performancePercent >= 75.0) {
            if ($totalPayments <= 0) {
                return $pendingPackages.' dossier(s) en attente et aucun paiement enregistre pour le moment.';
            }

            return $pendingPackages.' dossier(s) en attente et '.$paidPayments.'/'.$totalPayments.' paiements valides.';
        }

        if ($performancePercent >= 60.0) {
            return 'Quelques validations ralentissent le flux admin actuellement.';
        }

        return 'Attention requise sur les validations et le suivi des paiements.';
    }

    private function ensureSponsorSchema(): void
    {
        if ($this->sponsorSchemaEnsured) {
            return;
        }

        $connection = $this->connectionFactory->getConnection();
        $this->addSponsorColumnIfMissing(
            $connection,
            'logo_blob',
            'ALTER TABLE sponsor ADD COLUMN logo_blob MEDIUMBLOB NULL AFTER logo_url'
        );
        $this->addSponsorColumnIfMissing(
            $connection,
            'logo_mime_type',
            'ALTER TABLE sponsor ADD COLUMN logo_mime_type VARCHAR(100) NULL AFTER logo_blob'
        );

        $this->sponsorSchemaEnsured = true;
    }

    private function addSponsorColumnIfMissing(object $connection, string $columnName, string $sql): void
    {
        $statement = $connection->prepare('SHOW COLUMNS FROM sponsor LIKE :columnName');
        $statement->execute(['columnName' => $columnName]);

        if ($statement->fetch()) {
            return;
        }

        $connection->exec($sql);
    }

    private function buildSponsorDisplayUrl(array $sponsor): string
    {
        $mimeType = trim((string) ($sponsor['logo_mime_type'] ?? ''));
        $logoBlob = $sponsor['logo_blob'] ?? null;
        if (is_resource($logoBlob)) {
            $logoBlob = stream_get_contents($logoBlob);
        }
        if (is_string($logoBlob) && $logoBlob !== '' && $mimeType !== '') {
            return 'data:'.$mimeType.';base64,'.base64_encode($logoBlob);
        }

        return trim((string) ($sponsor['logo_url'] ?? ''));
    }
}
