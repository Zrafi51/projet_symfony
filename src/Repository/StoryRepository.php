<?php

namespace App\Repository;

use App\Database\PdoConnectionFactory;
use App\Entity\Music;
use App\Entity\Story;

class StoryRepository
{
    public function __construct(private PdoConnectionFactory $pdoFactory) {}

    private function pdo(): \PDO
    {
        return $this->pdoFactory->getConnection();
    }

    private function hydrate(array $row): Story
    {
        $s = (new Story())
            ->setAuteur($row['auteur'] ?? null)
            ->setMediaType((string) ($row['media_type'] ?? Story::TYPE_IMAGE))
            ->setFilename((string) ($row['filename'] ?? ''));
        $s->setId((int) $row['id']);
        if (isset($row['music_id']) && $row['music_id'] !== null) {
            $s->setMusicId((int) $row['music_id']);
        }
        if (isset($row['music_start']) && $row['music_start'] !== null) {
            $s->setMusicStart((float) $row['music_start']);
        }
        if (!empty($row['created_at'])) {
            try { $s->setCreatedAt(new \DateTime((string) $row['created_at'])); } catch (\Exception) {}
        }
        if (!empty($row['expires_at'])) {
            try { $s->setExpiresAt(new \DateTime((string) $row['expires_at'])); } catch (\Exception) {}
        }
        return $s;
    }

    /**
     * Attach Music entities for stories that have music_id set.
     * @param Story[] $stories
     */
    private function loadMusic(array $stories): void
    {
        $ids = [];
        foreach ($stories as $s) {
            if ($s->getMusicId() !== null) {
                $ids[$s->getMusicId()] = true;
            }
        }
        if (empty($ids)) return;
        $idArr = array_keys($ids);
        $ph = implode(',', array_fill(0, count($idArr), '?'));
        $stmt = $this->pdo()->prepare("SELECT * FROM music WHERE id IN ($ph)");
        $stmt->execute($idArr);
        $byId = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $r) {
            $m = (new Music())
                ->setId((int) $r['id'])
                ->setTitle((string) $r['title'])
                ->setArtist($r['artist'] ?? null)
                ->setFilename((string) ($r['filename'] ?? ''));
            if (!empty($r['uploaded_at'])) {
                try { $m->setUploadedAt(new \DateTime((string) $r['uploaded_at'])); } catch (\Exception) {}
            }
            $byId[$m->getId()] = $m;
        }
        foreach ($stories as $s) {
            if ($s->getMusicId() !== null && isset($byId[$s->getMusicId()])) {
                $s->setMusic($byId[$s->getMusicId()]);
            }
        }
    }

    public function find(int $id): ?Story
    {
        $stmt = $this->pdo()->prepare('SELECT * FROM stories WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) return null;
        $s = $this->hydrate($row);
        $this->loadMusic([$s]);
        return $s;
    }

    /**
     * @param string[] $usernames
     * @return Story[]
     */
    public function findActiveByAuthors(array $usernames): array
    {
        if (empty($usernames)) return [];
        $ph = implode(',', array_fill(0, count($usernames), '?'));
        $sql = "SELECT * FROM stories WHERE auteur IN ($ph) AND expires_at > NOW() ORDER BY created_at DESC";
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute(array_values($usernames));
        $stories = array_map(fn($r) => $this->hydrate($r), $stmt->fetchAll(\PDO::FETCH_ASSOC));
        $this->loadMusic($stories);
        return $stories;
    }

    /** @return Story[] */
    public function findActiveForUser(string $username): array
    {
        $stmt = $this->pdo()->prepare(
            'SELECT * FROM stories WHERE auteur = :u AND expires_at > NOW() ORDER BY created_at ASC'
        );
        $stmt->execute(['u' => $username]);
        $stories = array_map(fn($r) => $this->hydrate($r), $stmt->fetchAll(\PDO::FETCH_ASSOC));
        $this->loadMusic($stories);
        return $stories;
    }

    public function purgeExpired(): int
    {
        $stmt = $this->pdo()->prepare('DELETE FROM stories WHERE expires_at <= NOW()');
        $stmt->execute();
        return $stmt->rowCount();
    }

    public function userHasActiveStory(string $username): bool
    {
        $stmt = $this->pdo()->prepare(
            'SELECT COUNT(*) FROM stories WHERE auteur = :u AND expires_at > NOW()'
        );
        $stmt->execute(['u' => $username]);
        return ((int) $stmt->fetchColumn()) > 0;
    }

    public function save(Story $s): Story
    {
        $pdo = $this->pdo();
        if ($s->getCreatedAt() === null) $s->setCreatedAt(new \DateTime());
        if ($s->getExpiresAt() === null) $s->setExpiresAt((clone $s->getCreatedAt())->modify('+24 hours'));
        if ($s->getId() === null) {
            $stmt = $pdo->prepare(
                'INSERT INTO stories (auteur, media_type, filename, music_id, music_start, created_at, expires_at)
                 VALUES (:auteur, :mt, :fn, :mid, :ms, :ca, :ea)'
            );
            $stmt->execute([
                'auteur' => $s->getAuteur(),
                'mt' => $s->getMediaType(),
                'fn' => $s->getFilename(),
                'mid' => $s->getMusicId(),
                'ms' => $s->getMusicStart(),
                'ca' => $s->getCreatedAt()->format('Y-m-d H:i:s'),
                'ea' => $s->getExpiresAt()->format('Y-m-d H:i:s'),
            ]);
            $s->setId((int) $pdo->lastInsertId());
        } else {
            $stmt = $pdo->prepare(
                'UPDATE stories SET media_type = :mt, filename = :fn, music_id = :mid, music_start = :ms WHERE id = :id'
            );
            $stmt->execute([
                'id' => $s->getId(),
                'mt' => $s->getMediaType(),
                'fn' => $s->getFilename(),
                'mid' => $s->getMusicId(),
                'ms' => $s->getMusicStart(),
            ]);
        }
        return $s;
    }

    public function remove(Story $s): void
    {
        if ($s->getId() === null) return;
        $pdo = $this->pdo();
        try { $stmt = $pdo->prepare('DELETE FROM story_views WHERE story_id = :id'); $stmt->execute(['id' => $s->getId()]); } catch (\PDOException) {}
        try { $stmt = $pdo->prepare('DELETE FROM story_likes WHERE story_id = :id'); $stmt->execute(['id' => $s->getId()]); } catch (\PDOException) {}
        $stmt = $pdo->prepare('DELETE FROM stories WHERE id = :id');
        $stmt->execute(['id' => $s->getId()]);
    }
}
