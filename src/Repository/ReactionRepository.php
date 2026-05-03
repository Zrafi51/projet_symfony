<?php

namespace App\Repository;

use App\Database\PdoConnectionFactory;
use App\Entity\Post;
use App\Entity\Reaction;
use App\Entity\User;

class ReactionRepository
{
    public function __construct(private PdoConnectionFactory $pdoFactory) {}

    private function pdo(): \PDO
    {
        return $this->pdoFactory->getConnection();
    }

    private function hydrate(array $row): Reaction
    {
        $r = (new Reaction())
            ->setId((int) $row['id'])
            ->setPostId((int) $row['post_id'])
            ->setUsername($row['username'] ?? null)
            ->setReactionType((string) ($row['reaction_type'] ?? ''));
        if (!empty($row['created_at'])) {
            try { $r->setCreatedAt(new \DateTime((string) $row['created_at'])); } catch (\Exception) {}
        }
        return $r;
    }

    public function find(int $id): ?Reaction
    {
        $stmt = $this->pdo()->prepare('SELECT * FROM reactions WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ? $this->hydrate($row) : null;
    }

    public function findByPostAndUser(Post $post, User $user): ?Reaction
    {
        $stmt = $this->pdo()->prepare('SELECT * FROM reactions WHERE post_id = :p AND username = :u LIMIT 1');
        $stmt->execute(['p' => $post->getId(), 'u' => $user->getUsername()]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ? $this->hydrate($row) : null;
    }

    public function getReactionCountsForPost(Post $post): array
    {
        $stmt = $this->pdo()->prepare(
            'SELECT reaction_type, COUNT(*) AS c FROM reactions WHERE post_id = :p GROUP BY reaction_type'
        );
        $stmt->execute(['p' => $post->getId()]);
        $counts = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $counts[$row['reaction_type']] = (int) $row['c'];
        }
        return $counts;
    }

    public function getTotalReactionsForUser(User $user): int
    {
        $stmt = $this->pdo()->prepare(
            'SELECT COUNT(r.id) FROM reactions r
             INNER JOIN posts p ON p.id = r.post_id
             WHERE p.auteur = :u'
        );
        $stmt->execute(['u' => $user->getUsername()]);
        return (int) $stmt->fetchColumn();
    }

    public function getReactionBreakdownForUser(User $user): array
    {
        $stmt = $this->pdo()->prepare(
            'SELECT r.reaction_type, COUNT(r.id) AS c FROM reactions r
             INNER JOIN posts p ON p.id = r.post_id
             WHERE p.auteur = :u
             GROUP BY r.reaction_type'
        );
        $stmt->execute(['u' => $user->getUsername()]);
        $breakdown = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $breakdown[$row['reaction_type']] = (int) $row['c'];
        }
        return $breakdown;
    }

    /**
     * @param int[] $postIds
     * @return Reaction[]
     */
    public function getRecentForPostIds(array $postIds, string $excludeMe, int $limit): array
    {
        if (empty($postIds)) return [];
        $ph = implode(',', array_fill(0, count($postIds), '?'));
        $sql = "SELECT * FROM reactions WHERE post_id IN ($ph) AND username != ? ORDER BY created_at DESC LIMIT " . (int) $limit;
        $stmt = $this->pdo()->prepare($sql);
        $params = array_values($postIds);
        $params[] = $excludeMe;
        $stmt->execute($params);
        return array_map(fn($r) => $this->hydrate($r), $stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    public function save(Reaction $r): Reaction
    {
        $pdo = $this->pdo();
        if ($r->getCreatedAt() === null) {
            $r->setCreatedAt(new \DateTime());
        }
        if ($r->getId() === null) {
            $stmt = $pdo->prepare(
                'INSERT INTO reactions (post_id, username, reaction_type, created_at)
                 VALUES (:post_id, :username, :reaction_type, :created_at)'
            );
            $stmt->execute([
                'post_id' => $r->getPost()?->getId() ?? $r->getPostId(),
                'username' => $r->getUsername(),
                'reaction_type' => $r->getReactionType(),
                'created_at' => $r->getCreatedAt()->format('Y-m-d H:i:s'),
            ]);
            $r->setId((int) $pdo->lastInsertId());
        } else {
            $stmt = $pdo->prepare(
                'UPDATE reactions SET reaction_type = :reaction_type WHERE id = :id'
            );
            $stmt->execute([
                'id' => $r->getId(),
                'reaction_type' => $r->getReactionType(),
            ]);
        }
        return $r;
    }

    public function remove(Reaction $r): void
    {
        if ($r->getId() === null) return;
        $stmt = $this->pdo()->prepare('DELETE FROM reactions WHERE id = :id');
        $stmt->execute(['id' => $r->getId()]);
    }
}
