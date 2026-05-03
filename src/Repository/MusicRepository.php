<?php

namespace App\Repository;

use App\Database\PdoConnectionFactory;
use App\Entity\Music;

class MusicRepository
{
    public function __construct(private PdoConnectionFactory $pdoFactory) {}

    private function pdo(): \PDO
    {
        return $this->pdoFactory->getConnection();
    }

    private function hydrate(array $row): Music
    {
        $m = (new Music())
            ->setId((int) $row['id'])
            ->setTitle((string) $row['title'])
            ->setArtist($row['artist'] ?? null)
            ->setFilename((string) ($row['filename'] ?? ''));
        if (!empty($row['uploaded_at'])) {
            try { $m->setUploadedAt(new \DateTime((string) $row['uploaded_at'])); } catch (\Exception) {}
        }
        return $m;
    }

    public function find(int $id): ?Music
    {
        $stmt = $this->pdo()->prepare('SELECT * FROM music WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ? $this->hydrate($row) : null;
    }

    /** @return Music[] */
    public function findAllOrdered(): array
    {
        $rows = $this->pdo()->query('SELECT * FROM music ORDER BY uploaded_at DESC')->fetchAll(\PDO::FETCH_ASSOC);
        return array_map(fn($r) => $this->hydrate($r), $rows);
    }

    public function save(Music $m): Music
    {
        $pdo = $this->pdo();
        if ($m->getUploadedAt() === null) {
            $m->setUploadedAt(new \DateTime());
        }
        if ($m->getId() === null) {
            $stmt = $pdo->prepare(
                'INSERT INTO music (title, artist, filename, uploaded_at)
                 VALUES (:title, :artist, :filename, :uploaded_at)'
            );
            $stmt->execute([
                'title' => $m->getTitle(),
                'artist' => $m->getArtist(),
                'filename' => $m->getFilename(),
                'uploaded_at' => $m->getUploadedAt()->format('Y-m-d H:i:s'),
            ]);
            $m->setId((int) $pdo->lastInsertId());
        } else {
            $stmt = $pdo->prepare(
                'UPDATE music SET title = :title, artist = :artist, filename = :filename WHERE id = :id'
            );
            $stmt->execute([
                'id' => $m->getId(),
                'title' => $m->getTitle(),
                'artist' => $m->getArtist(),
                'filename' => $m->getFilename(),
            ]);
        }
        return $m;
    }

    public function remove(Music $m): void
    {
        if ($m->getId() === null) return;
        $stmt = $this->pdo()->prepare('DELETE FROM music WHERE id = :id');
        $stmt->execute(['id' => $m->getId()]);
    }
}
