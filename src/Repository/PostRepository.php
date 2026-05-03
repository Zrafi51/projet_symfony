<?php

namespace App\Repository;

use App\Database\PdoConnectionFactory;
use App\Entity\Comment;
use App\Entity\Image;
use App\Entity\Music;
use App\Entity\Post;
use App\Entity\Reaction;
use App\Entity\User;
use App\Entity\Video;

class PostRepository
{
    public function __construct(private PdoConnectionFactory $pdoFactory) {}

    private function pdo(): \PDO
    {
        return $this->pdoFactory->getConnection();
    }

    private function hydrate(array $row): Post
    {
        $p = new Post();
        $p->setId(isset($row['id']) ? (int) $row['id'] : null);
        $p->setAuteur($row['auteur'] ?? null);
        $p->setDescription($row['description'] ?? '');
        $p->setCheminPhoto($row['chemin_photo'] ?? '');
        $p->setLikes(isset($row['likes']) ? (int) $row['likes'] : 0);
        if (!empty($row['date_creation'])) {
            try { $p->setDateCreation(new \DateTime((string) $row['date_creation'])); } catch (\Exception) {}
        }
        if (isset($row['music_id']) && $row['music_id'] !== null) {
            $p->setMusicId((int) $row['music_id']);
        }
        return $p;
    }

    /**
     * Loads child collections (images/videos/comments/reactions/music) onto the given posts.
     * @param Post[] $posts
     */
    private function loadAssociations(array $posts): void
    {
        if (empty($posts)) return;

        $ids = [];
        $byId = [];
        $musicIds = [];
        foreach ($posts as $p) {
            if ($p->getId() !== null) {
                $ids[] = $p->getId();
                $byId[$p->getId()] = $p;
            }
            if ($p->getMusicId() !== null) {
                $musicIds[$p->getMusicId()] = true;
            }
        }
        if (empty($ids)) return;
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        // Images
        $stmt = $this->pdo()->prepare("SELECT * FROM images WHERE post_id IN ($placeholders) ORDER BY position ASC");
        $stmt->execute($ids);
        $imagesByPost = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $r) {
            $img = (new Image())
                ->setId((int) $r['id'])
                ->setPostId((int) $r['post_id'])
                ->setFilename((string) $r['filename'])
                ->setPosition(isset($r['position']) ? (int) $r['position'] : 0);
            if (isset($r['description'])) $img->setDescription($r['description']);
            $pid = (int) $r['post_id'];
            $imagesByPost[$pid][] = $img;
        }
        foreach ($byId as $pid => $p) {
            $p->setImages($imagesByPost[$pid] ?? []);
        }

        // Videos (table may not always exist — guard)
        try {
            $stmt = $this->pdo()->prepare("SELECT * FROM videos WHERE post_id IN ($placeholders) ORDER BY position ASC");
            $stmt->execute($ids);
            $videosByPost = [];
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $r) {
                $vid = (new Video())
                    ->setId((int) $r['id'])
                    ->setPostId((int) $r['post_id'])
                    ->setFilename((string) $r['filename'])
                    ->setPosition(isset($r['position']) ? (int) $r['position'] : 0);
                if (isset($r['description'])) $vid->setDescription($r['description']);
                $pid = (int) $r['post_id'];
                $videosByPost[$pid][] = $vid;
            }
            foreach ($byId as $pid => $p) {
                $p->setVideos($videosByPost[$pid] ?? []);
            }
        } catch (\PDOException) { /* table missing */ }

        // Comments
        $stmt = $this->pdo()->prepare("SELECT * FROM comments WHERE post_id IN ($placeholders) ORDER BY date_commentaire DESC");
        $stmt->execute($ids);
        $commentsByPost = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $r) {
            $c = (new Comment())
                ->setId((int) $r['id'])
                ->setPostId((int) $r['post_id'])
                ->setAuteur($r['auteur'] ?? null)
                ->setContenu((string) ($r['contenu'] ?? ''));
            if (!empty($r['date_commentaire'])) {
                try { $c->setDateCommentaire(new \DateTime((string) $r['date_commentaire'])); } catch (\Exception) {}
            }
            $pid = (int) $r['post_id'];
            // Re-link parent
            if (isset($byId[$pid])) {
                $c->setPost($byId[$pid]);
            }
            $commentsByPost[$pid][] = $c;
        }
        foreach ($byId as $pid => $p) {
            $p->setComments($commentsByPost[$pid] ?? []);
        }

        // Reactions
        $stmt = $this->pdo()->prepare("SELECT * FROM reactions WHERE post_id IN ($placeholders)");
        $stmt->execute($ids);
        $reactionsByPost = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $r) {
            $rx = (new Reaction())
                ->setId((int) $r['id'])
                ->setPostId((int) $r['post_id'])
                ->setUsername($r['username'] ?? null)
                ->setReactionType((string) ($r['reaction_type'] ?? ''));
            if (!empty($r['created_at'])) {
                try { $rx->setCreatedAt(new \DateTime((string) $r['created_at'])); } catch (\Exception) {}
            }
            $pid = (int) $r['post_id'];
            if (isset($byId[$pid])) {
                $rx->setPost($byId[$pid]);
            }
            $reactionsByPost[$pid][] = $rx;
        }
        foreach ($byId as $pid => $p) {
            $p->setReactions($reactionsByPost[$pid] ?? []);
        }

        // Music
        if (!empty($musicIds)) {
            $musicIdsArr = array_keys($musicIds);
            $mph = implode(',', array_fill(0, count($musicIdsArr), '?'));
            $stmt = $this->pdo()->prepare("SELECT * FROM music WHERE id IN ($mph)");
            $stmt->execute($musicIdsArr);
            $musicById = [];
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $r) {
                $m = (new Music())
                    ->setId((int) $r['id'])
                    ->setTitle((string) $r['title'])
                    ->setArtist($r['artist'] ?? null)
                    ->setFilename((string) ($r['filename'] ?? ''));
                if (!empty($r['uploaded_at'])) {
                    try { $m->setUploadedAt(new \DateTime((string) $r['uploaded_at'])); } catch (\Exception) {}
                }
                $musicById[$m->getId()] = $m;
            }
            foreach ($byId as $p) {
                if ($p->getMusicId() !== null && isset($musicById[$p->getMusicId()])) {
                    $p->setMusic($musicById[$p->getMusicId()]);
                }
            }
        }
    }

    public function find(int $id): ?Post
    {
        $stmt = $this->pdo()->prepare('SELECT * FROM posts WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) return null;
        $p = $this->hydrate($row);
        $this->loadAssociations([$p]);
        return $p;
    }

    /** @return Post[] */
    public function findAllOrderedByDate(): array
    {
        $rows = $this->pdo()->query('SELECT * FROM posts ORDER BY date_creation DESC')->fetchAll(\PDO::FETCH_ASSOC);
        $posts = array_map(fn($r) => $this->hydrate($r), $rows);
        $this->loadAssociations($posts);
        return $posts;
    }

    /**
     * @param string[]|null $includeAuthors
     * @param string[]|null $excludeAuthors
     * @return Post[]
     */
    public function searchPosts(
        ?string $keyword = null,
        ?\DateTimeInterface $dateFrom = null,
        ?\DateTimeInterface $dateTo = null,
        ?int $minLikes = null,
        string $sortBy = 'recent',
        ?array $includeAuthors = null,
        ?array $excludeAuthors = null
    ): array {
        $sql = 'SELECT * FROM posts WHERE 1=1';
        $params = [];

        if ($keyword) {
            // MySQL native prepares forbid reusing a placeholder — split into two.
            $sql .= ' AND (description LIKE :keyword1 OR auteur LIKE :keyword2)';
            $params['keyword1'] = '%' . $keyword . '%';
            $params['keyword2'] = '%' . $keyword . '%';
        }

        if ($includeAuthors !== null) {
            if (empty($includeAuthors)) {
                $sql .= ' AND 1 = 0';
            } else {
                $ph = [];
                foreach (array_values($includeAuthors) as $i => $a) {
                    $key = "ia$i";
                    $ph[] = ":$key";
                    $params[$key] = $a;
                }
                $sql .= ' AND auteur IN (' . implode(',', $ph) . ')';
            }
        }

        if (!empty($excludeAuthors)) {
            $ph = [];
            foreach (array_values($excludeAuthors) as $i => $a) {
                $key = "ea$i";
                $ph[] = ":$key";
                $params[$key] = $a;
            }
            $sql .= ' AND auteur NOT IN (' . implode(',', $ph) . ')';
        }

        if ($dateFrom) {
            $sql .= ' AND date_creation >= :dateFrom';
            $params['dateFrom'] = $dateFrom->format('Y-m-d H:i:s');
        }

        if ($dateTo) {
            $dateTo = (clone $dateTo)->modify('+1 day');
            $sql .= ' AND date_creation < :dateTo';
            $params['dateTo'] = $dateTo->format('Y-m-d H:i:s');
        }

        if ($minLikes !== null && $minLikes > 0) {
            $sql .= ' AND likes >= :minLikes';
            $params['minLikes'] = $minLikes;
        }

        switch ($sortBy) {
            case 'oldest':       $sql .= ' ORDER BY date_creation ASC'; break;
            case 'most_liked':   $sql .= ' ORDER BY likes DESC'; break;
            case 'least_liked':  $sql .= ' ORDER BY likes ASC'; break;
            default:             $sql .= ' ORDER BY date_creation DESC';
        }

        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute($params);
        $posts = array_map(fn($r) => $this->hydrate($r), $stmt->fetchAll(\PDO::FETCH_ASSOC));
        $this->loadAssociations($posts);
        return $posts;
    }

    /** @return Post[] */
    public function findByUser(User $user): array
    {
        $stmt = $this->pdo()->prepare('SELECT * FROM posts WHERE auteur = :u ORDER BY date_creation DESC');
        $stmt->execute(['u' => $user->getUsername()]);
        $posts = array_map(fn($r) => $this->hydrate($r), $stmt->fetchAll(\PDO::FETCH_ASSOC));
        $this->loadAssociations($posts);
        return $posts;
    }

    public function getPostCountByUser(User $user): int
    {
        $stmt = $this->pdo()->prepare('SELECT COUNT(*) FROM posts WHERE auteur = :u');
        $stmt->execute(['u' => $user->getUsername()]);
        return (int) $stmt->fetchColumn();
    }

    public function getTotalLikesReceived(User $user): int
    {
        $stmt = $this->pdo()->prepare('SELECT COALESCE(SUM(likes), 0) FROM posts WHERE auteur = :u');
        $stmt->execute(['u' => $user->getUsername()]);
        return (int) $stmt->fetchColumn();
    }

    public function getPostsPerMonth(User $user, int $months = 12): array
    {
        $sql = "SELECT DATE_FORMAT(date_creation, '%Y-%m') as month, COUNT(*) as count
                FROM posts
                WHERE auteur = :username
                GROUP BY month
                ORDER BY month DESC
                LIMIT " . (int) $months;

        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute(['username' => $user->getUsername()]);
        $data = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $data[$row['month']] = (int) $row['count'];
        }
        return $data;
    }

    /** @return Post[] */
    public function getRecentPostsByAuthors(array $authors, string $excludeMe, int $limit): array
    {
        if (empty($authors)) return [];
        $ph = [];
        $params = ['me' => $excludeMe];
        foreach (array_values($authors) as $i => $a) {
            $key = "a$i";
            $ph[] = ":$key";
            $params[$key] = $a;
        }
        $sql = 'SELECT * FROM posts WHERE auteur IN (' . implode(',', $ph) . ') AND auteur != :me ORDER BY date_creation DESC LIMIT ' . (int) $limit;
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute($params);
        $posts = array_map(fn($r) => $this->hydrate($r), $stmt->fetchAll(\PDO::FETCH_ASSOC));
        $this->loadAssociations($posts);
        return $posts;
    }

    public function countAll(): int
    {
        return (int) $this->pdo()->query('SELECT COUNT(*) FROM posts')->fetchColumn();
    }

    public function save(Post $p): Post
    {
        $pdo = $this->pdo();
        if ($p->getDateCreation() === null) {
            $p->setDateCreation(new \DateTime());
        }
        if ($p->getId() === null) {
            $stmt = $pdo->prepare(
                'INSERT INTO posts (auteur, description, chemin_photo, likes, date_creation, music_id)
                 VALUES (:auteur, :description, :chemin_photo, :likes, :date_creation, :music_id)'
            );
            $stmt->execute([
                'auteur' => $p->getAuteur(),
                'description' => $p->getDescription(),
                'chemin_photo' => $p->getCheminPhoto() ?? '',
                'likes' => $p->getLikes(),
                'date_creation' => $p->getDateCreation()->format('Y-m-d H:i:s'),
                'music_id' => $p->getMusicId(),
            ]);
            $p->setId((int) $pdo->lastInsertId());
        } else {
            $stmt = $pdo->prepare(
                'UPDATE posts SET auteur = :auteur, description = :description, chemin_photo = :chemin_photo,
                 likes = :likes, music_id = :music_id WHERE id = :id'
            );
            $stmt->execute([
                'id' => $p->getId(),
                'auteur' => $p->getAuteur(),
                'description' => $p->getDescription(),
                'chemin_photo' => $p->getCheminPhoto() ?? '',
                'likes' => $p->getLikes(),
                'music_id' => $p->getMusicId(),
            ]);
        }
        return $p;
    }

    /**
     * Save the post AND its child media collections (images/videos) and any
     * comments/reactions whose ID is null. Designed to be called after the
     * controller has manipulated the in-memory collections.
     */
    public function saveWithMedia(Post $p, ImageRepository $images, VideoRepository $videos): Post
    {
        $this->save($p);
        // Persist images
        foreach ($p->getImages() as $img) {
            $img->setPost($p);
            $images->save($img);
        }
        // Persist videos
        foreach ($p->getVideos() as $v) {
            $v->setPost($p);
            $videos->save($v);
        }
        return $p;
    }

    public function remove(Post $p): void
    {
        if ($p->getId() === null) return;
        $pdo = $this->pdo();
        // Clean child rows first (DB might not have CASCADE).
        try { $stmt = $pdo->prepare('DELETE FROM images WHERE post_id = :id'); $stmt->execute(['id' => $p->getId()]); } catch (\PDOException) {}
        try { $stmt = $pdo->prepare('DELETE FROM videos WHERE post_id = :id'); $stmt->execute(['id' => $p->getId()]); } catch (\PDOException) {}
        try { $stmt = $pdo->prepare('DELETE FROM comments WHERE post_id = :id'); $stmt->execute(['id' => $p->getId()]); } catch (\PDOException) {}
        try { $stmt = $pdo->prepare('DELETE FROM reactions WHERE post_id = :id'); $stmt->execute(['id' => $p->getId()]); } catch (\PDOException) {}
        try { $stmt = $pdo->prepare('DELETE FROM post_likes WHERE post_id = :id'); $stmt->execute(['id' => $p->getId()]); } catch (\PDOException) {}
        $stmt = $pdo->prepare('DELETE FROM posts WHERE id = :id');
        $stmt->execute(['id' => $p->getId()]);
    }
}
