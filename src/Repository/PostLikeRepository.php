<?php

namespace App\Repository;

use App\Database\PdoConnectionFactory;
use App\Entity\Post;
use App\Entity\PostLike;
use App\Entity\User;

class PostLikeRepository
{
    public function __construct(private PdoConnectionFactory $pdoFactory) {}

    private function pdo(): \PDO
    {
        return $this->pdoFactory->getConnection();
    }

    private function hydrate(array $row): PostLike
    {
        $pl = (new PostLike())
            ->setId((int) $row['id'])
            ->setPostId((int) $row['post_id'])
            ->setUsername($row['username'] ?? null);
        if (!empty($row['created_at'])) {
            try { $pl->setCreatedAt(new \DateTime((string) $row['created_at'])); } catch (\Exception) {}
        }
        return $pl;
    }

    public function find(int $id): ?PostLike
    {
        $stmt = $this->pdo()->prepare('SELECT * FROM post_likes WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ? $this->hydrate($row) : null;
    }

    public function findByPostAndUser(Post $post, User $user): ?PostLike
    {
        $stmt = $this->pdo()->prepare('SELECT * FROM post_likes WHERE post_id = :p AND username = :u LIMIT 1');
        $stmt->execute(['p' => $post->getId(), 'u' => $user->getUserIdentifier()]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ? $this->hydrate($row) : null;
    }

    /**
     * @return int[]
     */
    public function getLikedPostIdsForUser(User $user): array
    {
        $stmt = $this->pdo()->prepare('SELECT post_id FROM post_likes WHERE username = :u');
        $stmt->execute(['u' => $user->getUserIdentifier()]);
        return array_map(fn($r) => (int) $r['post_id'], $stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    public function countForPost(Post $post): int
    {
        $stmt = $this->pdo()->prepare('SELECT COUNT(*) FROM post_likes WHERE post_id = :p');
        $stmt->execute(['p' => $post->getId()]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Recent likes on the given post ids, excluding self-likes.
     * @param int[] $postIds
     * @return PostLike[]
     */
    public function getRecentForPostIds(array $postIds, string $excludeMe, int $limit): array
    {
        if (empty($postIds)) return [];
        $ph = implode(',', array_fill(0, count($postIds), '?'));
        $sql = "SELECT * FROM post_likes WHERE post_id IN ($ph) AND username != ? ORDER BY created_at DESC LIMIT " . (int) $limit;
        $stmt = $this->pdo()->prepare($sql);
        $params = array_values($postIds);
        $params[] = $excludeMe;
        $stmt->execute($params);
        return array_map(fn($r) => $this->hydrate($r), $stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    public function save(PostLike $pl): PostLike
    {
        $pdo = $this->pdo();
        if ($pl->getCreatedAt() === null) {
            $pl->setCreatedAt(new \DateTime());
        }
        if ($pl->getId() === null) {
            $stmt = $pdo->prepare(
                'INSERT INTO post_likes (post_id, username, created_at)
                 VALUES (:post_id, :username, :created_at)'
            );
            $stmt->execute([
                'post_id' => $pl->getPost()?->getId() ?? $pl->getPostId(),
                'username' => $pl->getUsername(),
                'created_at' => $pl->getCreatedAt()->format('Y-m-d H:i:s'),
            ]);
            $pl->setId((int) $pdo->lastInsertId());
        }
        return $pl;
    }

    public function remove(PostLike $pl): void
    {
        if ($pl->getId() === null) return;
        $stmt = $this->pdo()->prepare('DELETE FROM post_likes WHERE id = :id');
        $stmt->execute(['id' => $pl->getId()]);
    }
}
