<?php

namespace App\Controller;

use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class FaceAuthController extends AbstractController
{
    private const MATCH_THRESHOLD = 0.55; // Tightened for better security against false positives

    public function __construct(
        private readonly UserRepository $userRepository
    ) {
    }

    #[Route('/api/face-id/enroll', name: 'api_face_id_enroll', methods: ['POST'])]
    public function enroll(Request $request): JsonResponse
    {
        $session = $request->getSession();
        $authUser = $session->get('auth_user');

        if (!$authUser) {
            return new JsonResponse(['success' => false, 'message' => 'Non authentifie.'], 401);
        }

        $data = json_decode($request->getContent(), true);
        if (!isset($data['descriptor']) || !is_array($data['descriptor'])) {
            return new JsonResponse(['success' => false, 'message' => 'Descripteur invalide.'], 400);
        }

        // Store the descriptor as JSON string
        $descriptorJson = json_encode($data['descriptor']);
        
        $success = $this->userRepository->updateFaceDescriptor($authUser['email'], $descriptorJson);

        if ($success) {
            return new JsonResponse(['success' => true, 'message' => 'Face ID configure avec succes.']);
        }

        return new JsonResponse(['success' => false, 'message' => 'Erreur lors de la sauvegarde.'], 500);
    }

    #[Route('/api/face-id/login', name: 'api_face_id_login', methods: ['POST'])]
    public function login(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        if (!isset($data['email']) || !isset($data['descriptor']) || !is_array($data['descriptor'])) {
            return new JsonResponse(['success' => false, 'message' => 'Email et descripteur requis.'], 400);
        }

        $email = trim($data['email']);
        $liveDescriptor = $data['descriptor'];

        $user = $this->userRepository->getByEmail($email);
        
        if (!$user) {
            return new JsonResponse(['success' => false, 'message' => 'Utilisateur non trouve.'], 404);
        }

        if (($user['is_active'] ?? false) !== true) {
            return new JsonResponse(['success' => false, 'message' => 'Ce compte est suspendu ou desactive.'], 403);
        }

        if (($user['is_pending_validation'] ?? false) === true) {
            return new JsonResponse(['success' => false, 'message' => 'Compte en attente de validation.'], 403);
        }

        $storedDescriptorJson = $this->userRepository->getFaceDescriptor($email);
        
        if (!$storedDescriptorJson) {
            return new JsonResponse(['success' => false, 'message' => 'Face ID non configure pour ce compte.'], 400);
        }

        $storedDescriptor = json_decode($storedDescriptorJson, true);

        if (!is_array($storedDescriptor) || count($storedDescriptor) !== count($liveDescriptor)) {
            return new JsonResponse(['success' => false, 'message' => 'Donnees faciales corrompues.'], 500);
        }

        // Calculate Euclidean distance
        $distance = $this->euclideanDistance($liveDescriptor, $storedDescriptor);

        if ($distance < self::MATCH_THRESHOLD) {
            // Match successful, log the user in
            $session = $request->getSession();
            $session->set('auth_user', [
                'id' => (int) ($user['id'] ?? 0),
                'display_name' => (string) ($user['display_name'] ?? ''),
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

            return new JsonResponse([
                'success' => true, 
                'message' => 'Connexion reussie.',
                'redirect' => $this->resolveDashboardRoute((string) ($user['role'] ?? 'USER'))
            ]);
        }

        // Detailed debug info on failure
        $liveSnippet = implode(',', array_slice($liveDescriptor, 0, 3));
        $storedSnippet = implode(',', array_slice($storedDescriptor, 0, 3));
        
        return new JsonResponse([
            'success' => false, 
            'message' => "Authentification échouée. Le visage ne correspond pas au compte."
        ], 401);
    }

    private function euclideanDistance(array $a, array $b): float
    {
        $sum = 0.0;
        foreach ($a as $i => $val) {
            $sum += pow($val - $b[$i], 2);
        }
        return sqrt($sum);
    }

    private function resolveDashboardRoute(string $role): string
    {
        return in_array(strtoupper(trim($role)), ['ADMIN', 'SUPER_ADMIN'], true)
            ? '/admin/dashboard'
            : '/dashboard';
    }
}
