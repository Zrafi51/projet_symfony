<?php

namespace App\Service;

use App\Util\UploadedFileMimeTypeGuesser;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class ForumMediaStorageService
{
    private const PUBLIC_PREFIX = '/uploads/forum-media/';

    public function __construct(private readonly string $projectDir)
    {
    }

    public function store(UploadedFile $file, string $mediaKey): string
    {
        $directory = $this->getTargetDirectory();
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $extension = UploadedFileMimeTypeGuesser::detectExtension($file, 'jpg');
        $fileName = $this->sanitizeFileName($mediaKey)
            .'-'.date('YmdHis')
            .'-'.bin2hex(random_bytes(4))
            .'.'.strtolower($extension);

        $file->move($directory, $fileName);

        return self::PUBLIC_PREFIX.$fileName;
    }

    public function delete(?string $mediaPath): void
    {
        $absolutePath = $this->resolveManagedPath($mediaPath);
        if ($absolutePath === null || !is_file($absolutePath)) {
            return;
        }

        @unlink($absolutePath);
    }

    public function isManagedMedia(?string $mediaPath): bool
    {
        return $this->resolveManagedPath($mediaPath) !== null;
    }

    private function getTargetDirectory(): string
    {
        return $this->projectDir.'/public/uploads/forum-media';
    }

    private function resolveManagedPath(?string $mediaPath): ?string
    {
        $mediaPath = trim((string) $mediaPath);
        if ($mediaPath === '' || !str_starts_with($mediaPath, self::PUBLIC_PREFIX)) {
            return null;
        }

        $absolutePath = realpath($this->getTargetDirectory()) ?: $this->getTargetDirectory();
        $candidate = $this->projectDir.'/public'.$mediaPath;
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

        return $safe !== '' ? $safe : 'forum-media';
    }
}
