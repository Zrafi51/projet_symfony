<?php

namespace App\Repository;

use App\Database\PdoConnectionFactory;
use PDO;
use RuntimeException;

final class ForumRepository
{
    private const USER_TABLE = '`user`';
    private const POSTS_TABLE = 'forum_posts';
    private const COMMENTS_TABLE = 'forum_comments';
    private const STORIES_TABLE = 'forum_stories';
    private const REACTIONS_TABLE = 'forum_reactions';
    private const STORY_VIEWS_TABLE = 'forum_story_views';

    private const REACTION_CODES = [
        'LIKE',
        'LOVE',
        'WOW',
        'TRAVEL',
        'FIRE',
    ];

    private bool $schemaEnsured = false;

    public function __construct(private readonly PdoConnectionFactory $connectionFactory)
    {
    }

    public function isDatabaseAvailable(): bool
    {
        try {
            $this->connectionFactory->getConnection();

            return true;
        } catch (RuntimeException) {
            return false;
        }
    }

    public function getCommunityStats(): array
    {
        $this->ensureSchema();

        return [
            'posts' => (int) $this->fetchScalar('SELECT COUNT(*) FROM '.self::POSTS_TABLE, 0),
            'comments' => (int) $this->fetchScalar('SELECT COUNT(*) FROM '.self::COMMENTS_TABLE, 0),
            'stories' => (int) $this->fetchScalar('SELECT COUNT(*) FROM '.self::STORIES_TABLE.' WHERE expires_at > NOW()', 0),
            'reactions' => (int) $this->fetchScalar('SELECT COUNT(*) FROM '.self::REACTIONS_TABLE, 0),
            'authors' => (int) $this->fetchScalar('SELECT COUNT(DISTINCT user_id) FROM '.self::POSTS_TABLE, 0),
        ];
    }

    public function getFeed(): array
    {
        $this->ensureSchema();
        $statement = $this->connectionFactory->getConnection()->query(
            'SELECT p.*,
                    u.email AS author_email,
                    u.prenom AS author_prenom,
                    u.nom AS author_nom,
                    u.photo_url AS author_photo_url,
                    COALESCE(comment_counts.comment_count, 0) AS comment_count
             FROM '.self::POSTS_TABLE.' p
             LEFT JOIN '.self::USER_TABLE.' u ON u.id = p.user_id
             LEFT JOIN (
                SELECT post_id, COUNT(*) AS comment_count
                FROM '.self::COMMENTS_TABLE.'
                GROUP BY post_id
             ) comment_counts ON comment_counts.post_id = p.id
             ORDER BY p.created_at DESC, p.id DESC'
        );

        return array_map(
            fn (array $row): array => $this->mapPost($row),
            $statement->fetchAll(PDO::FETCH_ASSOC)
        );
    }

    public function getCommentsByPostIds(array $postIds): array
    {
        $this->ensureSchema();
        $postIds = $this->normalizeIdList($postIds);
        if ($postIds === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($postIds), '?'));
        $statement = $this->connectionFactory->getConnection()->prepare(
            'SELECT c.*,
                    u.email AS author_email,
                    u.prenom AS author_prenom,
                    u.nom AS author_nom,
                    u.photo_url AS author_photo_url
             FROM '.self::COMMENTS_TABLE.' c
             LEFT JOIN '.self::USER_TABLE.' u ON u.id = c.user_id
             WHERE c.post_id IN ('.$placeholders.')
             ORDER BY c.created_at ASC, c.id ASC'
        );
        $statement->execute($postIds);

        $grouped = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $mapped = $this->mapComment($row);
            $grouped[$mapped['post_id']][] = $mapped;
        }

        return $grouped;
    }

    public function getReactionSummaryByPostIds(array $postIds): array
    {
        $this->ensureSchema();
        $postIds = $this->normalizeIdList($postIds);
        if ($postIds === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($postIds), '?'));
        $statement = $this->connectionFactory->getConnection()->prepare(
            'SELECT post_id, reaction_code, COUNT(*) AS reaction_count
             FROM '.self::REACTIONS_TABLE.'
             WHERE post_id IN ('.$placeholders.')
             GROUP BY post_id, reaction_code
             ORDER BY post_id ASC, reaction_count DESC, reaction_code ASC'
        );
        $statement->execute($postIds);

        $grouped = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $postId = (int) ($row['post_id'] ?? 0);
            $reactionCode = $this->normalizeReactionCode((string) ($row['reaction_code'] ?? ''));
            $reactionCount = (int) ($row['reaction_count'] ?? 0);
            if ($postId <= 0 || $reactionCode === null || $reactionCount <= 0) {
                continue;
            }

            if (!isset($grouped[$postId])) {
                $grouped[$postId] = [
                    'total' => 0,
                    'items' => [],
                ];
            }

            $grouped[$postId]['total'] += $reactionCount;
            $grouped[$postId]['items'][] = [
                'code' => $reactionCode,
                'count' => $reactionCount,
            ];
        }

        return $grouped;
    }

    public function getUserReactionMap(int $userId, array $postIds): array
    {
        $this->ensureSchema();
        $postIds = $this->normalizeIdList($postIds);
        if ($userId <= 0 || $postIds === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($postIds), '?'));
        $statement = $this->connectionFactory->getConnection()->prepare(
            'SELECT post_id, reaction_code
             FROM '.self::REACTIONS_TABLE.'
             WHERE user_id = ? AND post_id IN ('.$placeholders.')'
        );
        $statement->execute(array_merge([$userId], $postIds));

        $map = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $postId = (int) ($row['post_id'] ?? 0);
            $reactionCode = $this->normalizeReactionCode((string) ($row['reaction_code'] ?? ''));
            if ($postId > 0 && $reactionCode !== null) {
                $map[$postId] = $reactionCode;
            }
        }

        return $map;
    }

    public function getActiveStories(): array
    {
        $this->ensureSchema();
        $statement = $this->connectionFactory->getConnection()->query(
            'SELECT s.*,
                    u.email AS author_email,
                    u.prenom AS author_prenom,
                    u.nom AS author_nom,
                    u.photo_url AS author_photo_url
             FROM '.self::STORIES_TABLE.' s
             LEFT JOIN '.self::USER_TABLE.' u ON u.id = s.user_id
             WHERE s.expires_at > NOW()
             ORDER BY s.created_at DESC, s.id DESC
             LIMIT 16'
        );

        return array_map(
            fn (array $row): array => $this->mapStory($row),
            $statement->fetchAll(PDO::FETCH_ASSOC)
        );
    }

    public function getStoryViewCountsByStoryIds(array $storyIds): array
    {
        $this->ensureSchema();
        $storyIds = $this->normalizeIdList($storyIds);
        if ($storyIds === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($storyIds), '?'));
        $statement = $this->connectionFactory->getConnection()->prepare(
            'SELECT story_id, COUNT(*) AS view_count
             FROM '.self::STORY_VIEWS_TABLE.'
             WHERE story_id IN ('.$placeholders.')
             GROUP BY story_id'
        );
        $statement->execute($storyIds);

        $counts = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $storyId = (int) ($row['story_id'] ?? 0);
            if ($storyId > 0) {
                $counts[$storyId] = (int) ($row['view_count'] ?? 0);
            }
        }

        return $counts;
    }

    public function getPostById(int $postId): ?array
    {
        $this->ensureSchema();
        $statement = $this->connectionFactory->getConnection()->prepare(
            'SELECT p.*,
                    u.email AS author_email,
                    u.prenom AS author_prenom,
                    u.nom AS author_nom,
                    u.photo_url AS author_photo_url
             FROM '.self::POSTS_TABLE.' p
             LEFT JOIN '.self::USER_TABLE.' u ON u.id = p.user_id
             WHERE p.id = :id
             LIMIT 1'
        );
        $statement->execute(['id' => $postId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->mapPost($row) : null;
    }

    public function getCommentById(int $commentId): ?array
    {
        $this->ensureSchema();
        $statement = $this->connectionFactory->getConnection()->prepare(
            'SELECT * FROM '.self::COMMENTS_TABLE.' WHERE id = :id LIMIT 1'
        );
        $statement->execute(['id' => $commentId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->mapComment($row) : null;
    }

    public function getStoryById(int $storyId): ?array
    {
        $this->ensureSchema();
        $statement = $this->connectionFactory->getConnection()->prepare(
            'SELECT * FROM '.self::STORIES_TABLE.' WHERE id = :id LIMIT 1'
        );
        $statement->execute(['id' => $storyId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->mapStory($row) : null;
    }

    public function createPost(int $userId, string $title, string $content, ?string $imagePath): int
    {
        $this->ensureSchema();
        $statement = $this->connectionFactory->getConnection()->prepare(
            'INSERT INTO '.self::POSTS_TABLE.' (user_id, title, content, image_path, created_at, updated_at)
             VALUES (:user_id, :title, :content, :image_path, NOW(), NOW())'
        );
        $statement->execute([
            'user_id' => $userId,
            'title' => trim($title),
            'content' => trim($content),
            'image_path' => $this->nullableString($imagePath),
        ]);

        return (int) $this->connectionFactory->getConnection()->lastInsertId();
    }

    public function updatePost(int $postId, string $title, string $content, ?string $imagePath): bool
    {
        $this->ensureSchema();
        $statement = $this->connectionFactory->getConnection()->prepare(
            'UPDATE '.self::POSTS_TABLE.'
             SET title = :title,
                 content = :content,
                 image_path = :image_path,
                 updated_at = NOW()
             WHERE id = :id'
        );

        return $statement->execute([
            'id' => $postId,
            'title' => trim($title),
            'content' => trim($content),
            'image_path' => $this->nullableString($imagePath),
        ]);
    }

    public function deletePost(int $postId): bool
    {
        $this->ensureSchema();
        $connection = $this->connectionFactory->getConnection();
        $connection->beginTransaction();

        try {
            $reactionsStatement = $connection->prepare('DELETE FROM '.self::REACTIONS_TABLE.' WHERE post_id = :post_id');
            $reactionsStatement->execute(['post_id' => $postId]);

            $commentsStatement = $connection->prepare('DELETE FROM '.self::COMMENTS_TABLE.' WHERE post_id = :post_id');
            $commentsStatement->execute(['post_id' => $postId]);

            $postStatement = $connection->prepare('DELETE FROM '.self::POSTS_TABLE.' WHERE id = :id');
            $postStatement->execute(['id' => $postId]);

            $connection->commit();

            return true;
        } catch (\Throwable) {
            $connection->rollBack();

            return false;
        }
    }

    public function createComment(int $postId, int $userId, string $content): int
    {
        $this->ensureSchema();
        $statement = $this->connectionFactory->getConnection()->prepare(
            'INSERT INTO '.self::COMMENTS_TABLE.' (post_id, user_id, content, created_at, updated_at)
             VALUES (:post_id, :user_id, :content, NOW(), NOW())'
        );
        $statement->execute([
            'post_id' => $postId,
            'user_id' => $userId,
            'content' => trim($content),
        ]);

        return (int) $this->connectionFactory->getConnection()->lastInsertId();
    }

    public function deleteComment(int $commentId): bool
    {
        $this->ensureSchema();
        $statement = $this->connectionFactory->getConnection()->prepare(
            'DELETE FROM '.self::COMMENTS_TABLE.' WHERE id = :id'
        );

        return $statement->execute(['id' => $commentId]);
    }

    public function createStory(int $userId, string $caption, ?string $imagePath, \DateTimeInterface $expiresAt): int
    {
        $this->ensureSchema();
        $statement = $this->connectionFactory->getConnection()->prepare(
            'INSERT INTO '.self::STORIES_TABLE.' (user_id, caption, image_path, created_at, expires_at)
             VALUES (:user_id, :caption, :image_path, NOW(), :expires_at)'
        );
        $statement->execute([
            'user_id' => $userId,
            'caption' => trim($caption),
            'image_path' => $this->nullableString($imagePath),
            'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
        ]);

        return (int) $this->connectionFactory->getConnection()->lastInsertId();
    }

    public function deleteStory(int $storyId): bool
    {
        $this->ensureSchema();
        $connection = $this->connectionFactory->getConnection();
        $connection->beginTransaction();

        try {
            $viewsStatement = $connection->prepare('DELETE FROM '.self::STORY_VIEWS_TABLE.' WHERE story_id = :story_id');
            $viewsStatement->execute(['story_id' => $storyId]);

            $storyStatement = $connection->prepare('DELETE FROM '.self::STORIES_TABLE.' WHERE id = :id');
            $storyStatement->execute(['id' => $storyId]);

            $connection->commit();

            return true;
        } catch (\Throwable) {
            $connection->rollBack();

            return false;
        }
    }

    public function recordStoryView(int $storyId, int $userId = 0, ?string $viewerKey = null): int
    {
        $this->ensureSchema();

        $userId = $userId > 0 ? $userId : 0;
        $viewerKey = $this->nullableString($viewerKey);
        if ($userId <= 0 && $viewerKey === null) {
            throw new RuntimeException('Viewer story invalide.');
        }

        $connection = $this->connectionFactory->getConnection();
        if ($userId > 0) {
            $statement = $connection->prepare(
                'INSERT INTO '.self::STORY_VIEWS_TABLE.' (story_id, user_id, viewer_key, viewed_at)
                 VALUES (:story_id, :user_id, NULL, NOW())
                 ON DUPLICATE KEY UPDATE viewed_at = viewed_at'
            );
            $statement->execute([
                'story_id' => $storyId,
                'user_id' => $userId,
            ]);
        } else {
            $statement = $connection->prepare(
                'INSERT INTO '.self::STORY_VIEWS_TABLE.' (story_id, user_id, viewer_key, viewed_at)
                 VALUES (:story_id, NULL, :viewer_key, NOW())
                 ON DUPLICATE KEY UPDATE viewed_at = viewed_at'
            );
            $statement->execute([
                'story_id' => $storyId,
                'viewer_key' => $viewerKey,
            ]);
        }

        return (int) $this->fetchScalar(
            'SELECT COUNT(*) FROM '.self::STORY_VIEWS_TABLE.' WHERE story_id = '.(int) $storyId,
            0
        );
    }

    public function setPostReaction(int $postId, int $userId, string $reactionCode): string
    {
        $this->ensureSchema();
        $reactionCode = $this->normalizeReactionCode($reactionCode);
        if ($reactionCode === null) {
            throw new RuntimeException('Reaction forum invalide.');
        }

        $connection = $this->connectionFactory->getConnection();
        $statement = $connection->prepare(
            'SELECT id, reaction_code
             FROM '.self::REACTIONS_TABLE.'
             WHERE post_id = :post_id AND user_id = :user_id
             LIMIT 1'
        );
        $statement->execute([
            'post_id' => $postId,
            'user_id' => $userId,
        ]);
        $existingReaction = $statement->fetch(PDO::FETCH_ASSOC);

        if (is_array($existingReaction)) {
            $currentReactionCode = $this->normalizeReactionCode((string) ($existingReaction['reaction_code'] ?? ''));
            if ($currentReactionCode === $reactionCode) {
                $deleteStatement = $connection->prepare(
                    'DELETE FROM '.self::REACTIONS_TABLE.' WHERE id = :id'
                );
                $deleteStatement->execute([
                    'id' => (int) ($existingReaction['id'] ?? 0),
                ]);

                return 'removed';
            }

            $updateStatement = $connection->prepare(
                'UPDATE '.self::REACTIONS_TABLE.'
                 SET reaction_code = :reaction_code,
                     updated_at = NOW()
                 WHERE id = :id'
            );
            $updateStatement->execute([
                'id' => (int) ($existingReaction['id'] ?? 0),
                'reaction_code' => $reactionCode,
            ]);

            return 'updated';
        }

        $insertStatement = $connection->prepare(
            'INSERT INTO '.self::REACTIONS_TABLE.' (post_id, user_id, reaction_code, created_at, updated_at)
             VALUES (:post_id, :user_id, :reaction_code, NOW(), NOW())'
        );
        $insertStatement->execute([
            'post_id' => $postId,
            'user_id' => $userId,
            'reaction_code' => $reactionCode,
        ]);

        return 'created';
    }

    private function ensureSchema(): void
    {
        if ($this->schemaEnsured) {
            return;
        }

        $connection = $this->connectionFactory->getConnection();
        $connection->exec(
            'CREATE TABLE IF NOT EXISTS '.self::POSTS_TABLE.' (
                id INT NOT NULL AUTO_INCREMENT,
                user_id INT NOT NULL,
                title VARCHAR(160) NOT NULL,
                content TEXT NOT NULL,
                image_path VARCHAR(255) DEFAULT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME DEFAULT NULL,
                PRIMARY KEY (id),
                KEY idx_forum_posts_user (user_id),
                KEY idx_forum_posts_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $connection->exec(
            'CREATE TABLE IF NOT EXISTS '.self::COMMENTS_TABLE.' (
                id INT NOT NULL AUTO_INCREMENT,
                post_id INT NOT NULL,
                user_id INT NOT NULL,
                content TEXT NOT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME DEFAULT NULL,
                PRIMARY KEY (id),
                KEY idx_forum_comments_post (post_id),
                KEY idx_forum_comments_user (user_id),
                KEY idx_forum_comments_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $connection->exec(
            'CREATE TABLE IF NOT EXISTS '.self::STORIES_TABLE.' (
                id INT NOT NULL AUTO_INCREMENT,
                user_id INT NOT NULL,
                caption VARCHAR(180) NOT NULL,
                image_path VARCHAR(255) DEFAULT NULL,
                created_at DATETIME NOT NULL,
                expires_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                KEY idx_forum_stories_user (user_id),
                KEY idx_forum_stories_expires (expires_at),
                KEY idx_forum_stories_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $this->ensureStoriesExpiresAtColumnCompatible($connection);

        $connection->exec(
            'CREATE TABLE IF NOT EXISTS '.self::REACTIONS_TABLE.' (
                id INT NOT NULL AUTO_INCREMENT,
                post_id INT NOT NULL,
                user_id INT NOT NULL,
                reaction_code VARCHAR(20) NOT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME DEFAULT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_forum_reactions_post_user (post_id, user_id),
                KEY idx_forum_reactions_post (post_id),
                KEY idx_forum_reactions_user (user_id),
                KEY idx_forum_reactions_code (reaction_code)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $connection->exec(
            'CREATE TABLE IF NOT EXISTS '.self::STORY_VIEWS_TABLE.' (
                id INT NOT NULL AUTO_INCREMENT,
                story_id INT NOT NULL,
                user_id INT DEFAULT NULL,
                viewer_key VARCHAR(120) DEFAULT NULL,
                viewed_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_forum_story_views_story_user (story_id, user_id),
                UNIQUE KEY uniq_forum_story_views_story_viewer (story_id, viewer_key),
                KEY idx_forum_story_views_story (story_id),
                KEY idx_forum_story_views_viewed (viewed_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $this->ensureReactionUniqueIndex($connection);
        $this->ensureStoryViewsIndexes($connection);
        $this->schemaEnsured = true;
    }

    private function ensureStoriesExpiresAtColumnCompatible(PDO $connection): void
    {
        $column = $connection->query(
            "SHOW COLUMNS FROM ".self::STORIES_TABLE." LIKE 'expires_at'"
        )?->fetch(PDO::FETCH_ASSOC);

        $columnType = strtolower((string) ($column['Type'] ?? ''));
        if ($columnType !== '' && str_contains($columnType, 'timestamp')) {
            $connection->exec(
                'ALTER TABLE '.self::STORIES_TABLE.' MODIFY expires_at DATETIME NOT NULL'
            );
        }
    }

    private function ensureReactionUniqueIndex(PDO $connection): void
    {
        $index = $connection->query(
            "SHOW INDEX FROM ".self::REACTIONS_TABLE." WHERE Key_name = 'uniq_forum_reactions_post_user'"
        )?->fetch(PDO::FETCH_ASSOC);

        if (!is_array($index)) {
            $connection->exec(
                'ALTER TABLE '.self::REACTIONS_TABLE.'
                 ADD UNIQUE KEY uniq_forum_reactions_post_user (post_id, user_id)'
            );
        }
    }

    private function ensureStoryViewsIndexes(PDO $connection): void
    {
        $userIndex = $connection->query(
            "SHOW INDEX FROM ".self::STORY_VIEWS_TABLE." WHERE Key_name = 'uniq_forum_story_views_story_user'"
        )?->fetch(PDO::FETCH_ASSOC);
        if (!is_array($userIndex)) {
            $connection->exec(
                'ALTER TABLE '.self::STORY_VIEWS_TABLE.'
                 ADD UNIQUE KEY uniq_forum_story_views_story_user (story_id, user_id)'
            );
        }

        $viewerIndex = $connection->query(
            "SHOW INDEX FROM ".self::STORY_VIEWS_TABLE." WHERE Key_name = 'uniq_forum_story_views_story_viewer'"
        )?->fetch(PDO::FETCH_ASSOC);
        if (!is_array($viewerIndex)) {
            $connection->exec(
                'ALTER TABLE '.self::STORY_VIEWS_TABLE.'
                 ADD UNIQUE KEY uniq_forum_story_views_story_viewer (story_id, viewer_key)'
            );
        }
    }

    private function mapPost(array $row): array
    {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'user_id' => (int) ($row['user_id'] ?? 0),
            'title' => trim((string) ($row['title'] ?? '')),
            'content' => trim((string) ($row['content'] ?? '')),
            'image_path' => $this->nullableString($row['image_path'] ?? null),
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
            'comment_count' => (int) ($row['comment_count'] ?? 0),
            'author_email' => (string) ($row['author_email'] ?? ''),
            'author_display_name' => $this->buildDisplayName(
                (string) ($row['author_prenom'] ?? ''),
                (string) ($row['author_nom'] ?? ''),
                (string) ($row['author_email'] ?? '')
            ),
            'author_photo_url' => (string) ($row['author_photo_url'] ?? ''),
        ];
    }

    private function mapComment(array $row): array
    {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'post_id' => (int) ($row['post_id'] ?? 0),
            'user_id' => (int) ($row['user_id'] ?? 0),
            'content' => trim((string) ($row['content'] ?? '')),
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
            'author_email' => (string) ($row['author_email'] ?? ''),
            'author_display_name' => $this->buildDisplayName(
                (string) ($row['author_prenom'] ?? ''),
                (string) ($row['author_nom'] ?? ''),
                (string) ($row['author_email'] ?? '')
            ),
            'author_photo_url' => (string) ($row['author_photo_url'] ?? ''),
        ];
    }

    private function mapStory(array $row): array
    {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'user_id' => (int) ($row['user_id'] ?? 0),
            'caption' => trim((string) ($row['caption'] ?? '')),
            'image_path' => $this->nullableString($row['image_path'] ?? null),
            'created_at' => $row['created_at'] ?? null,
            'expires_at' => $row['expires_at'] ?? null,
            'author_email' => (string) ($row['author_email'] ?? ''),
            'author_display_name' => $this->buildDisplayName(
                (string) ($row['author_prenom'] ?? ''),
                (string) ($row['author_nom'] ?? ''),
                (string) ($row['author_email'] ?? '')
            ),
            'author_photo_url' => (string) ($row['author_photo_url'] ?? ''),
        ];
    }

    private function buildDisplayName(string $prenom, string $nom, string $email): string
    {
        $fullName = trim($prenom.' '.$nom);

        return $fullName !== '' ? $fullName : ($email !== '' ? $email : 'Voyageur');
    }

    private function fetchScalar(string $sql, int $fallback): int
    {
        $value = $this->connectionFactory->getConnection()->query($sql)?->fetchColumn();

        return $value !== false ? (int) $value : $fallback;
    }

    private function normalizeIdList(array $ids): array
    {
        return array_values(
            array_filter(
                array_map('intval', $ids),
                static fn (int $id): bool => $id > 0
            )
        );
    }

    private function normalizeReactionCode(string $reactionCode): ?string
    {
        $reactionCode = strtoupper(trim($reactionCode));

        return in_array($reactionCode, self::REACTION_CODES, true) ? $reactionCode : null;
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }
}
