<?php

namespace App\Repository;

use App\Database\PdoConnectionFactory;
use App\Entity\Image;

class ImageRepository
{
    public function __construct(private PdoConnectionFactory $pdoFactory) {}

    private function pdo(): \PDO
    {
        return $this->pdoFactory->getConnection();
    }

    public function find(int $id): ?Image
    {
        $stmt = $this->pdo()->prepare('SELECT * FROM images WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) return null;
        $img = (new Image())
            ->setId((int) $row['id'])
            ->setPostId((int) $row['post_id'])
            ->setFilename((string) $row['filename'])
            ->setPosition(isset($row['position']) ? (int) $row['position'] : 0);
        if (isset($row['description'])) $img->setDescription($row['description']);
        return $img;
    }

    public function save(Image $img): Image
    {
        $pdo = $this->pdo();
        if ($img->getId() === null) {
            $stmt = $pdo->prepare(
                'INSERT INTO images (post_id, filename, description, position)
                 VALUES (:post_id, :filename, :description, :position)'
            );
            $stmt->execute([
                'post_id' => $img->getPost()?->getId() ?? $img->getPostId(),
                'filename' => $img->getFilename(),
                'description' => $img->getDescription(),
                'position' => $img->getPosition(),
            ]);
            $img->setId((int) $pdo->lastInsertId());
        } else {
            $stmt = $pdo->prepare(
                'UPDATE images SET filename = :filename, description = :description, position = :position WHERE id = :id'
            );
            $stmt->execute([
                'id' => $img->getId(),
                'filename' => $img->getFilename(),
                'description' => $img->getDescription(),
                'position' => $img->getPosition(),
            ]);
        }
        return $img;
    }

    public function remove(Image $img): void
    {
        if ($img->getId() === null) return;
        $stmt = $this->pdo()->prepare('DELETE FROM images WHERE id = :id');
        $stmt->execute(['id' => $img->getId()]);
    }
}
