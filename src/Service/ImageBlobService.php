<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;

class ImageBlobService
{
    /**
     * Convertit un fichier uploadé en BLOB pour stockage en base de données
     */
    public function convertUploadedFileToBlob(UploadedFile $file): array
    {
        $content = file_get_contents($file->getPathname());
        $mimeType = $file->getMimeType() ?? $file->guessExtension();
        
        return [
            'blob' => $content,
            'mimeType' => $mimeType
        ];
    }

    /**
     * Convertit un fichier existant en BLOB
     */
    public function convertFileToBlob(string $filePath): ?array
    {
        if (!file_exists($filePath)) {
            return null;
        }

        $content = file_get_contents($filePath);
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $filePath);
        finfo_close($finfo);
        
        return [
            'blob' => $content,
            'mimeType' => $mimeType
        ];
    }

    /**
     * Génère une URL data:image pour affichage direct
     */
    public function getBlobAsDataUrl($blob, string $mimeType): string
    {
        if (is_resource($blob)) {
            $content = stream_get_contents($blob);
        } else {
            $content = $blob;
        }
        
        return 'data:' . $mimeType . ';base64,' . base64_encode($content);
    }

    /**
     * Vérifie si le fichier est une image valide
     */
    public function isValidImage(UploadedFile $file): bool
    {
        $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        return in_array($file->getMimeType(), $allowedMimes);
    }

    /**
     * Redimensionne une image pour optimiser le stockage
     */
    public function resizeImage(string $imageData, int $maxWidth = 1920, int $maxHeight = 1080): string
    {
        $image = imagecreatefromstring($imageData);
        if (!$image) {
            return $imageData;
        }

        $width = imagesx($image);
        $height = imagesy($image);

        if ($width <= $maxWidth && $height <= $maxHeight) {
            imagedestroy($image);
            return $imageData;
        }

        $ratio = min($maxWidth / $width, $maxHeight / $height);
        $newWidth = (int)($width * $ratio);
        $newHeight = (int)($height * $ratio);

        $newImage = imagecreatetruecolor($newWidth, $newHeight);
        imagecopyresampled($newImage, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        ob_start();
        imagejpeg($newImage, null, 85);
        $resizedData = ob_get_clean();

        imagedestroy($image);
        imagedestroy($newImage);

        return $resizedData;
    }
}
