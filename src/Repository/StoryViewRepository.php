<?php

namespace App\Repository;

use App\Database\PdoConnectionFactory;
use App\Entity\Story;
use App\Entity\StoryView;

class StoryViewRepository
{
    public function __construct(
        private PdoConnectionFactory $pdoFactory,
        private StoryRepository $storyRepository
    ) {}

    private function pdo(): \PDO
    {
        return $this->pdoFactory->getConnection();
    }

    private function hydrate(array $row): StoryView
    {
        $sv = (new StoryView())
            ->setViewerUsername((string) $row['viewer_username']);
        $sv->setId((int) $row['id']);
        if (isset($row['story_id'])) {
            $sv->setStoryId((int) $row['story_id']);
        }
        if (!empty($row['viewed_at'])) {
            try { $sv->setViewedAt(new \DateTimeImmutable((string) $row['viewed_at'])); } catch (\Exception) {}
        }
        return $sv;
    }

    /**
     * Idempotent insert: returns true if a new row was created.
     */
    public function recordOnce(Story $story, string $viewer): bool
    {
        if ($story->getAuteur() === $viewer) {
            return false;
        }
        $pdo = $this->pdo();
        $stmt = $pdo->prepare(
            'SELECT id FROM story_views WHERE story_id = :sid AND viewer_username = :v LIMIT 1'
        );
        $stmt->execute(['sid' => $story->getId(), 'v' => $viewer]);
        if ($stmt->fetchColumn()) {
            return false;
        }
        $stmt = $pdo->prepare(
            'INSERT INTO story_views (story_id, viewer_username, viewed_at)
             VALUES (:sid, :v, :va)'
        );
        $stmt->execute([
            'sid' => $story->getId(),
            'v' => $viewer,
            'va' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);
        return true;
    }

    /**
     * Attach Story entity for hydrated views.
     * @param StoryView[] $views
     */
    private function loadStories(array $views): void
    {
        $ids = [];
        foreach ($views as $v) {
            if ($v->getStoryId() !== null) {
                $ids[$v->getStoryId()] = true;
            }
        }
        if (empty($ids)) return;
        $idArr = array_keys($ids);
        $ph = implode(',', array_fill(0, count($idArr), '?'));
        $stmt = $this->pdo()->prepare("SELECT * FROM stories WHERE id IN ($ph)");
        $stmt->execute($idArr);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        // Reuse story hydration via the repo
        $byId = [];
        foreach ($rows as $r) {
            $s = $this->storyRepository->find((int) $r['id']);
            if ($s) $byId[$s->getId()] = $s;
        }
        foreach ($views as $v) {
            if ($v->getStoryId() !== null && isset($byId[$v->getStoryId()])) {
                $v->setStory($byId[$v->getStoryId()]);
            }
        }
    }

    /** @return StoryView[] */
    public function recentForAuthor(string $username, int $limit = 15): array
    {
        // Two distinct placeholders — MySQL native prepares (emulate=false) forbid reusing :u
        $sql = 'SELECT v.* FROM story_views v
                INNER JOIN stories s ON s.id = v.story_id
                WHERE s.auteur = :author AND v.viewer_username != :viewer
                ORDER BY v.viewed_at DESC
                LIMIT ' . (int) $limit;
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute(['author' => $username, 'viewer' => $username]);
        $views = array_map(fn($r) => $this->hydrate($r), $stmt->fetchAll(\PDO::FETCH_ASSOC));
        $this->loadStories($views);
        return $views;
    }

    public function countForStory(Story $story): int
    {
        $stmt = $this->pdo()->prepare('SELECT COUNT(*) FROM story_views WHERE story_id = :s');
        $stmt->execute(['s' => $story->getId()]);
        return (int) $stmt->fetchColumn();
    }
}
