<?php

namespace App\Controller;

use App\Repository\ImageRepository;
use App\Repository\PostRepository;
use App\Repository\UserRepository;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ImageBlobController extends AbstractController
{
    #[Route('/image/post/{id}', name: 'image_post_blob')]
    public function servePostImage(int $id, PostRepository $postRepository, Connection $connection): Response
    {
        $post = $postRepository->find($id);
        if (!$post) {
            throw $this->createNotFoundException('Image non trouvee');
        }

        $data = $this->blobToString($post->getPhotoBlob());
        if ($data !== null && $data !== '') {
            return $this->binaryResponse($data, $post->getPhotoMimeType() ?? 'image/jpeg');
        }

        $row = $this->fetchFirstPostImageRow($connection, $id);
        if ($row) {
            $data = $this->firstBlob($row, ['image_blob', 'media_data']);
            if ($data !== null && $data !== '') {
                return $this->binaryResponse($data, (string) ($row['mime_type'] ?? 'image/jpeg'));
            }
        }

        return $this->legacyPostFileResponse($post->getCheminPhoto() ?? '');
    }

    #[Route('/image/gallery/{id}', name: 'image_gallery_blob')]
    public function serveGalleryImage(int $id, ImageRepository $imageRepository, Connection $connection): Response
    {
        $image = $imageRepository->find($id);
        if (!$image) {
            throw $this->createNotFoundException('Image non trouvee');
        }

        $data = $this->blobToString($image->getImageBlob());
        if ($data !== null && $data !== '') {
            return $this->binaryResponse($data, $image->getMimeType() ?? 'image/jpeg');
        }

        $row = $this->fetchImageRow($connection, $id);
        if ($row) {
            $data = $this->firstBlob($row, ['media_data', 'image_blob']);
            if ($data !== null && $data !== '') {
                return $this->binaryResponse($data, (string) ($row['mime_type'] ?? 'image/jpeg'));
            }

            return $this->legacyPostFileResponse((string) ($row['filename'] ?? ''));
        }

        return $this->legacyPostFileResponse($image->getFilename() ?? '');
    }

    #[Route('/image/profile/{username}', name: 'image_profile_blob')]
    public function serveProfileImage(string $username, UserRepository $userRepository): Response
    {
        $user = $userRepository->findOneBy(['username' => $username]);
        if (!$user || !$user->hasProfilePhotoBlob()) {
            throw $this->createNotFoundException('Image non trouvee');
        }

        $data = $this->blobToString($user->getProfilePhotoBlob());
        if ($data === null || $data === '') {
            throw $this->createNotFoundException('Image non trouvee');
        }

        return $this->binaryResponse($data, $user->getProfilePhotoMimeType() ?? 'image/jpeg', 3600);
    }

    private function fetchImageRow(Connection $connection, int $id): ?array
    {
        $columns = ['filename', 'mime_type'];
        foreach (['image_blob', 'media_data'] as $column) {
            if ($this->columnExists($connection, 'sf_images', $column)) {
                $columns[] = $column;
            }
        }

        if (count($columns) === 2) {
            return null;
        }

        $row = $connection->fetchAssociative(
            sprintf('SELECT %s FROM sf_images WHERE id = :id LIMIT 1', implode(', ', $columns)),
            ['id' => $id]
        );

        return $row ?: null;
    }

    private function fetchFirstPostImageRow(Connection $connection, int $postId): ?array
    {
        $columns = ['id', 'filename', 'mime_type'];
        foreach (['image_blob', 'media_data'] as $column) {
            if ($this->columnExists($connection, 'sf_images', $column)) {
                $columns[] = $column;
            }
        }

        if (count($columns) === 3) {
            return null;
        }

        $row = $connection->fetchAssociative(
            sprintf(
                'SELECT %s FROM sf_images WHERE post_id = :post_id ORDER BY position ASC, id ASC LIMIT 1',
                implode(', ', $columns)
            ),
            ['post_id' => $postId]
        );

        return $row ?: null;
    }

    private function columnExists(Connection $connection, string $table, string $column): bool
    {
        try {
            return (int) $connection->fetchOne(
                'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column',
                ['table' => $table, 'column' => $column]
            ) > 0;
        } catch (\Throwable) {
            return false;
        }
    }

    private function firstBlob(array $row, array $columns): ?string
    {
        foreach ($columns as $column) {
            if (!array_key_exists($column, $row)) {
                continue;
            }

            $data = $this->blobToString($row[$column]);
            if ($data !== null && $data !== '') {
                return $data;
            }
        }

        return null;
    }

    private function binaryResponse(string $data, string $mimeType, int $maxAge = 31536000): Response
    {
        $response = new Response($data);
        $response->headers->set('Content-Type', $mimeType !== '' ? $mimeType : 'image/jpeg');
        $response->headers->set('Cache-Control', 'public, max-age=' . $maxAge);

        return $response;
    }

    private function legacyPostFileResponse(string $filename): BinaryFileResponse
    {
        $basename = basename(str_replace('\\', '/', $filename));
        if ($basename === '' || $basename === '.' || $basename === '..') {
            throw $this->createNotFoundException('Image non trouvee');
        }

        foreach (['posts', ''] as $subdir) {
            $path = rtrim($this->getParameter('kernel.project_dir') . '/public/uploads/' . $subdir, '/\\') . '/' . $basename;
            if (is_file($path)) {
                $response = new BinaryFileResponse($path);
                $response->headers->set('Cache-Control', 'public, max-age=3600');

                return $response;
            }
        }

        throw $this->createNotFoundException('Image non trouvee');
    }

    private function blobToString(mixed $blob): ?string
    {
        if (is_resource($blob)) {
            $contents = stream_get_contents($blob);

            return $contents === false ? null : $contents;
        }

        return is_string($blob) ? $blob : null;
    }
}
