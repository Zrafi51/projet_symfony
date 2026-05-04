<?php

namespace App\Util;

use Symfony\Component\HttpFoundation\File\UploadedFile;

final class UploadedFileMimeTypeGuesser
{
    private const MIME_EXTENSION_MAP = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
        'image/svg+xml' => 'svg',
    ];

    public static function detect(UploadedFile $file): ?string
    {
        $path = $file->getPathname();
        if ($path === '') {
            return self::normalizeClientMimeType((string) $file->getClientMimeType(), $file);
        }

        $mimeType = null;

        if (function_exists('finfo_open')) {
            $finfo = @finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $detectedMimeType = @finfo_file($finfo, $path);
                @finfo_close($finfo);
                if (is_string($detectedMimeType) && trim($detectedMimeType) !== '') {
                    $mimeType = trim($detectedMimeType);
                }
            }
        }

        if (($mimeType === null || $mimeType === '') && function_exists('mime_content_type')) {
            $detectedMimeType = @mime_content_type($path);
            if (is_string($detectedMimeType) && trim($detectedMimeType) !== '') {
                $mimeType = trim($detectedMimeType);
            }
        }

        if ($mimeType === null || $mimeType === '') {
            $mimeType = self::normalizeClientMimeType((string) $file->getClientMimeType(), $file);
        }

        return self::normalizeMimeType((string) $mimeType, $file);
    }

    public static function detectExtension(UploadedFile $file, string $fallback = 'jpg'): string
    {
        $clientExtension = strtolower(trim((string) $file->getClientOriginalExtension()));
        if ($clientExtension !== '') {
            $safeClientExtension = preg_replace('/[^a-z0-9]+/', '', $clientExtension) ?? '';
            if ($safeClientExtension !== '') {
                return $safeClientExtension;
            }
        }

        $mimeType = self::detect($file);
        if ($mimeType !== null && isset(self::MIME_EXTENSION_MAP[$mimeType])) {
            return self::MIME_EXTENSION_MAP[$mimeType];
        }

        $safeFallback = preg_replace('/[^a-z0-9]+/', '', strtolower(trim($fallback))) ?? '';

        return $safeFallback !== '' ? $safeFallback : 'jpg';
    }

    private static function normalizeClientMimeType(string $mimeType, UploadedFile $file): ?string
    {
        $mimeType = trim($mimeType);
        if ($mimeType === '') {
            return self::normalizeMimeType('', $file);
        }

        return self::normalizeMimeType($mimeType, $file);
    }

    private static function normalizeMimeType(string $mimeType, UploadedFile $file): ?string
    {
        $mimeType = strtolower(trim($mimeType));
        $extension = strtolower((string) $file->getClientOriginalExtension());

        if ($extension === 'svg' || str_contains((string) $file->getClientOriginalName(), '.svg')) {
            return 'image/svg+xml';
        }

        return match ($mimeType) {
            'image/jpg', 'image/pjpeg' => 'image/jpeg',
            'image/x-png' => 'image/png',
            default => $mimeType !== '' ? $mimeType : null,
        };
    }
}
