<?php

namespace App\Service;

use App\Util\UploadedFileMimeTypeGuesser;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class ProfilePhotoStorageService
{
    private const PUBLIC_PREFIX = '/uploads/profile-photos/';
    private const LEGACY_MARKER = '/.easytravel/profile-photos';

    public function __construct(private readonly string $projectDir)
    {
    }

    public function store(UploadedFile $file, string $accountKey): string
    {
        $directory = $this->getTargetDirectory();
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $extension = UploadedFileMimeTypeGuesser::detectExtension($file, 'png');
        $fileName = $this->sanitizeFileName($accountKey)
            .'-'.date('YmdHis')
            .'-'.bin2hex(random_bytes(4))
            .'.'.strtolower($extension);

        $file->move($directory, $fileName);

        return self::PUBLIC_PREFIX.$fileName;
    }

    public function delete(?string $photoPath): void
    {
        $absolutePath = $this->resolveManagedPath($photoPath);
        if ($absolutePath === null || !is_file($absolutePath)) {
            return;
        }

        @unlink($absolutePath);
    }

    public function isManagedPhoto(?string $photoPath): bool
    {
        return $this->resolveManagedPath($photoPath) !== null;
    }

    public function resolveReadablePath(?string $photoPath): ?string
    {
        $managedPath = $this->resolveManagedPath($photoPath);
        if ($managedPath !== null && is_file($managedPath)) {
            return $managedPath;
        }

        $legacyPath = $this->resolveLegacyPath($photoPath);
        if ($legacyPath !== null && is_file($legacyPath)) {
            return $legacyPath;
        }

        return null;
    }

    public function encodePhotoReference(string $photoPath): string
    {
        return rtrim(strtr(base64_encode($photoPath), '+/', '-_'), '=');
    }

    public function decodePhotoReference(string $reference): ?string
    {
        $reference = trim($reference);
        if ($reference === '') {
            return null;
        }

        $normalizedReference = strtr($reference, '-_', '+/');
        $paddingLength = strlen($normalizedReference) % 4;
        if ($paddingLength > 0) {
            $normalizedReference .= str_repeat('=', 4 - $paddingLength);
        }

        $decoded = base64_decode($normalizedReference, true);
        if ($decoded === false || trim($decoded) === '') {
            return null;
        }

        return $decoded;
    }

    private function getTargetDirectory(): string
    {
        return $this->projectDir.'/public/uploads/profile-photos';
    }

    private function resolveManagedPath(?string $photoPath): ?string
    {
        $managedPublicPath = $this->resolveManagedPublicPath($photoPath);
        if ($managedPublicPath !== null) {
            return $managedPublicPath;
        }

        $legacyPath = $this->resolveLegacyPath($photoPath);
        if ($legacyPath !== null) {
            return $legacyPath;
        }

        return null;
    }

    private function resolveManagedPublicPath(?string $photoPath): ?string
    {
        $photoPath = trim((string) $photoPath);
        if ($photoPath === '' || !str_starts_with($photoPath, self::PUBLIC_PREFIX)) {
            return null;
        }

        $absolutePath = realpath($this->getTargetDirectory()) ?: $this->getTargetDirectory();
        $candidate = $this->projectDir.'/public'.$photoPath;
        $candidateDirectory = dirname($candidate);

        if (!str_starts_with(str_replace('\\', '/', $candidateDirectory), str_replace('\\', '/', $absolutePath))) {
            return null;
        }

        return $candidate;
    }

    private function resolveLegacyPath(?string $photoPath): ?string
    {
        $photoPath = trim((string) $photoPath);
        if ($photoPath === '') {
            return null;
        }

        $candidate = null;
        if (preg_match('#^file:/#i', $photoPath) === 1) {
            $uriPath = parse_url($photoPath, PHP_URL_PATH);
            if (!is_string($uriPath) || $uriPath === '') {
                return null;
            }

            $candidate = rawurldecode($uriPath);
            if (preg_match('#^/[a-zA-Z]:/#', $candidate) === 1) {
                $candidate = substr($candidate, 1);
            }
            $candidate = str_replace('/', DIRECTORY_SEPARATOR, $candidate);
        } elseif (preg_match('#^[a-zA-Z]:[\\\\/]#', $photoPath) === 1) {
            $candidate = str_replace('/', DIRECTORY_SEPARATOR, $photoPath);
        }

        if ($candidate === null || $candidate === '') {
            return null;
        }

        $normalizedCandidate = $this->normalizePath($candidate);
        if (!$this->isAllowedLegacyPath($normalizedCandidate)) {
            return null;
        }

        return $candidate;
    }

    private function getLegacyDirectory(): string
    {
        $userProfile = trim((string) (getenv('USERPROFILE') ?: getenv('HOME') ?: ''));
        if ($userProfile === '') {
            return $this->projectDir.'/var/legacy-profile-photos';
        }

        return rtrim($userProfile, '\\/').DIRECTORY_SEPARATOR.'.easytravel'.DIRECTORY_SEPARATOR.'profile-photos';
    }

    private function isAllowedLegacyPath(string $normalizedCandidate): bool
    {
        $legacyDirectory = $this->normalizePath($this->getLegacyDirectory());
        if (
            $normalizedCandidate === $legacyDirectory
            || str_starts_with($normalizedCandidate, $legacyDirectory.'/')
        ) {
            return true;
        }

        return str_contains($normalizedCandidate, self::LEGACY_MARKER.'/')
            || str_ends_with($normalizedCandidate, self::LEGACY_MARKER);
    }

    private function normalizePath(string $path): string
    {
        $normalized = str_replace('\\', '/', $path);
        $normalized = rtrim($normalized, '/');

        if (DIRECTORY_SEPARATOR === '\\') {
            $normalized = strtolower($normalized);
        }

        return $normalized;
    }

    private function sanitizeFileName(string $value): string
    {
        $safe = strtolower(trim($value));
        $safe = preg_replace('/[^a-z0-9]+/', '-', $safe) ?? '';
        $safe = trim($safe, '-');

        return $safe !== '' ? $safe : 'admin-account';
    }

    private function buildFileUri(string $path): string
    {
        $normalized = str_replace('\\', '/', $path);
        $segments = explode('/', $normalized);
        foreach ($segments as $index => $segment) {
            if ($segment === '' || preg_match('/^[a-zA-Z]:$/', $segment) === 1) {
                continue;
            }
            $segments[$index] = rawurlencode($segment);
        }

        return 'file:///'.implode('/', $segments);
    }
}
