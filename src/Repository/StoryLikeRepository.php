<?php

namespace App\Repository;

use App\Database\PdoConnectionFactory;
use App\Entity\Story;
use App\Entity\StoryLike;

class StoryLikeRepository
{
    public function __construct(
        private PdoConnectionFactory $pdoFactory,
        private StoryRepository $storyRepository
    ) {}

    private function pdo(): \PDO
    {
        return $this->pdoFactory->getConnection();
    }

    private function hydrate(array $row): StoryLike
    {
        $sl = (new StoryLike())
            ->setLikerUsername((string) $row['liker_username']);
        $sl->setId((int) $row['id']);
        if (isset($row['story_id'])) {
            $sl->setStoryId((int) $row['story_id']);
        }
        if (!empty($row['created_at'])) {
            try { $sl->setCreatedAt(new \DateTimeImmutable((string) $row['created_at'])); } catch (\Exception) {}
        }
        return $sl;
    }

    public function findLike(Story $story, string $liker): ?StoryLike
    {
        $stmt = $this->pdo()->prepare(
            'SELECT * FROM story_likes WHERE story_id = :s AND liker_username = :u LIMIT 1'
        );
        $stmt->execute(['s' => $story->getId(), 'u' => $liker]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ? $this->hydrate($row) : null;
    }

    public function countForStory(Story $story): int
    {
        $stmt = $this->pdo()->prepare('SELECT COUNT(*) FROM story_likes WHERE story_id = :s');
        $stmt->execute(['s' => $story->getId()]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * @param Story[] $stories
     * @return array<int, bool>
     */
    public function likedMapFor(array $stories, string $liker): array
    {
        if (empty($stories)) return [];
        $ids = array_map(fn(Story $s) => $s->getId(), $stories);
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $sql = "SELECT story_id FROM story_likes WHERE story_id IN ($ph) AND liker_username = ?";
        $stmt = $this->pdo()->prepare($sql);
        $params = array_values($ids);
        $params[] = $liker;
        $stmt->execute($params);
        $map = array_fill_keys($ids, false);
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $r) {
            $map[(int) $r['story_id']] = true;
        }
        return $map;
    }

    /** @return StoryLike[] */
    public function recentForAuthor(string $username, int $limit = 15): array
    {
        // Two distinct placeholders — MySQL native prepares (emulate=false) forbid reusing :u
        $sql = 'SELECT l.* FROM story_likes l
                INNER JOIN stories s ON s.id = l.story_id
                WHERE s.auteur = :author AND l.liker_username != :liker
                ORDER BY l.created_at DESC
                LIMIT ' . (int) $limit;
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute(['author' => $username, 'liker' => $username]);
        $likes = array_map(fn($r) => $this->hydrate($r), $stmt->fetchAll(\PDO::FETCH_ASSOC));
        // Hydrate Story for each like (so $sl->getStory()?->getId() works in NotificationController)
        foreach ($likes as $sl) {
            if ($sl->getStoryId() !== null) {
                $sl->setStory($this->storyRepository->find($sl->getStoryId()));
            }
        }
        return $likes;
    }

    public function save(StoryLike $sl): StoryLike
    {
        $pdo = $this->pdo();
        if ($sl->getId() === null) {
            $stmt = $pdo->prepare(
                'INSERT INTO story_likes (story_id, liker_username, created_at)
                 VALUES (:sid, :lu, :ca)'
            );
            $stmt->execute([
                'sid' => $sl->getStory()?->getId() ?? $sl->getStoryId(),
                'lu' => $sl->getLikerUsername(),
                'ca' => $sl->getCreatedAt()->format('Y-m-d H:i:s'),
            ]);
            $sl->setId((int) $pdo->lastInsertId());
        }
        return $sl;
    }

    public function remove(StoryLike $sl): void
    {
        if ($sl->getId() === null) return;
        $stmt = $this->pdo()->prepare('DELETE FROM story_likes WHERE id = :id');
        $stmt->execute(['id' => $sl->getId()]);
    }
}
