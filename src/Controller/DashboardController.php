<?php

namespace App\Controller;

use App\Repository\FactureRepository;
use App\Repository\FavoriteRepository;
use App\Repository\PaiementRepository;
use App\Repository\SupportRepository;
use App\Repository\UserRepository;
use App\Repository\VoyageRepository;
use App\Service\ProfilePhotoStorageService;
use App\Service\UserNotificationService;
use App\View\PhpTemplateRenderer;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DashboardController extends AbstractController
{
    public function __construct(
        private readonly PhpTemplateRenderer $renderer,
        private readonly SupportRepository $supportRepository,
        private readonly UserRepository $userRepository,
        private readonly PaiementRepository $paiementRepository,
        private readonly VoyageRepository $voyageRepository,
        private readonly FactureRepository $factureRepository,
        private readonly FavoriteRepository $favoriteRepository,
        private readonly UserNotificationService $notificationService,
        private readonly ProfilePhotoStorageService $profilePhotoStorageService,
    ) {
    }

    #[Route('/dashboard', name: 'app_dashboard', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $user = $this->requireAuthenticatedUser($request);
        if ($user === null) {
            return $this->redirectToRoute('app_login');
        }

        if ($this->isAdminRole((string) ($user['role'] ?? 'USER'))) {
            return $this->redirectToRoute('app_admin_dashboard');
        }

        $refreshedUser = $this->userRepository->getByEmail((string) ($user['email'] ?? ''));
        if ($refreshedUser !== null) {
            $user = $refreshedUser;
            $this->syncSessionUser($request, $user);
        }

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
        $databaseError = null;
        $upcomingTrips = [];
        $historyTrips = [];
        $favoritePackages = [];
        $factures = [];
        $latestNotifications = [];
        $unreadNotificationCount = 0;
        $metrics = [
            'planned_trips' => 0,
            'budget_average' => 0,
            'history_trips' => 0,
            'tracked_destinations' => 0,
            'upcoming_count' => 0,
            'favorites_count' => 0,
        ];

        try {
            $supportSnapshot = $this->supportRepository->getUserSupportSnapshot((int) ($user['id'] ?? 0));

            $clientEmail = (string) ($user['email'] ?? '');
            $clientName = trim((string) ($user['prenom'] ?? '').' '.(string) ($user['nom'] ?? ''));
            $allVoyages = $this->voyageRepository->findByClientEmail($clientEmail);
            if ($allVoyages === []) {
                $allVoyages = $this->voyageRepository->findByClientName($clientName);
            }

            $allPayments = $this->paiementRepository->findByClientEmail($clientEmail);
            if ($allPayments === []) {
                $allPayments = $this->paiementRepository->findByClientName($clientName);
            }

            $today = new \DateTimeImmutable('today');
            if ($allVoyages !== []) {
                foreach ($allVoyages as $voyage) {
                    $tripEndDate = !empty($voyage['dateRetour']) ? new \DateTimeImmutable((string) $voyage['dateRetour']) : null;
                    if ($tripEndDate !== null && $tripEndDate >= $today) {
                        $upcomingTrips[] = $voyage;
                    } else {
                        $historyTrips[] = $voyage;
                    }
                }
            } else {
                foreach ($allPayments as $payment) {
                    $paymentDate = !empty($payment['date_paiement']) ? new \DateTimeImmutable((string) $payment['date_paiement']) : null;
                    if ($paymentDate !== null && $paymentDate > $today) {
                        $upcomingTrips[] = $payment;
                    } else {
                        $historyTrips[] = $payment;
                    }
                }
            }

            $factures = $this->factureRepository->findByClientEmail($clientEmail);
            $favoritePackages = $this->favoriteRepository->findByUser((int) ($user['id'] ?? 0));

            $metrics['planned_trips'] = count($upcomingTrips);
            $metrics['history_trips'] = count($historyTrips);
            $metrics['upcoming_count'] = count($upcomingTrips);
            $metrics['favorites_count'] = count($favoritePackages);
            $metrics['tracked_destinations'] = count($favoritePackages);

            if ($allVoyages !== []) {
                $totalBudget = array_sum(array_map(
                    static fn (array $voyage): float => (float) ($voyage['prix'] ?? 0),
                    $allVoyages
                ));
                $metrics['budget_average'] = $totalBudget / count($allVoyages);
            } elseif ($allPayments !== []) {
                $totalBudget = array_sum(array_column($allPayments, 'montant'));
                $metrics['budget_average'] = $totalBudget / count($allPayments);
            }

            if ($this->notificationService->isDatabaseAvailable()) {
                $latestNotifications = $this->notificationService->getLatestNotifications($clientEmail, 8);
                $unreadNotificationCount = $this->notificationService->getUnreadCount($clientEmail);
            }
        } catch (RuntimeException $exception) {
            $databaseError = $exception->getMessage();
        }

        $user['photo_display_url'] = $this->resolvePhotoUrlForView((string) ($user['photo_url'] ?? ''));

        return new Response($this->renderer->render('dashboard/index', [
            'title' => 'Dashboard Client',
            'currentUser' => $user,
            'dashboardType' => 'user',
            'databaseError' => $databaseError,
            'supportSnapshot' => $supportSnapshot,
            'upcomingTrips' => $upcomingTrips,
            'historyTrips' => $historyTrips,
            'favoritePackages' => $favoritePackages,
            'factures' => $factures,
            'metrics' => $metrics,
            'latestNotifications' => $latestNotifications,
            'unreadNotificationCount' => $unreadNotificationCount,
            'statusMessage' => $this->consumeFlash($request, 'success') ?? $this->consumeFlash($request, 'info'),
            'errorMessage' => $this->consumeFlash($request, 'error'),
        ]));
    }

    private function requireAuthenticatedUser(Request $request): ?array
    {
        $user = $request->getSession()->get('auth_user');

        return is_array($user) && trim((string) ($user['email'] ?? '')) !== '' ? $user : null;
    }

    private function isAdminRole(string $role): bool
    {
        return in_array(strtoupper(trim($role)), ['ADMIN', 'SUPER_ADMIN'], true);
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

    private function consumeFlash(Request $request, string $type): ?string
    {
        $messages = $request->getSession()->getFlashBag()->get($type);

        return $messages !== [] ? implode(' ', $messages) : null;
    }

    private function syncSessionUser(Request $request, array $user): void
    {
        $request->getSession()->set('auth_user', [
            'id' => (int) ($user['id'] ?? 0),
            'display_name' => trim((string) ($user['prenom'] ?? '').' '.(string) ($user['nom'] ?? '')) ?: (string) ($user['email'] ?? ''),
            'prenom' => (string) ($user['prenom'] ?? ''),
            'nom' => (string) ($user['nom'] ?? ''),
            'email' => (string) ($user['email'] ?? ''),
            'telephone' => (string) ($user['telephone'] ?? ''),
            'adresse' => (string) ($user['adresse'] ?? ''),
            'date_naissance' => $user['date_naissance'] ?? null,
            'role' => (string) ($user['role'] ?? 'USER'),
            'photo_url' => (string) ($user['photo_url'] ?? ''),
            'is_active' => (bool) ($user['is_active'] ?? true),
        ]);
    }
}
