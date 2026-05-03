<?php

namespace App\Repository;

use App\Database\PdoConnectionFactory;
use App\Entity\Video;

class VideoRepository
{
    public function __construct(private PdoConnectionFactory $pdoFactory) {}

    private function pdo(): \PDO
    {
        return $this->pdoFactory->getConnection();
    }

    public function find(int $id): ?Video
    {
        $stmt = $this->pdo()->prepare('SELECT * FROM videos WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) return null;
        $v = (new Video())
            ->setId((int) $row['id'])
            ->setPostId((int) $row['post_id'])
            ->setFilename((string) $row['filename'])
            ->setPosition(isset($row['position']) ? (int) $row['position'] : 0);
        if (isset($row['description'])) $v->setDescription($row['description']);
        return $v;
    }

    public function save(Video $v): Video
    {
        $pdo = $this->pdo();
        if ($v->getId() === null) {
            $stmt = $pdo->prepare(
                'INSERT INTO videos (post_id, filename, description, position)
                 VALUES (:post_id, :filename, :description, :position)'
            );
            $stmt->execute([
                'post_id' => $v->getPost()?->getId() ?? $v->getPostId(),
                'filename' => $v->getFilename(),
                'description' => $v->getDescription(),
                'position' => $v->getPosition(),
            ]);
            $v->setId((int) $pdo->lastInsertId());
        } else {
            $stmt = $pdo->prepare(
                'UPDATE videos SET filename = :filename, description = :description, position = :position WHERE id = :id'
            );
            $stmt->execute([
                'id' => $v->getId(),
                'filename' => $v->getFilename(),
                'description' => $v->getDescription(),
                'position' => $v->getPosition(),
            ]);
        }
        return $v;
    }

    public function remove(Video $v): void
    {
        if ($v->getId() === null) return;
        $stmt = $this->pdo()->prepare('DELETE FROM videos WHERE id = :id');
        $stmt->execute(['id' => $v->getId()]);
    }
}
