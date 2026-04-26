<?php

namespace App\Service;

use App\Util\UploadedFileMimeTypeGuesser;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class SponsorLogoStorageService
{
    private const PUBLIC_PREFIX = '/uploads/sponsor-logos/';

    public function __construct(private readonly string $projectDir)
    {
    }

    public function store(UploadedFile $file, string $sponsorKey): string
    {
        $directory = $this->getTargetDirectory();
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $extension = UploadedFileMimeTypeGuesser::detectExtension($file, 'png');
        $fileName = $this->sanitizeFileName($sponsorKey)
            .'-'.date('YmdHis')
            .'-'.bin2hex(random_bytes(4))
            .'.'.strtolower($extension);

        $file->move($directory, $fileName);

        return self::PUBLIC_PREFIX.$fileName;
    }

    public function delete(?string $logoPath): void
    {
        $absolutePath = $this->resolveManagedPath($logoPath);
        if ($absolutePath === null || !is_file($absolutePath)) {
            return;
        }

        @unlink($absolutePath);
    }

    public function isManagedLogo(?string $logoPath): bool
    {
        return $this->resolveManagedPath($logoPath) !== null;
    }

    private function getTargetDirectory(): string
    {
        return $this->projectDir.'/public/uploads/sponsor-logos';
    }

    private function resolveManagedPath(?string $logoPath): ?string
    {
        $logoPath = trim((string) $logoPath);
        if ($logoPath === '' || !str_starts_with($logoPath, self::PUBLIC_PREFIX)) {
            return null;
        }

        $absolutePath = realpath($this->getTargetDirectory()) ?: $this->getTargetDirectory();
        $candidate = $this->projectDir.'/public'.$logoPath;
        $candidateDirectory = dirname($candidate);

        if (!str_starts_with(str_replace('\\', '/', $candidateDirectory), str_replace('\\', '/', $absolutePath))) {
            return null;
        }

        return $candidate;
    }

    private function sanitizeFileName(string $value): string
    {
        $safe = strtolower(trim($value));
        $safe = preg_replace('/[^a-z0-9]+/', '-', $safe) ?? '';
        $safe = trim($safe, '-');

        return $safe !== '' ? $safe : 'sponsor-logo';
    }
}
