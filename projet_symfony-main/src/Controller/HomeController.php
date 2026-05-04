<?php

namespace App\Controller;

use App\Repository\ActiviteRepository;
use App\Repository\AdminDashboardRepository;
use App\Repository\DestinationRepository;
use App\View\PhpTemplateRenderer;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    public function __construct(
        private readonly PhpTemplateRenderer $renderer,
        private readonly DestinationRepository $destinationRepository,
        private readonly ActiviteRepository $activiteRepository,
        private readonly AdminDashboardRepository $adminDashboardRepository,
    ) {
    }

    #[Route('/', name: 'app_home', methods: ['GET'])]
    #[Route('/accueil', name: 'app_home_accueil', methods: ['GET'])]
    #[Route('/home', name: 'app_home_alias', methods: ['GET'])]
    public function index(): Response
    {
        $databaseError = null;
        $stats = [
            'destinations' => 0,
            'activites' => 0,
        ];
        $previewDestinations = [];
        $homeSponsors = [];
        $homeAtmospheres = [];
        $homeMapPoints = [];

        try {
            $stats['destinations'] = $this->destinationRepository->count();
            $stats['activites'] = $this->activiteRepository->count();
            $previewDestinations = $this->adminDashboardRepository->findFeaturedDestinationsForHome(6);
            if ($previewDestinations === []) {
                $previewDestinations = $this->destinationRepository->findLatest(6);
            }
            $homeSponsors = $this->adminDashboardRepository->findActiveSponsorsForHome(8);
            $homeAtmospheres = $this->adminDashboardRepository->findActiveAtmospheresForHome(4);
            $homeMapPoints = $this->adminDashboardRepository->findActiveMapDestinationsForHome(11);
        } catch (RuntimeException $exception) {
            $databaseError = $exception->getMessage();
        }

        return new Response($this->renderer->render('home/index', [
            'title' => 'EasyTravel - Accueil',
            'databaseError' => $databaseError,
            'stats' => $stats,
            'previewDestinations' => $previewDestinations,
            'homeSponsors' => $homeSponsors,
            'homeAtmospheres' => $homeAtmospheres,
            'homeMapPoints' => $homeMapPoints,
        ]));
    }
}
