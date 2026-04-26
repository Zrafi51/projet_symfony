<?php

namespace App\Twig;

use App\Service\ProfilePhotoStorageService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class AppContextExtension extends AbstractExtension
{
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly ?ProfilePhotoStorageService $profilePhotoStorageService = null,
        private readonly ?UrlGeneratorInterface $urlGenerator = null,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('current_user', [$this, 'getCurrentUser']),
        ];
    }

    public function getCurrentUser(mixed $currentUser = null): array
    {
        return $this->resolveCurrentUser($currentUser, $this->requestStack->getCurrentRequest());
    }

    private function resolveCurrentUser(mixed $currentUser, ?Request $request): array
    {
        $sessionUser = is_array($currentUser) ? $currentUser : [];

        if ($sessionUser === [] && $request !== null && $request->hasSession()) {
            $candidate = $request->getSession()->get('auth_user', []);
            if (is_array($candidate)) {
                $sessionUser = $candidate;
            }
        }

        if ($sessionUser === [] && isset($_SESSION['auth_user']) && is_array($_SESSION['auth_user'])) {
            $sessionUser = $_SESSION['auth_user'];
        }

        if ($sessionUser === []) {
            return [];
        }

        $photoUrl = trim((string) ($sessionUser['photo_display_url'] ?? ''));
        if ($photoUrl === '') {
            $sessionUser['photo_display_url'] = $this->resolvePhotoUrlForView((string) ($sessionUser['photo_url'] ?? ''));
        }

        $displayName = trim((string) ($sessionUser['display_name'] ?? ''));
        if ($displayName === '') {
            $fullName = trim((string) ($sessionUser['prenom'] ?? '').' '.(string) ($sessionUser['nom'] ?? ''));
            $sessionUser['display_name'] = $fullName !== '' ? $fullName : (string) ($sessionUser['email'] ?? 'Voyageur');
        }

        return $sessionUser;
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

        if ($this->profilePhotoStorageService === null || $this->urlGenerator === null) {
            return '';
        }

        if ($this->profilePhotoStorageService->resolveReadablePath($photoPath) === null) {
            return '';
        }

        return $this->urlGenerator->generate('app_profile_photo', [
            'reference' => $this->profilePhotoStorageService->encodePhotoReference($photoPath),
        ]);
    }
}
