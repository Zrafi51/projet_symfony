<?php

namespace App\View;

use App\Service\ProfilePhotoStorageService;
use RuntimeException;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

final class PhpTemplateRenderer
{
    public function __construct(
        private readonly string $projectDir,
        private readonly ?Environment $twig = null,
        private readonly ?RequestStack $requestStack = null,
        private readonly ?ProfilePhotoStorageService $profilePhotoStorageService = null,
        private readonly ?UrlGeneratorInterface $urlGenerator = null
    )
    {
    }

    public function render(string $template, array $context = []): string
    {
        $contentTemplate = $this->projectDir.'/templates/'.$template.'.php';
        $context = $this->enrichContext($context);
        $twigTemplate = str_replace('\\', '/', $template).'.html.twig';
        $twigTemplatePath = $this->projectDir.'/templates/'.$twigTemplate;
        $layout = $context['layout'] ?? 'layout';
        $layoutTemplate = $this->projectDir.'/templates/'.$layout.'.php';
        $layoutTwigTemplate = str_replace('\\', '/', $layout).'.html.twig';
        $layoutTwigTemplatePath = $this->projectDir.'/templates/'.$layoutTwigTemplate;

        if ($this->twig !== null && is_file($twigTemplatePath)) {
            $twigContext = $context;
            unset($twigContext['layout']);
            $twigContext['legacy_content'] = is_file($contentTemplate)
                ? $this->renderPhpContentTemplate($contentTemplate, $context)
                : '';

            return $this->twig->render($twigTemplate, $twigContext);
        }

        if ($this->twig !== null && is_file($layoutTwigTemplatePath)) {
            if (!is_file($contentTemplate)) {
                throw new RuntimeException(sprintf('Template introuvable: %s', $contentTemplate));
            }

            $twigContext = $context;
            unset($twigContext['layout']);
            $twigContext['legacy_content'] = $this->renderPhpContentTemplate($contentTemplate, $context);

            return $this->twig->render($layoutTwigTemplate, $twigContext);
        }

        if (!is_file($contentTemplate)) {
            throw new RuntimeException(sprintf('Template introuvable: %s', $contentTemplate));
        }

        if (!is_file($layoutTemplate)) {
            throw new RuntimeException(sprintf('Layout introuvable: %s', $layoutTemplate));
        }

        unset($context['layout']);
        extract($context, EXTR_SKIP);

        ob_start();
        require $layoutTemplate;

        return (string) ob_get_clean();
    }

    public function renderFragment(string $template, array $context = []): string
    {
        $contentTemplate = $this->projectDir.'/templates/'.$template.'.php';
        $context = $this->enrichContext($context);
        $twigTemplate = str_replace('\\', '/', $template).'.html.twig';
        $twigTemplatePath = $this->projectDir.'/templates/'.$twigTemplate;

        if ($this->twig !== null && is_file($twigTemplatePath)) {
            return $this->twig->render($twigTemplate, $context);
        }

        if (!is_file($contentTemplate)) {
            throw new RuntimeException(sprintf('Fragment introuvable: %s', $template));
        }

        return $this->renderPhpContentTemplate($contentTemplate, $context);
    }

    private function renderPhpContentTemplate(string $contentTemplate, array $context): string
    {
        unset($context['layout']);
        extract($context, EXTR_SKIP);

        ob_start();
        require $contentTemplate;

        return (string) ob_get_clean();
    }

    private function enrichContext(array $context): array
    {
        $context['h'] ??= static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
        $request = $this->requestStack?->getCurrentRequest();
        $context['path'] ??= $request?->getPathInfo() ?: (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
        $context['currentUser'] = $this->resolveCurrentUser($context['currentUser'] ?? null, $request);

        return $context;
    }

    private function resolveCurrentUser(mixed $currentUser, mixed $request): array
    {
        $sessionUser = is_array($currentUser) ? $currentUser : [];
        if ($sessionUser === [] && $request !== null && method_exists($request, 'hasSession') && $request->hasSession()) {
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
