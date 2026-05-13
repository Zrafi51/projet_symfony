<?php

namespace App\Repository;

use App\Database\PdoConnectionFactory;
use PDO;

final class ChatRepository
{
    private bool $schemaEnsured = false;

    public function __construct(private readonly PdoConnectionFactory $connectionFactory)
    {
    }

    public function findRecentSessions(string $userId, int $limit = 60): array
    {
        $this->ensureSchema();
        $statement = $this->connectionFactory->getConnection()->prepare(
            'SELECT s.*,
                (SELECT m.content FROM messages m WHERE m.session_id = s.id ORDER BY m.created_at DESC, m.id DESC LIMIT 1) AS last_message
             FROM sessions s
             WHERE s.user_id = :user_id
             ORDER BY s.is_favorite DESC, COALESCE(s.favorited_at, s.updated_at) DESC, s.updated_at DESC, s.id DESC
             LIMIT :limit'
        );
        $statement->bindValue('user_id', $userId);
        $statement->bindValue('limit', max(1, $limit), PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }

    public function createSession(string $sessionId, string $userId, string $title, string $language = 'fr', ?array $formData = null): void
    {
        $this->ensureSchema();
        $formData = is_array($formData) && $formData !== [] ? $formData : ['language' => $language];
        $formData['language'] = $formData['language'] ?? $language;
        $formDataJson = json_encode($formData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($formDataJson === false) {
            $formDataJson = json_encode(['language' => $language], JSON_UNESCAPED_UNICODE);
        }

        $statement = $this->connectionFactory->getConnection()->prepare(
            'INSERT INTO sessions (id, user_id, title, form_data, agent_state, created_at, updated_at)
             VALUES (:id, :user_id, :title, :form_data, :agent_state, NOW(), NOW())'
        );
        $statement->execute([
            'id' => $sessionId,
            'user_id' => $userId,
            'title' => trim($title) !== '' ? trim($title) : 'Nouvelle discussion',
            'form_data' => $formDataJson,
            'agent_state' => '{}',
        ]);
    }

    public function findSession(string $sessionId, string $userId): ?array
    {
        $this->ensureSchema();
        $statement = $this->connectionFactory->getConnection()->prepare(
            'SELECT * FROM sessions WHERE id = :id AND user_id = :user_id LIMIT 1'
        );
        $statement->execute(['id' => $sessionId, 'user_id' => $userId]);
        $session = $statement->fetch();

        return is_array($session) ? $session : null;
    }

    public function updateAgentState(string $sessionId, string $userId, array $state): void
    {
        $this->ensureSchema();
        $json = json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            $json = '{}';
        }

        $statement = $this->connectionFactory->getConnection()->prepare(
            'UPDATE sessions SET agent_state = :agent_state, updated_at = NOW() WHERE id = :id AND user_id = :user_id'
        );
        $statement->execute([
            'agent_state' => $json,
            'id' => $sessionId,
            'user_id' => $userId,
        ]);
    }

    public function addMessage(string $sessionId, string $userId, string $role, string $content, ?array $contentJson = null): void
    {
        $this->ensureSchema();
        if (!$this->sessionBelongsToUser($sessionId, $userId)) {
            return;
        }

        $metadata = null;
        if ($contentJson !== null) {
            $metadata = json_encode($contentJson, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($metadata === false) {
                $metadata = null;
            }
        }

        $connection = $this->connectionFactory->getConnection();
        $message = $connection->prepare(
            'INSERT INTO messages (id, session_id, role, content, content_json, model, latency_ms, token_count, created_at)
             VALUES (:id, :session_id, :role, :content, :content_json, NULL, NULL, NULL, NOW())'
        );
        $message->execute([
            'id' => $this->generateId(),
            'session_id' => $sessionId,
            'role' => in_array($role, ['assistant', 'system'], true) ? $role : 'user',
            'content' => $content,
            'content_json' => $metadata,
        ]);

        $touch = $connection->prepare('UPDATE sessions SET updated_at = NOW(), last_message_at = NOW() WHERE id = :id');
        $touch->execute(['id' => $sessionId]);
    }

    public function findMessages(string $sessionId, string $userId): array
    {
        $this->ensureSchema();
        if (!$this->sessionBelongsToUser($sessionId, $userId)) {
            return [];
        }

        $statement = $this->connectionFactory->getConnection()->prepare(
            'SELECT * FROM messages WHERE session_id = :session_id ORDER BY created_at ASC, id ASC'
        );
        $statement->execute(['session_id' => $sessionId]);

        return $statement->fetchAll();
    }

    public function findFavoriteSessions(string $userId, int $limit = 60): array
    {
        $this->ensureSchema();
        $statement = $this->connectionFactory->getConnection()->prepare(
            'SELECT s.*,
                (SELECT m.content FROM messages m WHERE m.session_id = s.id ORDER BY m.created_at DESC, m.id DESC LIMIT 1) AS last_message
             FROM sessions s
             WHERE s.user_id = :user_id AND s.is_favorite = 1
             ORDER BY COALESCE(s.favorited_at, s.updated_at) DESC, s.updated_at DESC, s.id DESC
             LIMIT :limit'
        );
        $statement->bindValue('user_id', $userId);
        $statement->bindValue('limit', max(1, $limit), PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }

    public function saveCardSnapshot(string $sessionId, string $userId, array $card): void
    {
        $this->ensureSchema();
        if (!$this->sessionBelongsToUser($sessionId, $userId)) {
            return;
        }

        $cardId = trim((string) ($card['id'] ?? $card['card_current_id'] ?? ''));
        if ($cardId === '') {
            $cardId = $this->generateId();
        }

        $cardPayload = $card['card'] ?? $card;
        if (!is_array($cardPayload)) {
            $cardPayload = [];
        }
        $payload = json_encode($cardPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($payload === false) {
            $payload = '{}';
        }

        $connection = $this->connectionFactory->getConnection();
        $statement = $connection->prepare(
            'INSERT INTO chat_cards (id, session_id, user_id, card_json, status, created_at, updated_at)
             VALUES (:id, :session_id, :user_id, :card_json, :status, NOW(), NOW())
             ON DUPLICATE KEY UPDATE card_json = VALUES(card_json), status = VALUES(status), updated_at = NOW()'
        );
        $statement->execute([
            'id' => $cardId,
            'session_id' => $sessionId,
            'user_id' => $userId,
            'card_json' => $payload,
            'status' => trim((string) ($card['card_status'] ?? $card['status'] ?? 'generated')) ?: 'generated',
        ]);
    }

    public function findCards(string $sessionId, string $userId): array
    {
        $this->ensureSchema();
        if (!$this->sessionBelongsToUser($sessionId, $userId)) {
            return [];
        }

        $statement = $this->connectionFactory->getConnection()->prepare(
            'SELECT * FROM chat_cards WHERE session_id = :session_id AND user_id = :user_id ORDER BY updated_at DESC, created_at DESC'
        );
        $statement->execute(['session_id' => $sessionId, 'user_id' => $userId]);

        return array_map(function (array $row): array {
            $decoded = json_decode((string) ($row['card_json'] ?? '{}'), true);
            return [
                'id' => (string) $row['id'],
                'status' => (string) ($row['status'] ?? 'generated'),
                'createdAt' => $this->formatStorageTime((string) ($row['created_at'] ?? '')),
                'updatedAt' => $this->formatStorageTime((string) ($row['updated_at'] ?? '')),
                'card' => is_array($decoded) ? $decoded : [],
            ];
        }, $statement->fetchAll());
    }

    public function deleteCard(string $sessionId, string $userId, string $cardId): bool
    {
        $this->ensureSchema();
        $statement = $this->connectionFactory->getConnection()->prepare(
            'DELETE FROM chat_cards WHERE id = :id AND session_id = :session_id AND user_id = :user_id'
        );

        return $statement->execute(['id' => $cardId, 'session_id' => $sessionId, 'user_id' => $userId]);
    }

    public function renameSession(string $sessionId, string $userId, string $title): bool
    {
        $this->ensureSchema();
        $statement = $this->connectionFactory->getConnection()->prepare(
            'UPDATE sessions SET title = :title, updated_at = NOW() WHERE id = :id AND user_id = :user_id'
        );

        return $statement->execute([
            'title' => trim($title) !== '' ? trim($title) : 'Discussion',
            'id' => $sessionId,
            'user_id' => $userId,
        ]);
    }

    public function setFavorite(string $sessionId, string $userId, bool $favorite): bool
    {
        $this->ensureSchema();
        $statement = $this->connectionFactory->getConnection()->prepare(
            'UPDATE sessions
             SET is_favorite = :is_favorite,
                 favorited_at = '.($favorite ? 'NOW()' : 'NULL').',
                 updated_at = NOW()
             WHERE id = :id AND user_id = :user_id'
        );

        return $statement->execute([
            'is_favorite' => $favorite ? 1 : 0,
            'id' => $sessionId,
            'user_id' => $userId,
        ]);
    }

    public function deleteSession(string $sessionId, string $userId): bool
    {
        $this->ensureSchema();
        $connection = $this->connectionFactory->getConnection();
        $connection->prepare('DELETE FROM messages WHERE session_id = :session_id')->execute(['session_id' => $sessionId]);
        $connection->prepare('DELETE FROM chat_cards WHERE session_id = :session_id AND user_id = :user_id')->execute([
            'session_id' => $sessionId,
            'user_id' => $userId,
        ]);
        $statement = $connection->prepare('DELETE FROM sessions WHERE id = :id AND user_id = :user_id');

        return $statement->execute(['id' => $sessionId, 'user_id' => $userId]);
    }

    public function sessionBelongsToUser(string $sessionId, string $userId): bool
    {
        $this->ensureSchema();
        $statement = $this->connectionFactory->getConnection()->prepare(
            'SELECT id FROM sessions WHERE id = :id AND user_id = :user_id LIMIT 1'
        );
        $statement->execute(['id' => $sessionId, 'user_id' => $userId]);

        return (bool) $statement->fetch();
    }

    public function generateId(): string
    {
        return bin2hex(random_bytes(16));
    }

    private function ensureSchema(): void
    {
        if ($this->schemaEnsured) {
            return;
        }

        $connection = $this->connectionFactory->getConnection();
        $this->normalizeImportedSchema($connection);
        $connection->exec(
            'CREATE TABLE IF NOT EXISTS sessions (
                id VARCHAR(80) PRIMARY KEY,
                user_id VARCHAR(120) NOT NULL,
                title VARCHAR(180) NOT NULL,
                form_data TEXT DEFAULT NULL,
                agent_state TEXT DEFAULT NULL,
                is_favorite TINYINT(1) NOT NULL DEFAULT 0,
                favorited_at DATETIME DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                last_message_at DATETIME DEFAULT NULL
            )'
        );
        $this->ensureColumn($connection, 'sessions', 'is_favorite', 'ALTER TABLE sessions ADD COLUMN is_favorite TINYINT(1) NOT NULL DEFAULT 0 AFTER agent_state');
        $this->ensureColumn($connection, 'sessions', 'favorited_at', 'ALTER TABLE sessions ADD COLUMN favorited_at DATETIME DEFAULT NULL AFTER is_favorite');
        $connection->exec(
            'CREATE TABLE IF NOT EXISTS messages (
                id VARCHAR(80) PRIMARY KEY,
                session_id VARCHAR(80) NOT NULL,
                role VARCHAR(40) NOT NULL,
                content TEXT NOT NULL,
                content_json TEXT DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                model VARCHAR(120) DEFAULT NULL,
                latency_ms INT DEFAULT NULL,
                token_count INT DEFAULT NULL
            )'
        );
        $connection->exec(
            'CREATE TABLE IF NOT EXISTS chat_cards (
                id VARCHAR(80) PRIMARY KEY,
                session_id VARCHAR(80) NOT NULL,
                user_id VARCHAR(120) NOT NULL,
                card_json TEXT NOT NULL,
                status VARCHAR(40) NOT NULL DEFAULT "generated",
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_chat_cards_session_user (session_id, user_id)
            )'
        );

        $this->schemaEnsured = true;
    }

    private function normalizeImportedSchema(PDO $connection): void
    {
        if (!$this->tableExists($connection, 'sessions')) {
            return;
        }

        $this->dropForeignKeyIfExists($connection, 'messages', 'fk_messages_session');
        $this->modifyColumnIfExists($connection, 'sessions', 'id', 'ALTER TABLE sessions MODIFY id VARCHAR(80) NOT NULL');
        $this->modifyColumnIfExists($connection, 'sessions', 'user_id', 'ALTER TABLE sessions MODIFY user_id VARCHAR(120) NOT NULL');
        $this->modifyColumnIfExists($connection, 'messages', 'id', 'ALTER TABLE messages MODIFY id VARCHAR(80) NOT NULL');
        $this->modifyColumnIfExists($connection, 'messages', 'session_id', 'ALTER TABLE messages MODIFY session_id VARCHAR(80) NOT NULL');
        $this->modifyColumnIfExists($connection, 'chat_cards', 'id', 'ALTER TABLE chat_cards MODIFY id VARCHAR(80) NOT NULL');
        $this->modifyColumnIfExists($connection, 'chat_cards', 'session_id', 'ALTER TABLE chat_cards MODIFY session_id VARCHAR(80) NOT NULL');
        $this->modifyColumnIfExists($connection, 'chat_cards', 'user_id', 'ALTER TABLE chat_cards MODIFY user_id VARCHAR(120) NOT NULL');
    }

    private function tableExists(PDO $connection, string $table): bool
    {
        $statement = $connection->prepare(
            'SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table'
        );
        $statement->execute(['table' => $table]);

        return (int) $statement->fetchColumn() > 0;
    }

    private function modifyColumnIfExists(PDO $connection, string $table, string $column, string $sql): void
    {
        $statement = $connection->prepare(
            'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column'
        );
        $statement->execute(['table' => $table, 'column' => $column]);
        if ((int) $statement->fetchColumn() > 0) {
            $connection->exec($sql);
        }
    }

    private function dropForeignKeyIfExists(PDO $connection, string $table, string $constraint): void
    {
        $statement = $connection->prepare(
            'SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :table
               AND CONSTRAINT_NAME = :constraint
               AND CONSTRAINT_TYPE = "FOREIGN KEY"'
        );
        $statement->execute(['table' => $table, 'constraint' => $constraint]);
        if ((int) $statement->fetchColumn() > 0) {
            $connection->exec(sprintf('ALTER TABLE %s DROP FOREIGN KEY %s', $table, $constraint));
        }
    }

    private function ensureColumn(PDO $connection, string $table, string $column, string $sql): void
    {
        $statement = $connection->prepare(
            'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column'
        );
        $statement->execute(['table' => $table, 'column' => $column]);
        if ((int) $statement->fetchColumn() === 0) {
            $connection->exec($sql);
        }
    }

    private function formatStorageTime(string $value): string
    {
        $timestamp = strtotime($value);

        return $timestamp !== false ? date('d/m H:i', $timestamp) : date('d/m H:i');
    }
}
