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
             ORDER BY s.updated_at DESC, s.id DESC
             LIMIT :limit'
        );
        $statement->bindValue('user_id', $userId);
        $statement->bindValue('limit', max(1, $limit), PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }

    public function createSession(string $sessionId, string $userId, string $title, string $language = 'fr'): void
    {
        $this->ensureSchema();
        $statement = $this->connectionFactory->getConnection()->prepare(
            'INSERT INTO sessions (id, user_id, title, form_data, agent_state, created_at, updated_at)
             VALUES (:id, :user_id, :title, :form_data, :agent_state, NOW(), NOW())'
        );
        $statement->execute([
            'id' => $sessionId,
            'user_id' => $userId,
            'title' => trim($title) !== '' ? trim($title) : 'Nouvelle discussion',
            'form_data' => json_encode(['language' => $language], JSON_UNESCAPED_UNICODE),
            'agent_state' => '{}',
        ]);
    }

    public function addMessage(string $sessionId, string $userId, string $role, string $content): void
    {
        $this->ensureSchema();
        if (!$this->sessionBelongsToUser($sessionId, $userId)) {
            return;
        }

        $connection = $this->connectionFactory->getConnection();
        $message = $connection->prepare(
            'INSERT INTO messages (id, session_id, role, content, content_json, model, latency_ms, token_count, created_at)
             VALUES (:id, :session_id, :role, :content, NULL, NULL, NULL, NULL, NOW())'
        );
        $message->execute([
            'id' => $this->generateId(),
            'session_id' => $sessionId,
            'role' => in_array($role, ['assistant', 'system'], true) ? $role : 'user',
            'content' => $content,
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

    public function deleteSession(string $sessionId, string $userId): bool
    {
        $this->ensureSchema();
        $connection = $this->connectionFactory->getConnection();
        $connection->prepare('DELETE FROM messages WHERE session_id = :session_id')->execute(['session_id' => $sessionId]);
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
        $connection->exec(
            'CREATE TABLE IF NOT EXISTS sessions (
                id VARCHAR(80) PRIMARY KEY,
                user_id VARCHAR(120) NOT NULL,
                title VARCHAR(180) NOT NULL,
                form_data TEXT DEFAULT NULL,
                agent_state TEXT DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                last_message_at DATETIME DEFAULT NULL
            )'
        );
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

        $this->schemaEnsured = true;
    }
}
