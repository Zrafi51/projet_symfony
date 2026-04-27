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
use Symfony\UX\Map\InfoWindow;
use Symfony\UX\Map\Map;
use Symfony\UX\Map\Marker;
use Symfony\UX\Map\Point;

/**
 * Carte style Instagram — alimentée par le bundle officiel Symfony UX Map
 * (avec le bridge Leaflet + tuiles OpenStreetMap, gratuit et sans clé API).
 *
 * Le controller construit un objet Symfony\UX\Map\Map côté serveur en y
 * ajoutant un Marker (avec InfoWindow) pour chaque pin actif. Le rendu HTML
 * est ensuite délégué à la fonction Twig {{ ux_map(map: map) }} fournie par
 * le bundle.
 *
 * Routes :
 *   - GET  /social/map         → page carte
 *   - POST /social/map/pin     → crée/met à jour mon pin (lat,lng,ttlHours)
 *   - POST /social/map/unpin   → supprime mon pin
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

        // Nettoyage opportuniste pour que les lignes obsolètes ne traînent pas.
        $pins->purgeExpired();

        // Amis = personnes que je suis + moi (mon pin doit aussi apparaître).
        $following = $follows->getFollowingUsernames($me);
        $scope = array_values(array_unique(array_merge($following, [$me])));
        $active = $pins->findActiveForUsers($scope);

        // Map photos pour les info windows.
        $photoMap = $users->getPhotoMapByUsernames(array_map(
            fn ($p) => $p->getUsername(), $active
        ));

        // ─── Construction de la carte avec le bundle Symfony UX Map ───
        // Centre par défaut = Tunisie (mêmes valeurs qu'avant).
        $defaultCenter = new Point(34.0, 9.5);
        $defaultZoom = 6;

        // Si j'ai déjà un pin, on centre dessus.
        $mine = $pins->findActiveFor($me);
        if ($mine) {
            $defaultCenter = new Point($mine->getLatitude(), $mine->getLongitude());
            $defaultZoom = 10;
        }

        $map = (new Map())
            ->center($defaultCenter)
            ->zoom($defaultZoom);

        // Ajout d'un Marker (avec InfoWindow) par pin actif.
        foreach ($active as $p) {
            $isMine = $p->getUsername() === $me;
            $expiresHHMM = $p->getExpiresAt()->format('H:i');
            $photoHtml = isset($photoMap[$p->getUsername()])
                ? '<img src="/uploads/profiles/' . htmlspecialchars($photoMap[$p->getUsername()]) . '" style="width:48px;height:48px;border-radius:50%;object-fit:cover;border:2px solid ' . ($isMine ? '#dc3545' : '#1877f2') . ';"><br>'
                : '';
            $labelHtml = $p->getLabel() ? '<div style="font-style:italic;color:#666;">' . htmlspecialchars($p->getLabel()) . '</div>' : '';

            $map->addMarker(new Marker(
                position: new Point($p->getLatitude(), $p->getLongitude()),
                title: $p->getUsername(),
                infoWindow: new InfoWindow(
                    headerContent: '<strong>' . htmlspecialchars($p->getUsername()) . '</strong>',
                    content: $photoHtml . $labelHtml . '<div style="font-size:0.85em;color:#666;">Visible jusqu\'à ' . $expiresHHMM . '</div>',
                ),
                extra: ['isMine' => $isMine, 'username' => $p->getUsername()],
            ));
        }

        return $this->render('social/map/index.html.twig', [
            'map'  => $map,   // ← objet Symfony\UX\Map\Map du bundle
            'mine' => $mine,
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
