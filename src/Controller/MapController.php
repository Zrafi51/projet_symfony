<?php

namespace App\Controller;

use App\Repository\FollowRepository;
use App\Repository\LocationPinRepository;
use App\Repository\ForumUserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Instagram-style "Carte" — a shared map showing pins from the people you
 * follow + your own pin. Pins expire after the user-chosen duration (1h/4h/
 * 8h/24h). Uses Leaflet.js (OpenStreetMap tiles) — free, no API key.
 *
 * Routes:
 *   - GET  /map          → map page
 *   - POST /map/pin      → create/update my pin (lat,lng,ttlHours)
 *   - POST /map/unpin    → remove my pin
 */
class MapController extends AbstractController
{
    #[Route('/social/map', name: 'forum_map', methods: ['GET'])]
    public function index(
        FollowRepository      $follows,
        LocationPinRepository $pins,
        ForumUserRepository   $users
    ): Response {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('forum_login');
        }
        $me = $user->getUserIdentifier();

        // Opportunistic cleanup so stale rows don't linger forever.
        $pins->purgeExpired();

        // Friends = people I follow + me. Self is included so my own pin
        // renders on the map too.
        $following = $follows->getFollowingUsernames($me);
        $scope = array_values(array_unique(array_merge($following, [$me])));

        $active = $pins->findActiveForUsers($scope);

        // Build a lightweight JSON-able list for the Leaflet JS bootstrap.
        $photoMap = $users->getPhotoMapByUsernames(array_map(
            fn ($p) => $p->getUsername(), $active
        ));
        $markers = [];
        foreach ($active as $p) {
            $markers[] = [
                'username'  => $p->getUsername(),
                'lat'       => $p->getLatitude(),
                'lng'       => $p->getLongitude(),
                'label'     => $p->getLabel(),
                'photo'     => $photoMap[$p->getUsername()] ?? null,
                'expiresAt' => $p->getExpiresAt()->format(\DateTimeInterface::ATOM),
                'isMine'    => $p->getUsername() === $me,
            ];
        }

        $mine = $pins->findActiveFor($me);

        return $this->render('social/map/index.html.twig', [
            'markers' => $markers,
            'mine'    => $mine,
        ]);
    }

    #[Route('/social/map/pin', name: 'forum_map_pin', methods: ['POST'])]
    public function pin(Request $req, LocationPinRepository $pins): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['ok' => false, 'error' => 'auth'], 401);
        }
        if (!$this->isCsrfTokenValid('map_pin', (string) $req->request->get('_token'))) {
            return new JsonResponse(['ok' => false, 'error' => 'csrf'], 400);
        }
        $lat = (float) $req->request->get('lat');
        $lng = (float) $req->request->get('lng');
        $ttl = (int) $req->request->get('ttlHours', 4);
        $label = trim((string) $req->request->get('label', ''));
        $label = $label !== '' ? mb_substr($label, 0, 120) : null;

        // Sanity check — valid WGS84 range; fallback to 4h if ttl is off.
        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            return new JsonResponse(['ok' => false, 'error' => 'coords'], 400);
        }
        $allowed = [1, 4, 8, 24];
        if (!in_array($ttl, $allowed, true)) {
            $ttl = 4;
        }

        $expires = (new \DateTime())->modify("+{$ttl} hours");
        $pin = $pins->upsertFor($user->getUserIdentifier(), $lat, $lng, $expires, $label);

        return new JsonResponse([
            'ok'        => true,
            'lat'       => $pin->getLatitude(),
            'lng'       => $pin->getLongitude(),
            'expiresAt' => $pin->getExpiresAt()->format(\DateTimeInterface::ATOM),
        ]);
    }

    #[Route('/social/map/unpin', name: 'forum_map_unpin', methods: ['POST'])]
    public function unpin(Request $req, LocationPinRepository $pins): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['ok' => false, 'error' => 'auth'], 401);
        }
        if (!$this->isCsrfTokenValid('map_pin', (string) $req->request->get('_token'))) {
            return new JsonResponse(['ok' => false, 'error' => 'csrf'], 400);
        }
        $pins->removeFor($user->getUserIdentifier());
        return new JsonResponse(['ok' => true]);
    }
}
