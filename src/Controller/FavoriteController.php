<?php

namespace App\Controller;

use App\Repository\FavoriteRepository;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class FavoriteController extends AbstractController
{
    public function __construct(private readonly FavoriteRepository $favoriteRepository)
    {
    }

    #[Route('/favorites/toggle', name: 'app_favorite_toggle', methods: ['POST'])]
    public function toggle(Request $request): JsonResponse
    {
        $user = $request->getSession()->get('auth_user');
        if (!is_array($user) || (int) ($user['id'] ?? 0) <= 0) {
            return $this->json([
                'ok' => false,
                'message' => 'Connectez-vous pour enregistrer ce voyage dans vos favoris.',
            ], 401);
        }

        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            $payload = $request->request->all();
        }

        try {
            $result = $this->favoriteRepository->toggleFavorite((int) $user['id'], $payload);
        } catch (RuntimeException $exception) {
            return $this->json([
                'ok' => false,
                'message' => $exception->getMessage(),
            ], 500);
        }

        return $this->json([
            'ok' => true,
            'message' => $result['is_favorite']
                ? 'Voyage ajoute a vos favoris.'
                : 'Voyage retire de vos favoris.',
            ...$result,
        ]);
    }
}
