<?php

namespace App\Controller;

use App\Repository\AdminDashboardRepository;
use App\Repository\FactureRepository;
use App\Repository\PaiementRepository;
use App\Repository\SupportRepository;
use App\Repository\UserRepository;
use App\Service\InvoiceDeliveryService;
use App\Service\ProfilePhotoStorageService;
use App\Service\SponsorLogoStorageService;
use App\Service\UserNotificationService;
use App\Validation\LegacyValidator;
use App\Util\UploadedFileMimeTypeGuesser;
use App\View\PhpTemplateRenderer;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\Routing\Attribute\Route;

final class AdminDashboardController extends AbstractController
{
    public function __construct(
        private readonly PhpTemplateRenderer $renderer,
        private readonly AdminDashboardRepository $adminDashboardRepository,
        private readonly PaiementRepository $paiementRepository,
        private readonly FactureRepository $factureRepository,
        private readonly SupportRepository $supportRepository,
        private readonly UserRepository $userRepository,
        private readonly InvoiceDeliveryService $invoiceDeliveryService,
        private readonly ProfilePhotoStorageService $profilePhotoStorageService,
        private readonly SponsorLogoStorageService $sponsorLogoStorageService,
        private readonly UserNotificationService $notificationService,
    ) {
    }

    #[Route('/admin/dashboard', name: 'app_admin_dashboard', methods: ['GET'])]
    public function index(Request $request): Response
    {
        if ($redirect = $this->ensureAdminAccess($request)) {
            return $redirect;
        }

        $admin = $this->getAuthenticatedUser($request);
        $section = $this->sanitizeSection((string) $request->query->get('section', 'overview'));
        $userFilter = $this->sanitizeUserFilter((string) $request->query->get('user_filter', 'all'));
        $usersSearch = trim((string) $request->query->get('users_search', ''));
        $focusUserId = max(0, (int) $request->query->get('focus_user', 0));
        $focusReclamationId = max(0, (int) $request->query->get('focus_reclamation', 0));
        $selectedPaymentId = max(0, (int) $request->query->get('payment_id', 0));
        $notificationDrawerOpen = $this->shouldOpenNotificationDrawer((string) $request->query->get('notifications', ''));

        $databaseError = null;
        $overview = [
            'counts' => [
                'users' => 0,
                'active_users' => 0,
                'pending_users' => 0,
                'destinations' => 0,
                'destination_countries' => 0,
                'activites' => 0,
                'packages' => 0,
                'pending_packages' => 0,
                'confirmed_packages' => 0,
                'cancelled_packages' => 0,
                'travel_packages' => 0,
                'active_travel_packages' => 0,
                'map_destinations' => 0,
                'active_map_destinations' => 0,
                'atmospheres' => 0,
                'active_atmospheres' => 0,
                'featured_destinations' => 0,
                'visible_featured_destinations' => 0,
                'sponsors' => 0,
                'active_sponsors' => 0,
                'paiements' => 0,
                'factures' => 0,
                'reclamations' => 0,
                'reponses' => 0,
            ],
            'revenue' => [
                'total' => 0.0,
                'today' => 0.0,
                'today_change_percent' => 0.0,
                'daily_series' => [],
            ],
            'performance' => [
                'percent' => 0.0,
                'copy' => 'Systeme en attente',
            ],
            'pending_validations' => [],
            'latest_reservations' => [],
            'latest_payments' => [],
            'latest_destinations' => [],
            'latest_activites' => [],
            'travel_packages' => [],
            'map_destinations' => [],
            'atmospheres' => [],
            'featured_destinations' => [],
            'sponsors' => [],
            'latest_reclamations' => [],
            'destinations_admin' => [
                'entries' => [],
                'history_entries' => [],
                'all_destinations' => [],
                'visible_count' => 0,
                'linked_reservations' => 0,
                'average_ai_score' => 0.0,
                'average_satisfaction' => 0.0,
                'home_slots' => 6,
                'last_refresh_meta' => 'Aucun',
            ],
            'packages_admin' => [
                'entries' => [],
                'top_reserved' => [],
                'active_count' => 0,
                'ai_count' => 0,
                'average_ai_score' => 0.0,
            ],
            'map_admin' => [
                'entries' => [],
                'active_count' => 0,
                'ai_count' => 0,
                'average_ai_score' => 0.0,
                'source_copy' => 'map_destinations',
                'edit_copy' => 'clic + drag & drop',
                'sync_copy' => 'synchronisee',
            ],
            'atmospheres_admin' => [
                'entries' => [],
                'active_count' => 0,
                'sync_label' => 'LIVE',
            ],
            'reservations_admin' => [
                'stats' => [
                    'today_count' => 0,
                    'week_count' => 0,
                    'month_count' => 0,
                    'pending_count' => 0,
                ],
                'urgent_copy' => 'Aucune validation urgente pour le moment.',
                'pipeline_entries' => [],
                'validation_entries' => [],
                'recent_entries' => [],
                'forum_href' => '/admin/dashboard?section=reclamations',
            ],
        ];
        $supportSnapshot = [
            'counts' => [
                'total' => 0,
                'pending' => 0,
                'in_progress' => 0,
                'resolved' => 0,
                'rejected' => 0,
                'answered' => 0,
            ],
            'reclamations' => [],
            'responses_by_reclamation' => [],
        ];
        $paymentsAdmin = [
            'stats' => [
                'total' => 0,
                'paid' => 0,
                'revenue' => 0.0,
                'last_payment_at' => '',
                'pending_review' => 0,
            ],
            'entries' => [],
            'invoice_by_payment' => [],
        ];
        $facturesAdmin = [
            'stats' => [
                'total' => 0,
                'sent' => 0,
                'draft' => 0,
                'total_amount' => 0.0,
            ],
            'entries' => [],
            'payment_options' => [],
            'selected_payment_id' => $selectedPaymentId,
        ];
        $users = [];
        $notifications = [];
        $unreadCount = 0;

        try {
            $freshAdmin = $this->userRepository->getByEmail((string) ($admin['email'] ?? ''));
            if ($freshAdmin !== null) {
                $admin = array_merge($admin, $freshAdmin);
                $this->syncSessionUser($request, $freshAdmin);
            }

            $overview = $this->adminDashboardRepository->getOverviewSnapshot();
            $supportSnapshot = $this->supportRepository->getAdminSupportSnapshot();
            $users = $this->adminDashboardRepository->getUserDirectory($usersSearch, $userFilter);
            $allPayments = $this->paiementRepository->findAll();
            $allInvoices = $this->factureRepository->findAll();
            $paymentsAdmin = $this->buildPaymentsAdminData($allPayments, $allInvoices);
            $facturesAdmin = $this->buildInvoicesAdminData($allInvoices, $this->paiementRepository->findPaidPayments());
            $facturesAdmin['selected_payment_id'] = $selectedPaymentId;
            $overview['counts']['paiements'] = (int) ($paymentsAdmin['stats']['total'] ?? 0);
            $overview['counts']['factures'] = (int) ($facturesAdmin['stats']['total'] ?? 0);
            $notifications = $this->notificationService->getLatestNotifications((string) ($admin['email'] ?? ''), 20);
            $unreadCount = $this->notificationService->getUnreadCount((string) ($admin['email'] ?? ''));
        } catch (\Throwable $exception) {
            $databaseError = $exception->getMessage();
        }

        $admin['photo_display_url'] = $this->resolvePhotoUrlForView((string) ($admin['photo_url'] ?? ''));
        foreach ($users as &$user) {
            if (!is_array($user)) {
                continue;
            }

            $user['photo_display_url'] = $this->resolvePhotoUrlForView((string) ($user['photo_url'] ?? ''));
        }
        unset($user);

        $destinationsAdmin = $overview['destinations_admin'] ?? [];
        $packagesAdmin = $overview['packages_admin'] ?? [];
        $mapAdmin = $overview['map_admin'] ?? [];
        $reservationsAdmin = $overview['reservations_admin'] ?? [];
        $atmospheresAdmin = $overview['atmospheres_admin'] ?? [];
        $displayName = trim((string) ($admin['display_name'] ?? ''));
        $displayName = $displayName !== '' ? $displayName : 'Admin User';
        $email = (string) ($admin['email'] ?? 'admin@easytravel.local');
        $role = strtoupper((string) ($admin['role'] ?? 'ADMIN'));
        $photoUrl = trim((string) ($admin['photo_display_url'] ?? $admin['photo_url'] ?? ''));
        $initial = strtoupper(substr($displayName, 0, 1));
        $dashboardOverview = $overview['dashboard_overview'] ?? [];
        $monthlyRevenuePoints = $dashboardOverview['monthly_revenue_points'] ?? [];
        $topDestinations = $dashboardOverview['top_destinations'] ?? [];
        $monthlyRevenueHeights = $this->buildOverviewMiniBarHeights(
            array_map(static fn (array $point): float => (float) ($point['total'] ?? 0.0), $monthlyRevenuePoints),
            44.0,
            154.0
        );
        $dailyRevenueHeights = $this->buildOverviewMiniBarHeights($overview['revenue']['daily_series'] ?? [], 8.0, 10.0);
        $summaryCards = [
            [
                'abbr' => 'US',
                'tone' => 'blue',
                'label' => 'UTILISATEURS ACTIFS',
                'value' => $this->formatOverviewCount((float) ($dashboardOverview['active_users'] ?? ($overview['counts']['active_users'] ?? 0))),
                'hot' => true,
                'change' => (float) ($dashboardOverview['users_change_percent'] ?? 0.0),
                'change_copy' => $this->formatOverviewChange((float) ($dashboardOverview['users_change_percent'] ?? 0.0)),
                'change_tone' => $this->resolveOverviewChangeTone((float) ($dashboardOverview['users_change_percent'] ?? 0.0)),
                'series' => $this->buildOverviewMiniBarHeights($dashboardOverview['user_series'] ?? [], 10.0, 10.0),
            ],
            [
                'abbr' => 'RS',
                'tone' => 'orange',
                'label' => 'RESERVATIONS',
                'value' => $this->formatOverviewCount((float) ($dashboardOverview['total_reservations'] ?? ($overview['counts']['packages'] ?? 0))),
                'hot' => false,
                'change' => (float) ($dashboardOverview['reservations_change_percent'] ?? 0.0),
                'change_copy' => $this->formatOverviewChange((float) ($dashboardOverview['reservations_change_percent'] ?? 0.0)),
                'change_tone' => $this->resolveOverviewChangeTone((float) ($dashboardOverview['reservations_change_percent'] ?? 0.0)),
                'series' => $this->buildOverviewMiniBarHeights($dashboardOverview['reservation_series'] ?? [], 10.0, 10.0),
            ],
            [
                'abbr' => 'RV',
                'tone' => 'green',
                'label' => 'REVENUS TOTAUX',
                'value' => $this->formatOverviewCurrency((float) ($dashboardOverview['total_revenue'] ?? ($overview['revenue']['total'] ?? 0.0))),
                'hot' => false,
                'change' => (float) ($dashboardOverview['revenue_change_percent'] ?? 0.0),
                'change_copy' => $this->formatOverviewChange((float) ($dashboardOverview['revenue_change_percent'] ?? 0.0)),
                'change_tone' => $this->resolveOverviewChangeTone((float) ($dashboardOverview['revenue_change_percent'] ?? 0.0)),
                'series' => $this->buildOverviewMiniBarHeights($dashboardOverview['revenue_series'] ?? [], 10.0, 10.0),
            ],
            [
                'abbr' => 'DT',
                'tone' => 'violet',
                'label' => 'DESTINATIONS',
                'value' => $this->formatOverviewCount((float) ($dashboardOverview['total_destinations'] ?? ($overview['counts']['destinations'] ?? 0))),
                'hot' => false,
                'change' => (float) ($dashboardOverview['destinations_change_percent'] ?? 0.0),
                'change_copy' => $this->formatOverviewChange((float) ($dashboardOverview['destinations_change_percent'] ?? 0.0)),
                'change_tone' => $this->resolveOverviewChangeTone((float) ($dashboardOverview['destinations_change_percent'] ?? 0.0)),
                'series' => $this->buildOverviewMiniBarHeights($dashboardOverview['destination_series'] ?? [], 10.0, 10.0),
            ],
        ];
        $topDestinationsView = [];
        foreach ($topDestinations as $index => $destination) {
            $growthValue = (float) ($destination['growth_percent'] ?? 0.0);
            $topDestinationsView[] = [
                ...$destination,
                'rank_tone' => match ($index) {
                    0 => 'gold',
                    1 => 'silver',
                    2 => 'bronze',
                    default => 'blue',
                },
                'display_name' => trim((string) ($destination['country_code'] ?? 'ET')).'  '.trim((string) ($destination['destination_name'] ?? 'Destination')),
                'revenue_label' => $this->formatOverviewCurrency((float) ($destination['total_revenue'] ?? 0.0)),
                'growth_label' => $this->formatOverviewChange($growthValue),
                'growth_tone' => $this->resolveOverviewChangeTone($growthValue),
            ];
        }
        $pageTitles = [
            'overview' => 'Dashboard',
            'destinations' => 'Destinations',
            'packages' => 'Packages/Offres',
            'users' => 'Utilisateurs',
            'map' => 'Carte Interactive',
            'atmospheres' => 'Atmospheres',
            'reservations' => 'Reservations',
            'reclamations' => 'Reclamations',
            'paiements' => 'Paiements',
        ];
        $notificationParams = ['section' => $section, 'notifications' => 'open'];
        if ($section === 'users') {
            $notificationParams['user_filter'] = $userFilter;
            if ($usersSearch !== '') {
                $notificationParams['users_search'] = $usersSearch;
            }
            if ($focusUserId > 0) {
                $notificationParams['focus_user'] = $focusUserId;
            }
        }
        if ($section === 'reclamations' && $focusReclamationId > 0) {
            $notificationParams['focus_reclamation'] = $focusReclamationId;
        }
        $notificationCloseParams = $notificationParams;
        unset($notificationCloseParams['notifications']);
        $navItems = [
            ['section' => 'overview', 'label' => 'Dashboard', 'icon_shell' => 'admin-nav-icon-dashboard', 'icon' => 'dashboard', 'badge' => 'LIVE', 'badge_class' => 'admin-nav-badge-live', 'href' => $this->generateUrl('app_admin_dashboard', ['section' => 'overview'])],
            ['section' => 'destinations', 'label' => 'Destinations', 'icon_shell' => 'admin-nav-icon-destination', 'icon' => 'destination', 'badge' => (string) ($overview['counts']['destinations'] ?? 0), 'badge_class' => '', 'href' => $this->generateUrl('app_admin_dashboard', ['section' => 'destinations'])],
            ['section' => 'packages', 'label' => 'Packages/Offres', 'icon_shell' => 'admin-nav-icon-payment', 'icon' => 'bookings', 'badge' => (string) ($overview['counts']['travel_packages'] ?? 0), 'badge_class' => '', 'href' => $this->generateUrl('app_admin_dashboard', ['section' => 'packages'])],
            ['section' => 'users', 'label' => 'Utilisateurs', 'icon_shell' => 'admin-nav-icon-users', 'icon' => 'users', 'badge' => (string) ($overview['counts']['users'] ?? 0), 'badge_class' => '', 'href' => $this->generateUrl('app_admin_dashboard', ['section' => 'users', 'user_filter' => $userFilter, 'users_search' => $usersSearch])],
            ['section' => 'map', 'label' => 'Carte Interactive', 'icon_shell' => 'admin-nav-icon-destination', 'icon' => 'destination', 'badge' => (string) ($overview['counts']['map_destinations'] ?? 0), 'badge_class' => '', 'href' => $this->generateUrl('app_admin_dashboard', ['section' => 'map'])],
            ['section' => 'atmospheres', 'label' => 'Atmospheres', 'icon_shell' => 'admin-nav-icon-atmosphere', 'icon' => 'atmosphere', 'badge' => (string) ($overview['counts']['atmospheres'] ?? 0), 'badge_class' => '', 'href' => $this->generateUrl('app_admin_dashboard', ['section' => 'atmospheres'])],
            ['section' => 'reservations', 'label' => 'Reservations', 'icon_shell' => 'admin-nav-icon-bookings', 'icon' => 'bookings', 'badge' => (string) ($overview['counts']['packages'] ?? 0), 'badge_class' => '', 'href' => $this->generateUrl('app_admin_dashboard', ['section' => 'reservations'])],
            ['section' => 'reclamations', 'label' => 'Reclamations', 'icon_shell' => 'admin-nav-icon-users', 'icon' => 'users', 'badge' => (string) ($overview['counts']['reclamations'] ?? 0), 'badge_class' => '', 'href' => $this->generateUrl('app_admin_dashboard', ['section' => 'reclamations'])],
            ['section' => 'paiements', 'label' => 'Paiements', 'icon_shell' => 'admin-nav-icon-payment', 'icon' => 'payment', 'badge' => (string) ($overview['counts']['paiements'] ?? 0), 'badge_class' => '', 'href' => $this->generateUrl('app_admin_dashboard', ['section' => 'paiements'])],
        ];

        $viewContext = [
            'layout' => 'admin-layout',
            'pageClass' => 'admin-page-body',
            'title' => 'Admin Dashboard',
            'databaseError' => $databaseError,
            'currentUser' => $admin,
            'displayName' => $displayName,
            'currentEmail' => $email,
            'currentRole' => $role,
            'currentPhotoUrl' => $photoUrl,
            'currentInitial' => $initial,
            'activeSection' => $section,
            'pageTitles' => $pageTitles,
            'pageTitle' => $pageTitles[$section] ?? 'Dashboard',
            'lastDashboardRefreshLabel' => (new DateTimeImmutable('now'))->format('H:i:s'),
            'navItems' => $navItems,
            'userFilter' => $userFilter,
            'usersSearch' => $usersSearch,
            'focusUserId' => $focusUserId,
            'focusReclamationId' => $focusReclamationId,
            'notificationDrawerOpen' => $notificationDrawerOpen,
            'notificationRefreshUrl' => $this->generateUrl('app_admin_dashboard', $notificationParams),
            'notificationCloseUrl' => $this->generateUrl('app_admin_dashboard', $notificationCloseParams),
            'notificationFormState' => $notificationParams,
            'overview' => $overview,
            'dashboardOverview' => $dashboardOverview,
            'summaryCards' => $summaryCards,
            'monthlyRevenuePoints' => $monthlyRevenuePoints,
            'monthlyRevenueHeights' => $monthlyRevenueHeights,
            'dailyRevenueHeights' => $dailyRevenueHeights,
            'topDestinations' => $topDestinationsView,
            'supportSnapshot' => $supportSnapshot,
            'supportCounts' => $supportSnapshot['counts'] ?? [],
            'supportReclamations' => $supportSnapshot['reclamations'] ?? [],
            'supportResponses' => $supportSnapshot['responses_by_reclamation'] ?? [],
            'paymentsAdmin' => $paymentsAdmin,
            'paymentStats' => $paymentsAdmin['stats'] ?? [],
            'paymentEntries' => $paymentsAdmin['entries'] ?? [],
            'facturesAdmin' => $facturesAdmin,
            'invoiceStats' => $facturesAdmin['stats'] ?? [],
            'invoiceEntries' => $facturesAdmin['entries'] ?? [],
            'invoicePaymentOptions' => $facturesAdmin['payment_options'] ?? [],
            'featuredEntries' => $destinationsAdmin['entries'] ?? ($overview['featured_destinations'] ?? []),
            'featuredHistoryEntries' => $destinationsAdmin['history_entries'] ?? [],
            'allDestinationOptions' => $destinationsAdmin['all_destinations'] ?? [],
            'visibleFeaturedCount' => (int) ($destinationsAdmin['visible_count'] ?? ($overview['counts']['visible_featured_destinations'] ?? 0)),
            'linkedFeaturedReservations' => (int) ($destinationsAdmin['linked_reservations'] ?? 0),
            'featuredAverageAiScore' => (float) ($destinationsAdmin['average_ai_score'] ?? 0.0),
            'featuredAverageSatisfaction' => (float) ($destinationsAdmin['average_satisfaction'] ?? 0.0),
            'featuredHomeSlots' => max(1, (int) ($destinationsAdmin['home_slots'] ?? 6)),
            'featuredLastRefreshMeta' => (string) ($destinationsAdmin['last_refresh_meta'] ?? 'Aucun'),
            'packageEntries' => $packagesAdmin['entries'] ?? ($overview['travel_packages'] ?? []),
            'topReservedPackages' => $packagesAdmin['top_reserved'] ?? [],
            'travelPackageActiveCount' => (int) ($packagesAdmin['active_count'] ?? ($overview['counts']['active_travel_packages'] ?? 0)),
            'travelPackageAiCount' => (int) ($packagesAdmin['ai_count'] ?? 0),
            'travelPackageAverageAiScore' => (float) ($packagesAdmin['average_ai_score'] ?? 0.0),
            'mapEntries' => $mapAdmin['entries'] ?? ($overview['map_destinations'] ?? []),
            'mapActiveCount' => (int) ($mapAdmin['active_count'] ?? ($overview['counts']['active_map_destinations'] ?? 0)),
            'mapAiCount' => (int) ($mapAdmin['ai_count'] ?? 0),
            'mapAverageAiScore' => (float) ($mapAdmin['average_ai_score'] ?? 0.0),
            'mapSourceCopy' => (string) ($mapAdmin['source_copy'] ?? 'map_destinations'),
            'mapEditCopy' => (string) ($mapAdmin['edit_copy'] ?? 'clic + drag & drop'),
            'mapSyncCopy' => (string) ($mapAdmin['sync_copy'] ?? 'synchronisee'),
            'reservationStats' => $reservationsAdmin['stats'] ?? ['today_count' => 0, 'week_count' => 0, 'month_count' => 0, 'pending_count' => 0],
            'reservationPipelineEntries' => $reservationsAdmin['pipeline_entries'] ?? [],
            'reservationValidationEntries' => $reservationsAdmin['validation_entries'] ?? [],
            'reservationRecentEntries' => $reservationsAdmin['recent_entries'] ?? [],
            'reservationUrgentCopy' => (string) ($reservationsAdmin['urgent_copy'] ?? 'Aucune validation urgente pour le moment.'),
            'reservationForumHref' => (string) ($reservationsAdmin['forum_href'] ?? '/admin/dashboard?section=reclamations'),
            'atmosphereEntries' => $atmospheresAdmin['entries'] ?? ($overview['atmospheres'] ?? []),
            'atmosphereActiveCount' => (int) ($atmospheresAdmin['active_count'] ?? ($overview['counts']['active_atmospheres'] ?? 0)),
            'atmosphereSyncLabel' => (string) ($atmospheresAdmin['sync_label'] ?? 'LIVE'),
            'userFilterOptions' => [
                'all' => 'Tous',
                'active' => 'Actifs',
                'pending' => 'En attente',
                'premium' => 'Premium',
            ],
            'userRoleOptions' => ['USER', 'VIP', 'AGENT', 'ADMIN'],
            'users' => $users,
            'sponsors' => $overview['sponsors'] ?? [],
            'sponsorsWithLogoCount' => count(array_filter(
                $overview['sponsors'] ?? [],
                static fn (array $sponsor): bool => trim((string) ($sponsor['logo_display_url'] ?? $sponsor['logo_url'] ?? '')) !== ''
            )),
            'notifications' => $notifications,
            'unreadCount' => $unreadCount,
            'statusMessage' => $this->consumeFlash($request, 'success') ?? $this->consumeFlash($request, 'info'),
            'errorMessage' => $this->consumeFlash($request, 'error'),
        ];

        return new Response($this->renderer->render('admin/dashboard', $viewContext));
    }

    #[Route('/admin/destinations/refresh-ai', name: 'app_admin_destinations_refresh_ai', methods: ['POST'])]
    public function refreshDestinationsWithAi(Request $request): RedirectResponse
    {
        if ($redirect = $this->ensureAdminAccess($request)) {
            return $redirect;
        }

        $admin = $this->getAuthenticatedUser($request);
        $adminId = (int) ($admin['id'] ?? 0);
        if (!$this->adminDashboardRepository->refreshFeaturedDestinationsWithLocalSuggestions($adminId)) {
            $request->getSession()->getFlashBag()->add('error', 'Impossible de preparer les suggestions destinations pour le moment.');

            return $this->redirectBackToDashboard($request, 'destinations');
        }

        $request->getSession()->getFlashBag()->add('success', 'Les destinations vedettes ont ete rafraichies et synchronisees avec la Home.');

        return $this->redirectBackToDashboard($request, 'destinations');
    }

    #[Route('/admin/sponsors/generate-ai', name: 'app_admin_sponsor_generate_ai', methods: ['POST'])]
    public function generateSponsorWithAi(Request $request, HttpClientInterface $httpClient): JsonResponse
    {
        if ($this->ensureAdminAccess($request)) {
            return new JsonResponse(['error' => 'Accès refusé.'], 403);
        }

        $companyName = trim((string) $request->request->get('company_name', ''));
        if ($companyName === '') {
            return new JsonResponse(['error' => 'Le nom de la société est requis.'], 400);
        }

        $system = 'Tu es un assistant marketing. Tu génères des données structurées pour des sponsors de plateformes de voyage. Réponds UNIQUEMENT avec un objet JSON valide, sans texte autour, sans balises markdown.';
        $user   = sprintf(
            'Génère les données sponsor pour la société : "%s". '
            . 'Retourne un objet JSON avec exactement ces clés : '
            . 'description (texte professionnel court en français, 1-2 phrases), '
            . 'website (URL valide commençant par https://), '
            . 'type (exactement "Partenaire" ou "Premium"), '
            . 'montant (nombre entier estimé en euros). '
            . 'Exemple attendu : {"description":"...","website":"https://...","type":"Premium","montant":5000}',
            $companyName
        );

        $raw = null;
        try {
            $response = $httpClient->request('POST', 'https://text.pollinations.ai/openai', [
                'headers' => ['Content-Type' => 'application/json'],
                'json'    => [
                    'model'       => 'openai',
                    'temperature' => 0.7,
                    'max_tokens'  => 250,
                    'private'     => true,
                    'seed'        => random_int(1, 999999),
                    'messages'    => [
                        ['role' => 'system', 'content' => $system],
                        ['role' => 'user',   'content' => $user],
                    ],
                ],
                'timeout'      => 30,
                'max_duration' => 40,
            ]);
            $body = $response->getContent(false);
            $decoded = json_decode($body, true);
            $raw = $decoded['choices'][0]['message']['content']
                ?? $decoded['message']['content']
                ?? $decoded['content']
                ?? null;
        } catch (\Throwable) {
            // fall through to default
        }

        // Parse the AI JSON response
        $data = null;
        if ($raw !== null) {
            // Strip possible markdown fences
            $clean = preg_replace('/^```(?:json)?\s*/i', '', trim($raw));
            $clean = preg_replace('/\s*```$/', '', trim($clean ?? ''));
            $data  = json_decode($clean ?? '', true);
        }

        // Build safe result with fallbacks
        $description = trim((string) ($data['description'] ?? ''));
        $website     = trim((string) ($data['website'] ?? ''));
        $type        = trim((string) ($data['type'] ?? ''));
        $montant     = (int) ($data['montant'] ?? 0);

        if ($description === '') {
            $description = sprintf('Partenaire officiel EasyTravel. %s accompagne nos voyageurs avec des solutions de qualité.', $companyName);
        }
        if (!filter_var($website, FILTER_VALIDATE_URL)) {
            $slug    = strtolower(preg_replace('/[^a-z0-9]+/i', '', $companyName) ?? $companyName);
            $website = 'https://www.' . $slug . '.com';
        }
        if (!in_array($type, ['Partenaire', 'Premium'], true)) {
            $type = 'Partenaire';
        }
        if ($montant <= 0) {
            $montant = 1000;
        }

        return new JsonResponse([
            'description' => $description,
            'website'     => $website,
            'type'        => $type,
            'montant'     => $montant,
        ]);
    }

    #[Route('/admin/destinations/featured/{id}/save', name: 'app_admin_featured_destination_save', methods: ['POST'])]
    public function saveFeaturedDestination(int $id, Request $request): RedirectResponse
    {
        if ($redirect = $this->ensureAdminAccess($request)) {
            return $redirect;
        }

        $admin = $this->getAuthenticatedUser($request);
        $adminId = (int) ($admin['id'] ?? 0);
        if (!$this->adminDashboardRepository->updateFeaturedDestination($id, $this->extractFeaturedDestinationPayload($request), $adminId)) {
            $request->getSession()->getFlashBag()->add('error', 'Impossible de sauvegarder cette destination vedette.');

            return $this->redirectBackToDashboard($request, 'destinations');
        }

        $request->getSession()->getFlashBag()->add('success', 'La destination vedette a ete mise a jour.');

        return $this->redirectBackToDashboard($request, 'destinations');
    }

    #[Route('/admin/destinations/featured/{id}/replace', name: 'app_admin_featured_destination_replace', methods: ['POST'])]
    public function replaceFeaturedDestination(int $id, Request $request): RedirectResponse
    {
        if ($redirect = $this->ensureAdminAccess($request)) {
            return $redirect;
        }

        $destinationId = max(0, (int) $request->request->get('destination_id', 0));
        if ($destinationId <= 0) {
            $request->getSession()->getFlashBag()->add('error', 'Choisissez une destination de remplacement.');

            return $this->redirectBackToDashboard($request, 'destinations');
        }

        $admin = $this->getAuthenticatedUser($request);
        $adminId = (int) ($admin['id'] ?? 0);
        if (!$this->adminDashboardRepository->replaceFeaturedDestinationWithDestination($id, $destinationId, $adminId)) {
            $request->getSession()->getFlashBag()->add('error', 'Impossible de remplacer cette destination vedette.');

            return $this->redirectBackToDashboard($request, 'destinations');
        }

        $request->getSession()->getFlashBag()->add('success', 'La destination vedette a ete remplacee.');

        return $this->redirectBackToDashboard($request, 'destinations');
    }

    #[Route('/admin/destinations/featured/{id}/move', name: 'app_admin_featured_destination_move', methods: ['POST'])]
    public function moveFeaturedDestination(int $id, Request $request): RedirectResponse
    {
        if ($redirect = $this->ensureAdminAccess($request)) {
            return $redirect;
        }

        $direction = trim((string) $request->request->get('direction', ''));
        $offset = $direction === 'up' ? -1 : 1;
        $admin = $this->getAuthenticatedUser($request);
        $adminId = (int) ($admin['id'] ?? 0);
        if (!$this->adminDashboardRepository->moveFeaturedDestination($id, $offset, $adminId)) {
            $request->getSession()->getFlashBag()->add('error', 'Impossible de deplacer cette destination vedette.');

            return $this->redirectBackToDashboard($request, 'destinations');
        }

        $request->getSession()->getFlashBag()->add('success', 'L ordre des destinations Home a ete mis a jour.');

        return $this->redirectBackToDashboard($request, 'destinations');
    }

    #[Route('/admin/packages/generate-ai', name: 'app_admin_packages_generate_ai', methods: ['POST'])]
    public function generatePackagesWithAi(Request $request): RedirectResponse
    {
        if ($redirect = $this->ensureAdminAccess($request)) {
            return $redirect;
        }

        $created = $this->adminDashboardRepository->generateTravelPackagesWithLocalSuggestions([
            'travel_type' => trim((string) $request->request->get('travel_type', 'couple')),
            'budget_min' => (string) $request->request->get('budget_min', '1200'),
            'budget_max' => (string) $request->request->get('budget_max', '3200'),
            'continent' => trim((string) $request->request->get('continent', 'Tous')),
            'duration_days' => (string) $request->request->get('duration_days', '7'),
        ]);

        if ($created <= 0) {
            $request->getSession()->getFlashBag()->add('error', 'Impossible de generer des packages pour le moment.');

            return $this->redirectBackToDashboard($request, 'packages');
        }

        $request->getSession()->getFlashBag()->add('success', $created.' package(s) ont ete generes et synchronises avec la Home.');

        return $this->redirectBackToDashboard($request, 'packages');
    }

    #[Route('/admin/packages/{id}/save', name: 'app_admin_package_save', methods: ['POST'])]
    public function saveTravelPackage(int $id, Request $request): RedirectResponse
    {
        if ($redirect = $this->ensureAdminAccess($request)) {
            return $redirect;
        }

        if (!$this->adminDashboardRepository->updateTravelPackage($id, $this->extractTravelPackagePayload($request))) {
            $request->getSession()->getFlashBag()->add('error', 'Impossible de sauvegarder ce package pour le moment.');

            return $this->redirectBackToDashboard($request, 'packages');
        }

        $request->getSession()->getFlashBag()->add('success', 'Le package a ete mis a jour et la Home est synchronisee.');

        return $this->redirectBackToDashboard($request, 'packages');
    }

    #[Route('/admin/map/optimize-ai', name: 'app_admin_map_optimize_ai', methods: ['POST'])]
    public function optimizeInteractiveMap(Request $request): RedirectResponse
    {
        if ($redirect = $this->ensureAdminAccess($request)) {
            return $redirect;
        }

        $created = $this->adminDashboardRepository->optimizeMapDestinationsWithLocalSuggestions();
        if ($created <= 0) {
            $request->getSession()->getFlashBag()->add('error', 'Impossible de preparer des hotspots IA pour le moment.');

            return $this->redirectBackToDashboard($request, 'map');
        }

        $request->getSession()->getFlashBag()->add('success', $created.' hotspot(s) IA ont ete prepares pour la Home.');

        return $this->redirectBackToDashboard($request, 'map');
    }

    #[Route('/admin/map/create', name: 'app_admin_map_create', methods: ['POST'])]
    public function createInteractiveMapDestination(Request $request): RedirectResponse
    {
        if ($redirect = $this->ensureAdminAccess($request)) {
            return $redirect;
        }

        $payload = $this->extractMapDestinationPayload($request);
        if (trim((string) ($payload['city'] ?? '')) === '') {
            $request->getSession()->getFlashBag()->add('error', 'La ville du hotspot est obligatoire.');

            return $this->redirectBackToDashboard($request, 'map');
        }

        if (!$this->adminDashboardRepository->createMapDestination($payload)) {
            $request->getSession()->getFlashBag()->add('error', 'Impossible d ajouter ce hotspot pour le moment.');

            return $this->redirectBackToDashboard($request, 'map');
        }

        $request->getSession()->getFlashBag()->add('success', 'Le nouveau hotspot a ete ajoute et synchronise.');

        return $this->redirectBackToDashboard($request, 'map');
    }

    #[Route('/admin/map/{id}/save', name: 'app_admin_map_save', methods: ['POST'])]
    public function saveInteractiveMapDestination(int $id, Request $request): RedirectResponse
    {
        if ($redirect = $this->ensureAdminAccess($request)) {
            return $redirect;
        }

        if (!$this->adminDashboardRepository->updateMapDestination($id, $this->extractMapDestinationPayload($request))) {
            $request->getSession()->getFlashBag()->add('error', 'Impossible de sauvegarder ce hotspot pour le moment.');

            return $this->redirectBackToDashboard($request, 'map');
        }

        $request->getSession()->getFlashBag()->add(
            'success',
            $request->request->getBoolean('drag_save')
                ? 'La position du hotspot a ete mise a jour.'
                : 'Le hotspot a ete mis a jour et la Home est synchronisee.'
        );

        return $this->redirectBackToDashboard($request, 'map');
    }

    #[Route('/admin/atmospheres/generate-ai', name: 'app_admin_atmospheres_generate_ai', methods: ['POST'])]
    public function generateAtmospheresWithAi(Request $request): RedirectResponse
    {
        if ($redirect = $this->ensureAdminAccess($request)) {
            return $redirect;
        }

        $updated = $this->adminDashboardRepository->generateAtmospheresWithLocalSuggestions();
        if ($updated <= 0) {
            $request->getSession()->getFlashBag()->add('error', 'Impossible de generer les suggestions atmospheres pour le moment.');

            return $this->redirectBackToDashboard($request, 'atmospheres');
        }

        $request->getSession()->getFlashBag()->add('success', $updated.' atmosphere(s) ont ete enrichies et synchronisees.');

        return $this->redirectBackToDashboard($request, 'atmospheres');
    }

    #[Route('/admin/atmospheres/create', name: 'app_admin_atmosphere_create', methods: ['POST'])]
    public function createAtmosphere(Request $request): RedirectResponse
    {
        if ($redirect = $this->ensureAdminAccess($request)) {
            return $redirect;
        }

        $payload = $this->extractAtmospherePayload($request);
        if (trim((string) ($payload['title'] ?? '')) === '' && trim((string) ($payload['atmosphere_type'] ?? '')) === '') {
            $request->getSession()->getFlashBag()->add('error', 'Le titre ou le type de l atmosphere est obligatoire.');

            return $this->redirectBackToDashboard($request, 'atmospheres');
        }

        if ($this->adminDashboardRepository->createAtmosphere($payload) <= 0) {
            $request->getSession()->getFlashBag()->add('error', 'Impossible d ajouter cette atmosphere pour le moment.');

            return $this->redirectBackToDashboard($request, 'atmospheres');
        }

        $request->getSession()->getFlashBag()->add('success', 'La nouvelle atmosphere est ajoutee et synchronisee avec la Home.');

        return $this->redirectBackToDashboard($request, 'atmospheres');
    }

    #[Route('/admin/atmospheres/{id}/save', name: 'app_admin_atmosphere_save', methods: ['POST'])]
    public function saveAtmosphere(int $id, Request $request): RedirectResponse
    {
        if ($redirect = $this->ensureAdminAccess($request)) {
            return $redirect;
        }

        if (!$this->adminDashboardRepository->updateAtmosphere($id, $this->extractAtmospherePayload($request))) {
            $request->getSession()->getFlashBag()->add('error', 'Impossible de sauvegarder cette atmosphere pour le moment.');

            return $this->redirectBackToDashboard($request, 'atmospheres');
        }

        $request->getSession()->getFlashBag()->add('success', 'L atmosphere a ete mise a jour et la Home reste synchronisee.');

        return $this->redirectBackToDashboard($request, 'atmospheres');
    }

    #[Route('/admin/atmospheres/{id}/move', name: 'app_admin_atmosphere_move', methods: ['POST'])]
    public function moveAtmosphere(int $id, Request $request): RedirectResponse
    {
        if ($redirect = $this->ensureAdminAccess($request)) {
            return $redirect;
        }

        $direction = trim((string) $request->request->get('direction', ''));
        $offset = $direction === 'up' ? -1 : 1;
        if (!$this->adminDashboardRepository->moveAtmosphere($id, $offset)) {
            $request->getSession()->getFlashBag()->add('error', 'Impossible de changer l ordre de cette atmosphere.');

            return $this->redirectBackToDashboard($request, 'atmospheres');
        }

        $request->getSession()->getFlashBag()->add('success', 'L ordre des atmospheres Home a ete mis a jour.');

        return $this->redirectBackToDashboard($request, 'atmospheres');
    }

    #[Route('/admin/atmospheres/{id}/delete', name: 'app_admin_atmosphere_delete', methods: ['POST'])]
    public function deleteAtmosphere(int $id, Request $request): RedirectResponse
    {
        if ($redirect = $this->ensureAdminAccess($request)) {
            return $redirect;
        }

        if (!$this->adminDashboardRepository->deleteAtmosphere($id)) {
            $request->getSession()->getFlashBag()->add('error', 'Impossible de supprimer cette atmosphere.');

            return $this->redirectBackToDashboard($request, 'atmospheres');
        }

        $request->getSession()->getFlashBag()->add('success', 'L atmosphere a ete supprimee de la Home.');

        return $this->redirectBackToDashboard($request, 'atmospheres');
    }

    #[Route('/admin/reservations/create', name: 'app_admin_reservation_create', methods: ['POST'])]
    public function createReservation(Request $request): RedirectResponse
    {
        if ($redirect = $this->ensureAdminAccess($request)) {
            return $redirect;
        }

        $reservationId = $this->adminDashboardRepository->createReservationDraft($this->extractReservationDraftPayload($request));
        if ($reservationId <= 0) {
            $request->getSession()->getFlashBag()->add('error', 'Impossible de creer cette reservation admin pour le moment.');

            return $this->redirectBackToDashboard($request, 'reservations');
        }

        $request->getSession()->getFlashBag()->add('success', 'La nouvelle reservation admin #'.$reservationId.' a ete ajoutee dans le pipeline.');

        return $this->redirectBackToDashboard($request, 'reservations');
    }

    #[Route('/admin/reservations/{id}/accept', name: 'app_admin_reservation_accept', methods: ['POST'])]
    public function acceptReservation(int $id, Request $request): RedirectResponse
    {
        if ($redirect = $this->ensureAdminAccess($request)) {
            return $redirect;
        }

        $admin = $this->getAuthenticatedUser($request);
        if (!$this->adminDashboardRepository->acceptReservation($id, (string) ($admin['email'] ?? ''))) {
            $request->getSession()->getFlashBag()->add('error', 'Impossible d accepter cette reservation pour le moment.');

            return $this->redirectBackToDashboard($request, 'reservations');
        }

        $request->getSession()->getFlashBag()->add('success', 'La reservation a ete acceptee et le paiement a ete synchronise.');

        return $this->redirectBackToDashboard($request, 'reservations');
    }

    #[Route('/admin/reservations/{id}/refuse', name: 'app_admin_reservation_refuse', methods: ['POST'])]
    public function refuseReservation(int $id, Request $request): RedirectResponse
    {
        if ($redirect = $this->ensureAdminAccess($request)) {
            return $redirect;
        }

        $admin = $this->getAuthenticatedUser($request);
        $reason = $this->sanitizeReservationDecisionReason((string) $request->request->get('reason', ''));
        if (!$this->adminDashboardRepository->refuseReservation($id, (string) ($admin['email'] ?? ''), $reason)) {
            $request->getSession()->getFlashBag()->add('error', 'Impossible de refuser cette reservation pour le moment.');

            return $this->redirectBackToDashboard($request, 'reservations');
        }

        $request->getSession()->getFlashBag()->add('success', 'La reservation a ete refusee et le motif a ete enregistre.');

        return $this->redirectBackToDashboard($request, 'reservations');
    }

    #[Route('/admin/reservations/export', name: 'app_admin_reservations_export', methods: ['GET'])]
    public function exportReservations(Request $request): Response
    {
        if ($redirect = $this->ensureAdminAccess($request)) {
            return $redirect;
        }

        $rows = $this->adminDashboardRepository->getReservationExportRows(8);
        $lines = ['horaire;titre;details;statut'];
        foreach ($rows as $row) {
            $lines[] = implode(';', [
                $this->csvValue((string) ($row['time_badge'] ?? '')),
                $this->csvValue((string) ($row['title'] ?? 'Reservation')),
                $this->csvValue((string) ($row['recent_subtitle'] ?? '')),
                $this->csvValue((string) ($row['status_text'] ?? 'A revoir')),
            ]);
        }

        return new Response(
            implode(PHP_EOL, $lines),
            Response::HTTP_OK,
            [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="admin-reservations-'.(new \DateTimeImmutable('now'))->format('Ymd-His').'.csv"',
            ]
        );
    }

    #[Route('/admin/destinations/sponsors/create', name: 'app_admin_destinations_sponsor_create', methods: ['POST'])]
    public function createDestinationsSponsor(Request $request): RedirectResponse
    {
        if ($redirect = $this->ensureAdminAccess($request)) {
            return $redirect;
        }

        $payload = $this->extractSponsorPayload($request);
        $logoFile = $request->files->get('sponsor_logo');
        if (trim((string) ($payload['nom'] ?? '')) === '') {
            $request->getSession()->getFlashBag()->add('error', 'Le nom du sponsor est obligatoire.');

            return $this->redirectBackToDashboard($request, 'destinations');
        }

        $payload['site_web'] = $this->normalizeSponsorWebsite((string) ($payload['site_web'] ?? ''));
        if (($linkValidationMessage = $this->validateSponsorLinks($payload, $logoFile instanceof \Symfony\Component\HttpFoundation\File\UploadedFile && $logoFile->isValid())) !== null) {
            $request->getSession()->getFlashBag()->add('error', $linkValidationMessage);

            return $this->redirectBackToDashboard($request, 'destinations');
        }

        if ($logoFile instanceof \Symfony\Component\HttpFoundation\File\UploadedFile && $logoFile->isValid()) {
            $mimeType = UploadedFileMimeTypeGuesser::detect($logoFile) ?? '';
            $allowedMimeTypes = ['image/png', 'image/jpeg', 'image/webp', 'image/gif', 'image/svg+xml'];
            if (!in_array($mimeType, $allowedMimeTypes, true)) {
                $request->getSession()->getFlashBag()->add('error', 'Le logo sponsor doit etre une image PNG, JPG, WEBP, GIF ou SVG.');

                return $this->redirectBackToDashboard($request, 'destinations');
            }

            if ($logoFile->getSize() !== false && $logoFile->getSize() > 5 * 1024 * 1024) {
                $request->getSession()->getFlashBag()->add('error', 'Le logo sponsor ne doit pas depasser 5 Mo.');

                return $this->redirectBackToDashboard($request, 'destinations');
            }

            $logoBinary = @file_get_contents($logoFile->getRealPath());
            if (!is_string($logoBinary) || $logoBinary === '') {
                $request->getSession()->getFlashBag()->add('error', 'Impossible de lire le logo sponsor selectionne.');

                return $this->redirectBackToDashboard($request, 'destinations');
            }

            $payload['logo_blob'] = $logoBinary;
            $payload['logo_mime_type'] = $mimeType;
        }

        if (!$this->adminDashboardRepository->createSponsor($payload)) {
            $request->getSession()->getFlashBag()->add('error', 'Impossible d ajouter ce sponsor pour le moment.');

            return $this->redirectBackToDashboard($request, 'destinations');
        }

        $request->getSession()->getFlashBag()->add('success', 'Le sponsor Home a ete ajoute.');

        return $this->redirectBackToDashboard($request, 'destinations');
    }

    #[Route('/admin/destinations/sponsors/{uid}/delete', name: 'app_admin_destinations_sponsor_delete', methods: ['POST'])]
    public function deleteDestinationsSponsor(string $uid, Request $request): RedirectResponse
    {
        if ($redirect = $this->ensureAdminAccess($request)) {
            return $redirect;
        }

        if (!$this->adminDashboardRepository->deleteSponsorByUid($uid)) {
            $request->getSession()->getFlashBag()->add('error', 'Impossible de supprimer ce sponsor.');

            return $this->redirectBackToDashboard($request, 'destinations');
        }

        $request->getSession()->getFlashBag()->add('success', 'Le sponsor a ete supprime.');

        return $this->redirectBackToDashboard($request, 'destinations');
    }

    #[Route('/admin/notifications/read-all', name: 'app_admin_notifications_read_all', methods: ['POST'])]
    public function readAllNotifications(Request $request): RedirectResponse
    {
        if ($redirect = $this->ensureAdminAccess($request)) {
            return $redirect;
        }

        $admin = $this->getAuthenticatedUser($request);
        $this->notificationService->markAllAsRead((string) ($admin['email'] ?? ''));
        $request->getSession()->getFlashBag()->add('success', 'Notifications admin marquees comme lues.');

        return $this->redirectBackToDashboard(
            $request,
            'overview',
            null,
            null,
            $this->shouldOpenNotificationDrawer((string) $request->request->get('notifications', ''))
        );
    }

    #[Route('/admin/reclamations/{id}/status', name: 'app_admin_reclamation_status', methods: ['POST'])]
    public function updateReclamationStatus(int $id, Request $request): RedirectResponse
    {
        if ($redirect = $this->ensureAdminAccess($request)) {
            return $redirect;
        }

        $reclamation = $this->supportRepository->getReclamationById($id);
        if ($reclamation === null) {
            $request->getSession()->getFlashBag()->add('error', 'Reclamation introuvable.');

            return $this->redirectBackToDashboard($request, 'reclamations');
        }

        $status = $this->sanitizeReclamationStatus((string) $request->request->get('statut', 'EN_ATTENTE'));
        if (!$this->supportRepository->updateReclamationStatus($id, $status)) {
            $request->getSession()->getFlashBag()->add('error', 'Impossible de mettre a jour ce statut pour le moment.');

            return $this->redirectBackToDashboard($request, 'reclamations', null, $id);
        }

        $admin = $this->getAuthenticatedUser($request);
        $recipientEmail = trim((string) ($reclamation['email'] ?? ''));
        if ($recipientEmail !== '') {
            $this->notificationService->notifyUser(
                $recipientEmail,
                (string) ($reclamation['role'] ?? 'USER'),
                (string) ($admin['email'] ?? ''),
                (string) ($admin['role'] ?? 'ADMIN'),
                'ACCOUNT',
                'Mise a jour de votre reclamation',
                'Votre reclamation "'.(string) ($reclamation['sujet'] ?? 'Support').'" est maintenant '.strtolower(str_replace('_', ' ', $status)).'.'
            );
        }

        $request->getSession()->getFlashBag()->add('success', 'Le statut de la reclamation a ete mis a jour.');

        return $this->redirectBackToDashboard($request, 'reclamations', null, $id);
    }

    #[Route('/admin/reclamations/{id}/reply', name: 'app_admin_reclamation_reply', methods: ['POST'])]
    public function replyToReclamation(int $id, Request $request): RedirectResponse
    {
        if ($redirect = $this->ensureAdminAccess($request)) {
            return $redirect;
        }

        $reclamation = $this->supportRepository->getReclamationById($id);
        if ($reclamation === null) {
            $request->getSession()->getFlashBag()->add('error', 'Reclamation introuvable.');

            return $this->redirectBackToDashboard($request, 'reclamations');
        }

        $content = trim((string) $request->request->get('contenu', ''));
        if ($content === '') {
            $request->getSession()->getFlashBag()->add('error', 'Veuillez saisir une reponse avant l envoi.');

            return $this->redirectBackToDashboard($request, 'reclamations', null, $id);
        }

        if (mb_strlen($content) < 4) {
            $request->getSession()->getFlashBag()->add('error', 'La reponse est trop courte.');

            return $this->redirectBackToDashboard($request, 'reclamations', null, $id);
        }

        $admin = $this->getAuthenticatedUser($request);
        $adminId = (int) ($admin['id'] ?? 0);
        if ($adminId <= 0) {
            $request->getSession()->getFlashBag()->add('error', 'Session admin invalide.');

            return $this->redirectBackToDashboard($request, 'reclamations', null, $id);
        }

        if (!$this->supportRepository->addAdminResponse($id, $adminId, $content)) {
            $request->getSession()->getFlashBag()->add('error', 'Impossible d envoyer cette reponse pour le moment.');

            return $this->redirectBackToDashboard($request, 'reclamations', null, $id);
        }

        if (strtoupper((string) ($reclamation['statut'] ?? 'EN_ATTENTE')) === 'EN_ATTENTE') {
            $this->supportRepository->updateReclamationStatus($id, 'EN_COURS');
        }

        $recipientEmail = trim((string) ($reclamation['email'] ?? ''));
        if ($recipientEmail !== '') {
            $this->notificationService->notifyUser(
                $recipientEmail,
                (string) ($reclamation['role'] ?? 'USER'),
                (string) ($admin['email'] ?? ''),
                (string) ($admin['role'] ?? 'ADMIN'),
                'ACCOUNT',
                'Nouvelle reponse a votre reclamation',
                'L administration a repondu a votre reclamation "'.(string) ($reclamation['sujet'] ?? 'Support').'".'
            );
        }

        $request->getSession()->getFlashBag()->add('success', 'La reponse a ete envoyee au client.');

        return $this->redirectBackToDashboard($request, 'reclamations', null, $id);
    }

    #[Route('/admin/dashboard/paiements/save', name: 'app_admin_dashboard_payment_save', methods: ['POST'])]
    public function saveDashboardPayment(Request $request): RedirectResponse
    {
        if ($redirect = $this->ensureAdminAccess($request)) {
            return $redirect;
        }

        $payload = $this->extractDashboardPaymentPayload($request);
        $errorMessage = $this->validateDashboardPaymentPayload($payload);
        if ($errorMessage !== null) {
            $request->getSession()->getFlashBag()->add('error', $errorMessage);

            return $this->redirectBackToDashboard($request, 'paiements');
        }

        $paymentId = (int) ($payload['id'] ?? 0);
        if ($paymentId > 0) {
            $saved = $this->paiementRepository->update($paymentId, $payload);
            $successMessage = 'Paiement mis a jour avec succes.';
        } else {
            $paymentId = $this->paiementRepository->create($payload);
            $saved = $paymentId > 0;
            $successMessage = 'Paiement cree avec succes.';
        }

        if (!$saved) {
            $request->getSession()->getFlashBag()->add('error', 'Impossible de sauvegarder ce paiement pour le moment.');

            return $this->redirectBackToDashboard($request, 'paiements');
        }

        $request->getSession()->getFlashBag()->add('success', $successMessage);

        return $this->redirectBackToDashboard($request, 'paiements');
    }

    #[Route('/admin/dashboard/paiements/{id}/delete', name: 'app_admin_dashboard_payment_delete', methods: ['POST'])]
    public function deleteDashboardPayment(int $id, Request $request): RedirectResponse
    {
        if ($redirect = $this->ensureAdminAccess($request)) {
            return $redirect;
        }

        if ($this->factureRepository->findByPaiementId($id) !== null) {
            $request->getSession()->getFlashBag()->add('error', 'Supprimez d abord la facture liee avant de retirer ce paiement.');

            return $this->redirectBackToDashboard($request, 'paiements');
        }

        if (!$this->paiementRepository->delete($id)) {
            $request->getSession()->getFlashBag()->add('error', 'Impossible de supprimer ce paiement pour le moment.');

            return $this->redirectBackToDashboard($request, 'paiements');
        }

        $request->getSession()->getFlashBag()->add('success', 'Paiement supprime avec succes.');

        return $this->redirectBackToDashboard($request, 'paiements');
    }

    #[Route('/admin/dashboard/factures/save', name: 'app_admin_dashboard_invoice_save', methods: ['POST'])]
    public function saveDashboardInvoice(Request $request): RedirectResponse
    {
        if ($redirect = $this->ensureAdminAccess($request)) {
            return $redirect;
        }

        $payload = $this->hydrateDashboardInvoicePayload($this->extractDashboardInvoicePayload($request));
        $payload['montant_total'] = $this->computeDashboardInvoiceTotal($payload);
        $errorMessage = $this->validateDashboardInvoicePayload($payload);
        if ($errorMessage !== null) {
            $request->getSession()->getFlashBag()->add('error', $errorMessage);

            return $this->redirectBackToDashboard($request, 'paiements');
        }

        $intent = trim((string) $request->request->get('intent', 'save'));
        if (trim((string) ($payload['statut'] ?? '')) === '') {
            $payload['statut'] = 'GENEREE';
        }

        $invoiceId = (int) ($payload['id'] ?? 0);
        if ($invoiceId > 0) {
            $saved = $this->factureRepository->update($invoiceId, $payload);
            $successMessage = 'Facture mise a jour.';
        } else {
            $invoiceId = $this->factureRepository->create($payload);
            $saved = $invoiceId > 0;
            $successMessage = 'Facture generee avec succes.';
        }

        if (!$saved) {
            $request->getSession()->getFlashBag()->add('error', 'Impossible de sauvegarder cette facture pour le moment.');

            return $this->redirectBackToDashboard(
                $request,
                'paiements',
                null,
                null,
                null,
                ['payment_id' => (int) ($payload['paiement_id'] ?? 0)]
            );
        }

        if ($intent !== 'send') {
            $request->getSession()->getFlashBag()->add('success', $successMessage);
        }

        if ($intent === 'send') {
            $invoice = $this->factureRepository->find($invoiceId);
            if ($invoice === null) {
                $request->getSession()->getFlashBag()->add('error', 'Facture introuvable apres sauvegarde.');
            } else {
                $result = $this->invoiceDeliveryService->deliver($invoice);
                $request->getSession()->getFlashBag()->add($result['ok'] ? 'success' : 'error', $result['message']);
            }
        }

        return $this->redirectBackToDashboard(
            $request,
            'paiements',
            null,
            null,
            null,
            ['payment_id' => (int) ($payload['paiement_id'] ?? 0)]
        );
    }

    #[Route('/admin/dashboard/factures/{id}/send', name: 'app_admin_dashboard_invoice_send', methods: ['POST'])]
    public function sendDashboardInvoice(int $id, Request $request): RedirectResponse
    {
        if ($redirect = $this->ensureAdminAccess($request)) {
            return $redirect;
        }

        $invoice = $this->factureRepository->find($id);
        if ($invoice === null) {
            $request->getSession()->getFlashBag()->add('error', 'Facture introuvable.');

            return $this->redirectBackToDashboard($request, 'paiements');
        }

        $result = $this->invoiceDeliveryService->deliver($invoice);
        if (!$result['ok']) {
            $request->getSession()->getFlashBag()->add('error', $result['message']);

            return $this->redirectBackToDashboard(
                $request,
                'paiements',
                null,
                null,
                null,
                ['payment_id' => (int) ($invoice['paiement_id'] ?? 0)]
            );
        }

        $request->getSession()->getFlashBag()->add('success', $result['message']);

        return $this->redirectBackToDashboard(
            $request,
            'paiements',
            null,
            null,
            null,
            ['payment_id' => (int) ($invoice['paiement_id'] ?? 0)]
        );
    }

    #[Route('/admin/dashboard/factures/{id}/delete', name: 'app_admin_dashboard_invoice_delete', methods: ['POST'])]
    public function deleteDashboardInvoice(int $id, Request $request): RedirectResponse
    {
        if ($redirect = $this->ensureAdminAccess($request)) {
            return $redirect;
        }

        if (!$this->factureRepository->delete($id)) {
            $request->getSession()->getFlashBag()->add('error', 'Impossible de supprimer cette facture pour le moment.');

            return $this->redirectBackToDashboard($request, 'paiements');
        }

        $request->getSession()->getFlashBag()->add('success', 'Facture supprimee avec succes.');

        return $this->redirectBackToDashboard($request, 'paiements');
    }

    #[Route('/admin/users/{id}/validate', name: 'app_admin_user_validate', methods: ['POST'])]
    public function validateUser(int $id, Request $request): RedirectResponse
    {
        if ($redirect = $this->ensureAdminAccess($request)) {
            return $redirect;
        }

        $user = $this->userRepository->getById($id);
        if ($user === null) {
            $request->getSession()->getFlashBag()->add('error', 'Compte utilisateur introuvable.');

            return $this->redirectBackToDashboard($request);
        }

        if ($this->isAdminRole((string) ($user['role'] ?? 'USER'))) {
            $request->getSession()->getFlashBag()->add('error', "Ce compte admin n'a pas besoin de validation.");

            return $this->redirectBackToDashboard($request);
        }

        if (($user['is_validated'] ?? true) === true && ($user['is_active'] ?? true) === true) {
            $request->getSession()->getFlashBag()->add('info', $user['display_name'].' est deja valide.');

            return $this->redirectBackToDashboard($request, 'users', $id);
        }

        if (!$this->userRepository->validateUserAccount((string) ($user['email'] ?? ''))) {
            $request->getSession()->getFlashBag()->add('error', 'Impossible de valider ce compte pour le moment.');

            return $this->redirectBackToDashboard($request);
        }

        $admin = $this->getAuthenticatedUser($request);
        $this->notificationService->notifyUser(
            (string) ($user['email'] ?? ''),
            (string) ($user['role'] ?? 'USER'),
            (string) ($admin['email'] ?? ''),
            (string) ($admin['role'] ?? 'ADMIN'),
            'ACCOUNT',
            "Compte valide par l'administration",
            'Votre compte EasyTravel a ete valide. Vous pouvez maintenant vous connecter.'
        );

        $request->getSession()->getFlashBag()->add('success', $user['display_name'].' peut maintenant se connecter.');

        return $this->redirectBackToDashboard($request, 'users', $id);
    }

    #[Route('/admin/users/{id}/toggle-active', name: 'app_admin_user_toggle_active', methods: ['POST'])]
    public function toggleUserActive(int $id, Request $request): RedirectResponse
    {
        if ($redirect = $this->ensureAdminAccess($request)) {
            return $redirect;
        }

        $user = $this->userRepository->getById($id);
        if ($user === null) {
            $request->getSession()->getFlashBag()->add('error', 'Compte utilisateur introuvable.');

            return $this->redirectBackToDashboard($request);
        }

        if ($this->isAdminRole((string) ($user['role'] ?? 'USER'))) {
            $request->getSession()->getFlashBag()->add('error', "La suspension d'un compte admin n'est pas autorisee ici.");

            return $this->redirectBackToDashboard($request);
        }

        $isActive = (bool) ($user['is_active'] ?? true);
        $ok = $isActive
            ? $this->userRepository->suspendUserAccount((string) ($user['email'] ?? ''))
            : $this->userRepository->reactivateUserAccount((string) ($user['email'] ?? ''));

        if (!$ok) {
            $request->getSession()->getFlashBag()->add(
                'error',
                $isActive ? 'Impossible de suspendre ce compte pour le moment.' : 'Impossible de reactiver ce compte pour le moment.'
            );

            return $this->redirectBackToDashboard($request);
        }

        $request->getSession()->getFlashBag()->add(
            'success',
            $isActive
                ? $user['display_name']." a ete suspendu par l'administration."
                : $user['display_name']." a ete reactive par l'administration."
        );

        return $this->redirectBackToDashboard($request, 'users', $id);
    }

    #[Route('/admin/users/{id}/role', name: 'app_admin_user_role', methods: ['POST'])]
    public function updateUserRole(int $id, Request $request): RedirectResponse
    {
        if ($redirect = $this->ensureAdminAccess($request)) {
            return $redirect;
        }

        $user = $this->userRepository->getById($id);
        if ($user === null) {
            $request->getSession()->getFlashBag()->add('error', 'Compte utilisateur introuvable.');

            return $this->redirectBackToDashboard($request);
        }

        if ($this->isAdminRole((string) ($user['role'] ?? 'USER'))) {
            $request->getSession()->getFlashBag()->add('error', "Le changement de role d'un admin n'est pas autorise ici.");

            return $this->redirectBackToDashboard($request);
        }

        $newRole = strtoupper(trim((string) $request->request->get('role', 'USER')));
        $allowedRoles = ['USER', 'VIP', 'AGENT', 'ADMIN'];
        if (!in_array($newRole, $allowedRoles, true)) {
            $request->getSession()->getFlashBag()->add('error', 'Role utilisateur invalide.');

            return $this->redirectBackToDashboard($request);
        }

        if ($newRole === strtoupper((string) ($user['role'] ?? 'USER'))) {
            $request->getSession()->getFlashBag()->add('info', 'Aucun changement de role a appliquer.');

            return $this->redirectBackToDashboard($request, 'users', $id);
        }

        if (!$this->userRepository->updateUserRole((string) ($user['email'] ?? ''), $newRole)) {
            $request->getSession()->getFlashBag()->add('error', 'Impossible de mettre a jour ce role pour le moment.');

            return $this->redirectBackToDashboard($request);
        }

        $admin = $this->getAuthenticatedUser($request);
        $this->notificationService->notifyUser(
            (string) ($user['email'] ?? ''),
            $newRole,
            (string) ($admin['email'] ?? ''),
            (string) ($admin['role'] ?? 'ADMIN'),
            'ACCOUNT',
            'Votre role EasyTravel a ete mis a jour',
            'Votre compte est desormais configure avec le role '.$newRole.'.'
        );

        $request->getSession()->getFlashBag()->add('success', $user['display_name'].' est maintenant '.$newRole.'.');

        return $this->redirectBackToDashboard($request, 'users', $id);
    }

    #[Route('/admin/users/{id}/delete', name: 'app_admin_user_delete', methods: ['POST'])]
    public function deleteUser(int $id, Request $request): RedirectResponse
    {
        if ($redirect = $this->ensureAdminAccess($request)) {
            return $redirect;
        }

        $user = $this->userRepository->getById($id);
        if ($user === null) {
            $request->getSession()->getFlashBag()->add('error', 'Compte utilisateur introuvable.');

            return $this->redirectBackToDashboard($request);
        }

        if ($this->isAdminRole((string) ($user['role'] ?? 'USER'))) {
            $request->getSession()->getFlashBag()->add('error', "La suppression d'un compte admin n'est pas autorisee depuis cette section.");

            return $this->redirectBackToDashboard($request);
        }

        if (!$this->userRepository->deleteUserAccount((string) ($user['email'] ?? ''))) {
            $request->getSession()->getFlashBag()->add('error', 'Impossible de supprimer ce compte pour le moment.');

            return $this->redirectBackToDashboard($request);
        }

        $request->getSession()->getFlashBag()->add('success', $user['display_name'].' a ete supprime.');

        return $this->redirectBackToDashboard($request, 'users');
    }

    private function ensureAdminAccess(Request $request): ?RedirectResponse
    {
        $user = $this->getAuthenticatedUser($request);
        if ($user === null) {
            return $this->redirectToRoute('app_login');
        }

        if (!$this->isAdminRole((string) ($user['role'] ?? 'USER'))) {
            return $this->redirectToRoute('app_dashboard');
        }

        return null;
    }

    private function getAuthenticatedUser(Request $request): ?array
    {
        $user = $request->getSession()->get('auth_user');

        return is_array($user) && trim((string) ($user['email'] ?? '')) !== '' ? $user : null;
    }

    private function syncSessionUser(Request $request, array $user): void
    {
        $request->getSession()->set('auth_user', [
            'id' => (int) ($user['id'] ?? 0),
            'display_name' => (string) ($user['display_name'] ?? trim(((string) ($user['prenom'] ?? '')).' '.((string) ($user['nom'] ?? '')))),
            'email' => (string) ($user['email'] ?? ''),
            'role' => (string) ($user['role'] ?? 'ADMIN'),
            'photo_url' => (string) ($user['photo_url'] ?? ''),
        ]);
    }

    private function redirectBackToDashboard(
        Request $request,
        string $defaultSection = 'users',
        ?int $focusUserId = null,
        ?int $focusReclamationId = null,
        ?bool $notificationDrawerOpen = null,
        array $extraParams = []
    ): RedirectResponse
    {
        $section = $this->sanitizeSection((string) $request->request->get('section', $defaultSection));
        $userFilter = $this->sanitizeUserFilter((string) $request->request->get('user_filter', 'all'));
        $usersSearch = trim((string) $request->request->get('users_search', ''));
        $focusUserId ??= max(0, (int) $request->request->get('focus_user', 0));
        $focusReclamationId ??= max(0, (int) $request->request->get('focus_reclamation', 0));

        $params = ['section' => $section];
        if ($section === 'users') {
            $params['user_filter'] = $userFilter;
            if ($usersSearch !== '') {
                $params['users_search'] = $usersSearch;
            }
            if ($focusUserId !== null && $focusUserId > 0) {
                $params['focus_user'] = $focusUserId;
            }
        }
        if ($section === 'reclamations' && $focusReclamationId !== null && $focusReclamationId > 0) {
            $params['focus_reclamation'] = $focusReclamationId;
        }
        $notificationDrawerOpen ??= $this->shouldOpenNotificationDrawer((string) $request->request->get('notifications', ''));
        if ($notificationDrawerOpen) {
            $params['notifications'] = 'open';
        }
        foreach ($extraParams as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $params[(string) $key] = $value;
        }

        return $this->redirectToRoute('app_admin_dashboard', $params);
    }

    private function sanitizeSection(string $section): string
    {
        $section = strtolower(trim($section));
        if ($section === 'factures') {
            return 'paiements';
        }
        if ($section === 'activites') {
            return 'overview';
        }

        $allowed = [
            'overview',
            'users',
            'destinations',
            'packages',
            'map',
            'atmospheres',
            'reservations',
            'reclamations',
            'paiements',
        ];

        return in_array($section, $allowed, true) ? $section : 'overview';
    }

    private function sanitizeUserFilter(string $filter): string
    {
        $allowed = ['all', 'active', 'pending', 'premium'];
        $filter = strtolower(trim($filter));

        return in_array($filter, $allowed, true) ? $filter : 'all';
    }

    private function sanitizeReclamationStatus(string $status): string
    {
        $allowed = ['EN_ATTENTE', 'EN_COURS', 'RESOLUE', 'REJETEE'];
        $status = strtoupper(trim($status));

        return in_array($status, $allowed, true) ? $status : 'EN_ATTENTE';
    }

    private function resolvePhotoUrlForView(string $photoPath): string
    {
        $photoPath = trim($photoPath);
        if ($photoPath === '') {
            return '';
        }

        if (
            str_starts_with($photoPath, '/')
            || str_starts_with($photoPath, 'http://')
            || str_starts_with($photoPath, 'https://')
            || str_starts_with($photoPath, 'data:')
        ) {
            return $photoPath;
        }

        if ($this->profilePhotoStorageService->resolveReadablePath($photoPath) === null) {
            return '';
        }

        return $this->generateUrl('app_profile_photo', [
            'reference' => $this->profilePhotoStorageService->encodePhotoReference($photoPath),
        ]);
    }

    private function shouldOpenNotificationDrawer(string $value): bool
    {
        return in_array(strtolower(trim($value)), ['1', 'true', 'open'], true);
    }

    private function isAdminRole(string $role): bool
    {
        return in_array(strtoupper(trim($role)), ['ADMIN', 'SUPER_ADMIN'], true);
    }

    private function extractFeaturedDestinationPayload(Request $request): array
    {
        return [
            'destination_name' => trim((string) $request->request->get('destination_name', '')),
            'country' => trim((string) $request->request->get('country', '')),
            'continent' => trim((string) $request->request->get('continent', '')),
            'description' => trim((string) $request->request->get('description', '')),
            'video_path' => trim((string) $request->request->get('video_path', '')),
            'avg_price' => (string) $request->request->get('avg_price', '0'),
            'best_season' => trim((string) $request->request->get('best_season', '')),
            'travel_types' => trim((string) $request->request->get('travel_types', '')),
            'interests' => trim((string) $request->request->get('interests', '')),
            'is_featured' => $request->request->getBoolean('is_featured'),
        ];
    }

    private function extractAtmospherePayload(Request $request): array
    {
        $payload = [];

        foreach ([
            'atmosphere_type',
            'title',
            'description',
            'video_path',
            'ai_interest_tags',
            'ai_suggested_destinations',
            'ai_suggested_countries',
            'ai_suggested_continents',
            'ai_featured_payload',
            'ai_score',
            'avg_price',
            'display_order',
        ] as $field) {
            if ($request->request->has($field)) {
                $payload[$field] = trim((string) $request->request->get($field, ''));
            }
        }

        if ($request->request->has('is_active')) {
            $payload['is_active'] = $request->request->getBoolean('is_active');
        }

        return $payload;
    }

    private function extractReservationDraftPayload(Request $request): array
    {
        return [
            'destination_id' => max(0, (int) $request->request->get('destination_id', 0)),
            'client_nom' => trim((string) $request->request->get('client_nom', '')),
            'client_email' => trim((string) $request->request->get('client_email', '')),
            'date_debut' => trim((string) $request->request->get('date_debut', '')),
            'date_fin' => trim((string) $request->request->get('date_fin', '')),
            'nb_adultes' => max(1, (int) $request->request->get('nb_adultes', 2)),
            'nb_enfants' => max(0, (int) $request->request->get('nb_enfants', 0)),
            'prix_total' => trim((string) $request->request->get('prix_total', '0')),
        ];
    }

    private function sanitizeReservationDecisionReason(string $reason): string
    {
        $reason = trim($reason);

        return $reason !== '' ? $reason : 'Refusee par l administration.';
    }

    private function csvValue(string $value): string
    {
        $value = str_replace(["\r", "\n", ';'], [' ', ' ', ','], trim($value));

        return $value;
    }

    private function extractTravelPackagePayload(Request $request): array
    {
        return [
            'package_name' => trim((string) $request->request->get('package_name', '')),
            'destinations' => trim((string) $request->request->get('destinations', '')),
            'continent' => trim((string) $request->request->get('continent', '')),
            'duration_days' => (string) $request->request->get('duration_days', '1'),
            'price_from' => (string) $request->request->get('price_from', '0'),
            'price_to' => (string) $request->request->get('price_to', '0'),
            'badge' => trim((string) $request->request->get('badge', 'Nouveau')),
            'description' => trim((string) $request->request->get('description', '')),
            'travel_type' => trim((string) $request->request->get('travel_type', 'couple')),
            'interests' => trim((string) $request->request->get('interests', '')),
            'includes' => trim((string) $request->request->get('includes', '')),
            'best_period' => trim((string) $request->request->get('best_period', '')),
            'is_active' => $request->request->getBoolean('is_active'),
        ];
    }

    private function buildPaymentsAdminData(array $payments, array $invoices): array
    {
        $stats = $this->paiementRepository->getStats();
        $invoiceByPayment = [];
        foreach ($invoices as $invoice) {
            $paymentId = (int) ($invoice['paiement_id'] ?? 0);
            if ($paymentId <= 0 || isset($invoiceByPayment[$paymentId])) {
                continue;
            }

            $invoiceByPayment[$paymentId] = $invoice;
        }

        $pendingReview = 0;
        $entries = [];
        foreach ($payments as $index => $payment) {
            $status = strtoupper(trim((string) ($payment['statut'] ?? 'PAYE')));
            if ($status !== 'PAYE') {
                ++$pendingReview;
            }

            $paymentId = (int) ($payment['id'] ?? 0);
            $entries[] = [
                ...$payment,
                'display_rank' => str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT),
                'status_label' => $this->formatAdminStatusLabel($status),
                'status_class' => $this->resolvePaymentStatusClass($status),
                'code_class' => $this->resolvePaymentCodeClass($status),
                'date_label' => $this->formatAdminDateLabel((string) ($payment['date_paiement'] ?? '')),
                'summary_copy' => $this->buildPaymentSummaryCopy($payment),
                'invoice_id' => (int) ($invoiceByPayment[$paymentId]['id'] ?? 0),
                'invoice_number' => (string) ($invoiceByPayment[$paymentId]['numero_facture'] ?? ''),
            ];
        }

        $stats['pending_review'] = $pendingReview;

        return [
            'stats' => $stats,
            'entries' => $entries,
            'invoice_by_payment' => $invoiceByPayment,
        ];
    }

    private function buildInvoicesAdminData(array $invoices, array $paidPayments): array
    {
        $stats = $this->factureRepository->getStats();
        $paymentOptions = [];
        $paymentsById = [];

        foreach ($paidPayments as $payment) {
            $paymentId = (int) ($payment['id'] ?? 0);
            if ($paymentId <= 0) {
                continue;
            }

            $paymentsById[$paymentId] = $payment;
            $paymentOptions[] = [
                ...$payment,
                'date_label' => $this->formatAdminDateLabel((string) ($payment['date_paiement'] ?? '')),
                'label' => trim((string) ($payment['client_nom'] ?? 'Client'))
                    .' - '
                    .trim((string) ($payment['destination'] ?? 'Destination'))
                    .' ('
                    .number_format((float) ($payment['montant'] ?? 0), 2, '.', ' ')
                    .' EUR)',
            ];
        }

        $entries = [];
        foreach ($invoices as $index => $invoice) {
            $status = strtoupper(trim((string) ($invoice['statut'] ?? 'GENEREE')));
            $paymentId = (int) ($invoice['paiement_id'] ?? 0);
            $linkedPayment = $paymentsById[$paymentId] ?? null;

            $entries[] = [
                ...$invoice,
                'display_rank' => str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT),
                'status_label' => $this->formatAdminStatusLabel($status),
                'status_class' => $this->resolveInvoiceStatusClass($status),
                'code_class' => $this->resolveInvoiceCodeClass($status),
                'date_label' => $this->formatAdminDateLabel((string) ($invoice['date_emission'] ?? ''), 'd/m/Y'),
                'payment_reference' => (string) ($linkedPayment['reference_transaction'] ?? ''),
                'payment_amount' => (float) ($linkedPayment['montant'] ?? 0),
            ];
        }

        return [
            'stats' => $stats,
            'entries' => $entries,
            'payment_options' => $paymentOptions,
        ];
    }

    private function extractDashboardPaymentPayload(Request $request): array
    {
        return [
            'id' => max(0, (int) $request->request->get('id', 0)),
            'client_nom' => trim((string) $request->request->get('client_nom', '')),
            'destination' => trim((string) $request->request->get('destination', '')),
            'montant' => round((float) $request->request->get('montant', 0), 2),
            'date_paiement' => $this->normalizeDashboardDateTimeInput((string) $request->request->get('date_paiement', '')),
            'statut' => strtoupper(trim((string) $request->request->get('statut', 'PAYE'))),
            'reference_transaction' => trim((string) $request->request->get('reference_transaction', '')),
            'package_id' => max(0, (int) $request->request->get('package_id', 0)),
            'numero_carte_masque' => trim((string) $request->request->get('numero_carte_masque', '')),
            'type_voyage' => trim((string) $request->request->get('type_voyage', 'Aventure')),
        ];
    }

    private function validateDashboardPaymentPayload(array $payload): ?string
    {
        if (trim((string) ($payload['client_nom'] ?? '')) === '') {
            return 'Le nom client est obligatoire.';
        }

        if (trim((string) ($payload['destination'] ?? '')) === '') {
            return 'La destination est obligatoire.';
        }

        if ((float) ($payload['montant'] ?? 0) <= 0) {
            return 'Le montant doit etre superieur a 0.';
        }

        if (trim((string) ($payload['date_paiement'] ?? '')) === '') {
            return 'La date de paiement est obligatoire.';
        }

        return null;
    }

    private function extractDashboardInvoicePayload(Request $request): array
    {
        return [
            'id' => max(0, (int) $request->request->get('id', 0)),
            'paiement_id' => max(0, (int) $request->request->get('paiement_id', 0)),
            'numero_facture' => trim((string) $request->request->get('numero_facture', '')),
            'date_emission' => trim((string) $request->request->get('date_emission', date('Y-m-d'))),
            'client_nom' => trim((string) $request->request->get('client_nom', '')),
            'client_email' => trim((string) $request->request->get('client_email', '')),
            'client_adresse' => trim((string) $request->request->get('client_adresse', '')),
            'destination' => trim((string) $request->request->get('destination', '')),
            'date_debut' => trim((string) $request->request->get('date_debut', '')),
            'date_fin' => trim((string) $request->request->get('date_fin', '')),
            'nb_personnes' => max(1, (int) $request->request->get('nb_personnes', 1)),
            'montant_transport' => round((float) $request->request->get('montant_transport', 0), 2),
            'montant_hebergement' => round((float) $request->request->get('montant_hebergement', 0), 2),
            'montant_activites' => round((float) $request->request->get('montant_activites', 0), 2),
            'montant_total' => round((float) $request->request->get('montant_total', 0), 2),
            'statut' => strtoupper(trim((string) $request->request->get('statut', 'GENEREE'))),
            'type_voyage' => trim((string) $request->request->get('type_voyage', '')),
        ];
    }

    private function hydrateDashboardInvoicePayload(array $payload): array
    {
        $paymentId = (int) ($payload['paiement_id'] ?? 0);
        if ($paymentId <= 0) {
            return $payload;
        }

        $payment = $this->paiementRepository->find($paymentId);
        if ($payment === null) {
            return $payload;
        }

        $split = $this->splitDashboardInvoiceAmounts((float) ($payment['montant'] ?? 0));

        if (trim((string) ($payload['client_nom'] ?? '')) === '') {
            $payload['client_nom'] = (string) ($payment['client_nom'] ?? '');
        }
        if (trim((string) ($payload['destination'] ?? '')) === '') {
            $payload['destination'] = (string) ($payment['destination'] ?? '');
        }
        if (trim((string) ($payload['type_voyage'] ?? '')) === '') {
            $payload['type_voyage'] = (string) ($payment['type_voyage'] ?? 'Aventure');
        }
        if ((float) ($payload['montant_transport'] ?? 0) <= 0) {
            $payload['montant_transport'] = $split['transport'];
        }
        if ((float) ($payload['montant_hebergement'] ?? 0) <= 0) {
            $payload['montant_hebergement'] = $split['hebergement'];
        }
        if ((float) ($payload['montant_activites'] ?? 0) < 0) {
            $payload['montant_activites'] = $split['activites'];
        }

        return $payload;
    }

    private function validateDashboardInvoicePayload(array $payload): ?string
    {
        if (trim((string) ($payload['client_nom'] ?? '')) === '') {
            return 'Veuillez entrer le nom du client.';
        }

        if (trim((string) ($payload['client_email'] ?? '')) === '') {
            return 'Veuillez entrer l email du client.';
        }

        if (!LegacyValidator::isValidEmail((string) ($payload['client_email'] ?? ''))) {
            return 'Veuillez entrer un email client valide.';
        }

        if (trim((string) ($payload['destination'] ?? '')) === '') {
            return 'Veuillez entrer la destination.';
        }

        if (trim((string) ($payload['date_debut'] ?? '')) === '' || trim((string) ($payload['date_fin'] ?? '')) === '') {
            return 'Veuillez renseigner les dates du voyage.';
        }

        if (
            (float) ($payload['montant_transport'] ?? 0) <= 0
            || (float) ($payload['montant_hebergement'] ?? 0) <= 0
            || (float) ($payload['montant_activites'] ?? 0) < 0
        ) {
            return 'Les montants de facture sont invalides.';
        }

        return null;
    }

    private function computeDashboardInvoiceTotal(array $payload): float
    {
        return round(
            (float) ($payload['montant_transport'] ?? 0)
            + (float) ($payload['montant_hebergement'] ?? 0)
            + (float) ($payload['montant_activites'] ?? 0),
            2
        );
    }

    private function splitDashboardInvoiceAmounts(float $total): array
    {
        $transport = round($total * 0.40, 2);
        $hebergement = round($total * 0.45, 2);
        $activites = round(max(0, $total - $transport - $hebergement), 2);

        return [
            'transport' => $transport,
            'hebergement' => $hebergement,
            'activites' => $activites,
        ];
    }

    private function normalizeDashboardDateTimeInput(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        return str_contains($value, 'T') ? str_replace('T', ' ', $value).':00' : $value;
    }

    private function formatAdminDateLabel(?string $value, string $format = 'd/m/Y H:i'): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return 'Non renseigne';
        }

        try {
            return (new DateTimeImmutable($value))->format($format);
        } catch (\Throwable) {
            return $value;
        }
    }

    private function formatAdminStatusLabel(string $status): string
    {
        return str_replace('_', ' ', strtoupper(trim($status)));
    }

    private function resolvePaymentStatusClass(string $status): string
    {
        return match (strtoupper(trim($status))) {
            'PAYE' => 'admin-status-pill-green',
            'REMBOURSE', 'ANNULE', 'REFUSE' => 'admin-status-pill-red',
            'EN_ATTENTE', 'A_REVOIR', 'EN_COURS' => 'admin-status-pill-orange',
            default => 'admin-status-pill-blue',
        };
    }

    private function resolvePaymentCodeClass(string $status): string
    {
        return match (strtoupper(trim($status))) {
            'PAYE' => 'admin-booking-code-green',
            'REMBOURSE', 'ANNULE', 'REFUSE' => 'admin-booking-code-red',
            'EN_ATTENTE', 'A_REVOIR', 'EN_COURS' => 'admin-booking-code-orange',
            default => 'admin-booking-code-blue',
        };
    }

    private function resolveInvoiceStatusClass(string $status): string
    {
        return match (strtoupper(trim($status))) {
            'ENVOYEE' => 'admin-status-pill-green',
            'ANNULEE' => 'admin-status-pill-red',
            'PREVIEW' => 'admin-status-pill-blue',
            default => 'admin-status-pill-orange',
        };
    }

    private function resolveInvoiceCodeClass(string $status): string
    {
        return match (strtoupper(trim($status))) {
            'ENVOYEE' => 'admin-booking-code-green',
            'ANNULEE' => 'admin-booking-code-red',
            'PREVIEW' => 'admin-booking-code-blue',
            default => 'admin-booking-code-violet',
        };
    }

    private function buildPaymentSummaryCopy(array $payment): string
    {
        $reference = trim((string) ($payment['reference_transaction'] ?? 'Reference indisponible'));
        $card = trim((string) ($payment['numero_carte_masque'] ?? 'Carte non renseignee'));

        return 'Ref: '.$reference.' | Carte: '.$card;
    }

    private function formatOverviewCount(float|int|string $value): string
    {
        $value = (float) $value;
        if ($value >= 1000) {
            return number_format($value / 1000, 1, '.', ' ').'K';
        }

        return number_format($value, 0, '.', ' ');
    }

    private function formatOverviewCurrency(float|int|string $value): string
    {
        $value = (float) $value;
        if ($value >= 1000000) {
            return 'EUR'.number_format($value / 1000000, 1, '.', ' ').'M';
        }
        if ($value >= 1000) {
            return 'EUR'.number_format($value / 1000, 1, '.', ' ').'K';
        }

        return 'EUR'.number_format($value, 0, '.', ' ');
    }

    private function formatOverviewChange(float|int|string $value): string
    {
        return sprintf('%+.1f%%', (float) $value);
    }

    private function resolveOverviewChangeTone(float|int|string $value): string
    {
        return (float) $value < 0 ? 'negative' : 'positive';
    }

    private function buildOverviewMiniBarHeights(array $series, float $baseHeight, float $extraHeight): array
    {
        $maxValue = 0.0;
        foreach ($series as $value) {
            $maxValue = max($maxValue, (float) $value);
        }

        if ($maxValue <= 0.0) {
            return array_fill(0, max(1, count($series)), $baseHeight + ($extraHeight * 0.45));
        }

        return array_map(
            static fn (float|int|string $value): float => $baseHeight + ((((float) $value) / $maxValue) * $extraHeight),
            $series
        );
    }

    private function extractMapDestinationPayload(Request $request): array
    {
        $payload = [];

        foreach ([
            'city',
            'country',
            'continent',
            'package_name',
            'duration',
            'price',
            'original_price',
            'image_path',
            'description',
            'best_period',
            'includes',
            'highlight_1',
            'highlight_2',
            'highlight_3',
            'x_percent',
            'y_percent',
            'ai_score',
            'display_order',
        ] as $field) {
            if ($request->request->has($field)) {
                $payload[$field] = trim((string) $request->request->get($field, ''));
            }
        }

        if ($request->request->has('ai_recommended')) {
            $payload['ai_recommended'] = $request->request->getBoolean('ai_recommended');
        }

        if ($request->request->has('is_active')) {
            $payload['is_active'] = $request->request->getBoolean('is_active');
        }

        return $payload;
    }

    private function extractSponsorPayload(Request $request): array
    {
        return [
            'nom' => trim((string) $request->request->get('nom', '')),
            'logo_url' => trim((string) $request->request->get('logo_url', '')),
            'description' => trim((string) $request->request->get('description', '')),
            'site_web' => trim((string) $request->request->get('site_web', '')),
            'type' => trim((string) $request->request->get('type', 'Partenaire')),
            'montant_sponsoring' => (string) $request->request->get('montant_sponsoring', '0'),
            'date_debut' => trim((string) $request->request->get('date_debut', '')),
            'date_fin' => trim((string) $request->request->get('date_fin', '')),
            'est_actif' => $request->request->getBoolean('est_actif', true),
        ];
    }

    private function validateSponsorLinks(array $payload, bool $hasUploadedLogo): ?string
    {
        $siteWeb = trim((string) ($payload['site_web'] ?? ''));
        if ($siteWeb === '') {
            return 'Le lien du sponsor est obligatoire.';
        }

        if (!$this->isValidHttpUrl($siteWeb)) {
            return 'Le lien du sponsor doit etre une URL valide qui commence par http:// ou https://.';
        }

        $logoUrl = trim((string) ($payload['logo_url'] ?? ''));
        if ($logoUrl === '' && !$hasUploadedLogo) {
            return 'Le logo sponsor est obligatoire: ajoutez une image ou une URL de logo valide.';
        }

        if ($logoUrl !== '' && !$this->isValidSponsorLogoUrl($logoUrl)) {
            return 'L URL du logo sponsor doit etre une URL http(s) valide ou un chemin local qui commence par /.';
        }

        return null;
    }

    private function normalizeSponsorWebsite(string $url): string
    {
        $url = trim($url);
        if ($url === '' || preg_match('/^https?:\/\//i', $url) === 1) {
            return $url;
        }

        return 'https://'.$url;
    }

    private function isValidSponsorLogoUrl(string $url): bool
    {
        $url = trim($url);
        if ($url === '') {
            return false;
        }

        if (str_starts_with($url, '/')) {
            return !str_starts_with($url, '//') && !str_contains($url, '..') && !preg_match('/\s/', $url);
        }

        return $this->isValidHttpUrl($url);
    }

    private function isValidHttpUrl(string $url): bool
    {
        $url = trim($url);
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $scheme = strtolower((string) (parse_url($url, PHP_URL_SCHEME) ?? ''));
        $host = trim((string) (parse_url($url, PHP_URL_HOST) ?? ''));

        return in_array($scheme, ['http', 'https'], true) && $host !== '';
    }

    private function consumeFlash(Request $request, string $type): ?string
    {
        $messages = $request->getSession()->getFlashBag()->get($type);

        return $messages !== [] ? implode(' ', $messages) : null;
    }
}
