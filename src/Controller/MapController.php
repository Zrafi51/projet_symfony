<?php

namespace App\Controller;

use App\Repository\FollowRepository;
use App\Repository\LocationPinRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\UX\Map\InfoWindow;
use Symfony\UX\Map\Map;
use Symfony\UX\Map\Marker;
use Symfony\UX\Map\Point;

/**
 * Instagram-style "Carte" — a shared map showing pins from the people you
 * follow + your own pin. Pins expire after the user-chosen duration (1h/4h/
 * 8h/24h).
 *
 * Now built on the official symfony/ux-leaflet-map bundle: the Map object is
 * built server-side and rendered with the {{ ux_map(...) }} Twig helper.
 *
 * Routes:
 *   - GET  /map          → map page
 *   - POST /map/pin      → create/update my pin (lat,lng,ttlHours)
 *   - POST /map/unpin    → remove my pin
 */
class MapController extends AbstractController
{
    #[Route('/map', name: 'app_map', methods: ['GET'])]
    public function index(
        FollowRepository      $follows,
        LocationPinRepository $pins,
        UserRepository        $users
    ): Response {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }
        $me = $user->getUserIdentifier();

        $pins->purgeExpired();

        $following = $follows->getFollowingUsernames($me);
        $scope = array_values(array_unique(array_merge($following, [$me])));

        $active = $pins->findActiveForUsers($scope);
        $photoMap = $users->getPhotoMapByUsernames(array_map(
            fn ($p) => $p->getUsername(), $active
        ));

        $mine = $pins->findActiveFor($me);

        // Build the UX Map. Default view = roughly Tunisia; if the viewer has
        // an active pin we centre on it instead.
        $center = new Point(34.0, 9.5);
        $zoom = 6.0;
        if ($mine) {
            $center = new Point($mine->getLatitude(), $mine->getLongitude());
            $zoom = 10.0;
        }

        $map = (new Map())
            ->center($center)
            ->zoom($zoom);

        foreach ($active as $p) {
            $username = $p->getUsername();
            $expiresAt = $p->getExpiresAt();
            $isMine = $username === $me;

            $info = sprintf(
                '<strong>%s</strong>%s<div class="small text-muted">Visible jusqu\'à %s</div>',
                htmlspecialchars($username, ENT_QUOTES, 'UTF-8'),
                $p->getLabel()
                    ? '<div class="fst-italic text-muted">'
                        . htmlspecialchars($p->getLabel(), ENT_QUOTES, 'UTF-8')
                        . '</div>'
                    : '',
                $expiresAt->format('H:i')
            );

            $map->addMarker(new Marker(
                position: new Point($p->getLatitude(), $p->getLongitude()),
                title: $username,
                infoWindow: new InfoWindow(content: $info, opened: false),
                extra: [
                    'username'  => $username,
                    'label'     => $p->getLabel(),
                    'photo'     => $photoMap[$username] ?? null,
                    'isMine'    => $isMine,
                    'expiresAt' => $expiresAt->format(\DateTimeInterface::ATOM),
                ],
            ));
        }

        return $this->render('map/index.html.twig', [
            'map'  => $map,
            'mine' => $mine,
        ]);
    }

    #[Route('/map/pin', name: 'app_map_pin', methods: ['POST'])]
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

    #[Route('/map/unpin', name: 'app_map_unpin', methods: ['POST'])]
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
