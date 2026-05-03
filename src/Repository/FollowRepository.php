<?php

namespace App\Repository;

use App\Database\PdoConnectionFactory;
use App\Entity\Follow;

class FollowRepository
{
    public function __construct(private PdoConnectionFactory $pdoFactory) {}

    private function pdo(): \PDO
    {
        return $this->pdoFactory->getConnection();
    }

    private function hydrate(array $row): Follow
    {
        $f = (new Follow())
            ->setId((int) $row['id'])
            ->setFollowerUsername((string) $row['follower_username'])
            ->setFollowingUsername((string) $row['following_username'])
            ->setStatus((string) ($row['status'] ?? Follow::STATUS_ACCEPTED));
        if (!empty($row['created_at'])) {
            try { $f->setCreatedAt(new \DateTimeImmutable((string) $row['created_at'])); } catch (\Exception) {}
        }
        return $f;
    }

    public function find(int $id): ?Follow
    {
        $stmt = $this->pdo()->prepare('SELECT * FROM follows WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ? $this->hydrate($row) : null;
    }

    public function findEdge(string $follower, string $following): ?Follow
    {
        $stmt = $this->pdo()->prepare(
            'SELECT * FROM follows WHERE follower_username = :f AND following_username = :t LIMIT 1'
        );
        $stmt->execute(['f' => $follower, 't' => $following]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ? $this->hydrate($row) : null;
    }

    public function isFollowing(string $follower, string $following): bool
    {
        $edge = $this->findEdge($follower, $following);
        return $edge !== null && $edge->isAccepted();
    }

    public function hasPending(string $follower, string $following): bool
    {
        $edge = $this->findEdge($follower, $following);
        return $edge !== null && $edge->isPending();
    }

    /** @return string[] */
    public function getFollowingUsernames(string $username): array
    {
        $stmt = $this->pdo()->prepare(
            'SELECT following_username FROM follows WHERE follower_username = :u AND status = :s'
        );
        $stmt->execute(['u' => $username, 's' => Follow::STATUS_ACCEPTED]);
        return array_column($stmt->fetchAll(\PDO::FETCH_ASSOC), 'following_username');
    }

    /** @return string[] */
    public function getFollowerUsernames(string $username): array
    {
        $stmt = $this->pdo()->prepare(
            'SELECT follower_username FROM follows WHERE following_username = :u AND status = :s'
        );
        $stmt->execute(['u' => $username, 's' => Follow::STATUS_ACCEPTED]);
        return array_column($stmt->fetchAll(\PDO::FETCH_ASSOC), 'follower_username');
    }

    public function countFollowers(string $username): int
    {
        $stmt = $this->pdo()->prepare(
            'SELECT COUNT(*) FROM follows WHERE following_username = :u AND status = :s'
        );
        $stmt->execute(['u' => $username, 's' => Follow::STATUS_ACCEPTED]);
        return (int) $stmt->fetchColumn();
    }

    public function countFollowing(string $username): int
    {
        $stmt = $this->pdo()->prepare(
            'SELECT COUNT(*) FROM follows WHERE follower_username = :u AND status = :s'
        );
        $stmt->execute(['u' => $username, 's' => Follow::STATUS_ACCEPTED]);
        return (int) $stmt->fetchColumn();
    }

    /** @return Follow[] */
    public function getPendingRequestsFor(string $username): array
    {
        $stmt = $this->pdo()->prepare(
            'SELECT * FROM follows WHERE following_username = :u AND status = :s ORDER BY created_at DESC'
        );
        $stmt->execute(['u' => $username, 's' => Follow::STATUS_PENDING]);
        return array_map(fn($r) => $this->hydrate($r), $stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    /** @return Follow[] */
    public function getRecentFollowersOf(string $username, int $limit = 15): array
    {
        $sql = 'SELECT * FROM follows WHERE following_username = :u AND status = :s ORDER BY created_at DESC LIMIT ' . (int) $limit;
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute(['u' => $username, 's' => Follow::STATUS_ACCEPTED]);
        return array_map(fn($r) => $this->hydrate($r), $stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    public function save(Follow $f): Follow
    {
        $pdo = $this->pdo();
        if ($f->getId() === null) {
            $stmt = $pdo->prepare(
                'INSERT INTO follows (follower_username, following_username, status, created_at)
                 VALUES (:fu, :tu, :st, :ca)'
            );
            $stmt->execute([
                'fu' => $f->getFollowerUsername(),
                'tu' => $f->getFollowingUsername(),
                'st' => $f->getStatus(),
                'ca' => $f->getCreatedAt()->format('Y-m-d H:i:s'),
            ]);
            $f->setId((int) $pdo->lastInsertId());
        } else {
            $stmt = $pdo->prepare(
                'UPDATE follows SET status = :st WHERE id = :id'
            );
            $stmt->execute([
                'id' => $f->getId(),
                'st' => $f->getStatus(),
            ]);
        }
        return $f;
    }

    public function remove(Follow $f): void
    {
        if ($f->getId() === null) return;
        $stmt = $this->pdo()->prepare('DELETE FROM follows WHERE id = :id');
        $stmt->execute(['id' => $f->getId()]);
    }
}
