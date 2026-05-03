<?php

namespace App\Repository;

use App\Database\PdoConnectionFactory;
use App\Entity\Comment;
use App\Entity\Post;
use App\Entity\User;

class CommentRepository
{
    public function __construct(private PdoConnectionFactory $pdoFactory) {}

    private function pdo(): \PDO
    {
        return $this->pdoFactory->getConnection();
    }

    private function hydrate(array $row): Comment
    {
        $c = (new Comment())
            ->setId((int) $row['id'])
            ->setPostId((int) $row['post_id'])
            ->setAuteur($row['auteur'] ?? null)
            ->setContenu((string) ($row['contenu'] ?? ''));
        if (!empty($row['date_commentaire'])) {
            try { $c->setDateCommentaire(new \DateTime((string) $row['date_commentaire'])); } catch (\Exception) {}
        }
        // Attach a thin Post stub so templates that read `comment.post.id`
        // (admin/comments.html.twig) don't dereference null. The full Post is
        // not loaded here — only its id is needed in those views.
        if (!empty($row['post_id'])) {
            $stub = (new Post())->setId((int) $row['post_id']);
            $c->setPost($stub);
        }
        return $c;
    }

    public function find(int $id): ?Comment
    {
        $stmt = $this->pdo()->prepare('SELECT * FROM comments WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ? $this->hydrate($row) : null;
    }

    /** @return Comment[] */
    public function findByPost(Post $post): array
    {
        $stmt = $this->pdo()->prepare('SELECT * FROM comments WHERE post_id = :p ORDER BY date_commentaire DESC');
        $stmt->execute(['p' => $post->getId()]);
        return array_map(fn($r) => $this->hydrate($r), $stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    public function getTotalCommentsForUser(User $user): int
    {
        $stmt = $this->pdo()->prepare(
            'SELECT COUNT(c.id) FROM comments c
             INNER JOIN posts p ON p.id = c.post_id
             WHERE p.auteur = :u'
        );
        $stmt->execute(['u' => $user->getUsername()]);
        return (int) $stmt->fetchColumn();
    }

    public function countAll(): int
    {
        return (int) $this->pdo()->query('SELECT COUNT(*) FROM comments')->fetchColumn();
    }

    /** @return Comment[] */
    public function findAllOrderedByDate(): array
    {
        $rows = $this->pdo()->query('SELECT * FROM comments ORDER BY date_commentaire DESC')->fetchAll(\PDO::FETCH_ASSOC);
        return array_map(fn($r) => $this->hydrate($r), $rows);
    }

    /**
     * Recent comments on the given post ids, excluding ones authored by $excludeMe.
     * @param int[] $postIds
     * @return Comment[]
     */
    public function getRecentForPostIds(array $postIds, string $excludeMe, int $limit): array
    {
        if (empty($postIds)) return [];
        $ph = implode(',', array_fill(0, count($postIds), '?'));
        $sql = "SELECT * FROM comments WHERE post_id IN ($ph) AND auteur != ? ORDER BY date_commentaire DESC LIMIT " . (int) $limit;
        $stmt = $this->pdo()->prepare($sql);
        $params = array_values($postIds);
        $params[] = $excludeMe;
        $stmt->execute($params);
        return array_map(fn($r) => $this->hydrate($r), $stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    public function save(Comment $c): Comment
    {
        $pdo = $this->pdo();
        if ($c->getDateCommentaire() === null) {
            $c->setDateCommentaire(new \DateTime());
        }
        if ($c->getId() === null) {
            $stmt = $pdo->prepare(
                'INSERT INTO comments (post_id, auteur, contenu, date_commentaire)
                 VALUES (:post_id, :auteur, :contenu, :date_commentaire)'
            );
            $stmt->execute([
                'post_id' => $c->getPost()?->getId() ?? $c->getPostId(),
                'auteur' => $c->getAuteur(),
                'contenu' => $c->getContenu(),
                'date_commentaire' => $c->getDateCommentaire()->format('Y-m-d H:i:s'),
            ]);
            $c->setId((int) $pdo->lastInsertId());
        } else {
            $stmt = $pdo->prepare(
                'UPDATE comments SET auteur = :auteur, contenu = :contenu WHERE id = :id'
            );
            $stmt->execute([
                'id' => $c->getId(),
                'auteur' => $c->getAuteur(),
                'contenu' => $c->getContenu(),
            ]);
        }
        return $c;
    }

    public function remove(Comment $c): void
    {
        if ($c->getId() === null) return;
        $stmt = $this->pdo()->prepare('DELETE FROM comments WHERE id = :id');
        $stmt->execute(['id' => $c->getId()]);
    }
}
