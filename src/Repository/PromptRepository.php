<?php

namespace App\Repository;

use App\Database\PdoConnectionFactory;
use PDO;

final class PromptRepository
{
    private bool $schemaEnsured = false;

    public function __construct(private readonly PdoConnectionFactory $connectionFactory)
    {
    }

    public function getDashboardData(string $language = 'all', ?string $selectedPromptId = null): array
    {
        $prompts = $this->findAll($language);
        $selected = $selectedPromptId !== null ? $this->find($selectedPromptId) : ($prompts[0] ?? null);
        $versions = $selected !== null ? $this->findVersions((string) $selected['id']) : [];

        return [
            'language' => $language,
            'prompts' => $prompts,
            'selectedPrompt' => $selected,
            'versions' => $versions,
            'stats' => [
                'prompts' => count($prompts),
                'versions' => array_sum(array_map(static fn (array $prompt): int => (int) ($prompt['version_count'] ?? 0), $prompts)),
                'active' => count(array_filter($prompts, static fn (array $prompt): bool => trim((string) ($prompt['active_version_id'] ?? '')) !== '')),
            ],
        ];
    }

    public function findAll(string $language = 'all'): array
    {
        $this->ensureSchema();
        $sql = 'SELECT p.*,
                    pv.version AS active_version_number,
                    pv.content AS active_version_content,
                    (SELECT COUNT(*) FROM prompt_versions v WHERE v.prompt_id = p.id) AS version_count
                FROM prompts p
                LEFT JOIN prompt_versions pv ON pv.id = p.active_version_id';
        $params = [];
        if (in_array($language, ['fr', 'en'], true)) {
            $sql .= ' WHERE p.language = :language';
            $params['language'] = $language;
        }
        $sql .= ' ORDER BY p.language ASC, p.prompt_key ASC, p.created_at DESC';

        $statement = $this->connectionFactory->getConnection()->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll();
    }

    public function find(string $promptId): ?array
    {
        $this->ensureSchema();
        $statement = $this->connectionFactory->getConnection()->prepare(
            'SELECT p.*,
                    pv.version AS active_version_number,
                    pv.content AS active_version_content,
                    (SELECT COUNT(*) FROM prompt_versions v WHERE v.prompt_id = p.id) AS version_count
             FROM prompts p
             LEFT JOIN prompt_versions pv ON pv.id = p.active_version_id
             WHERE p.id = :id'
        );
        $statement->execute(['id' => $promptId]);
        $prompt = $statement->fetch();

        return is_array($prompt) ? $prompt : null;
    }

    public function createOrUpdatePrompt(array $payload): string
    {
        $this->ensureSchema();
        $id = trim((string) ($payload['id'] ?? ''));
        $language = $this->normalizeLanguage((string) ($payload['language'] ?? 'fr'));
        $promptKey = $this->normalizeRequired((string) ($payload['prompt_key'] ?? ''));
        $description = trim((string) ($payload['description'] ?? ''));

        if ($id === '') {
            $id = $this->generateUuid();
            $statement = $this->connectionFactory->getConnection()->prepare(
                'INSERT INTO prompts (id, prompt_key, description, active_version_id, language, created_at)
                 VALUES (:id, :prompt_key, :description, NULL, :language, NOW())'
            );
            $statement->execute([
                'id' => $id,
                'prompt_key' => $promptKey,
                'description' => $description !== '' ? $description : null,
                'language' => $language,
            ]);

            return $id;
        }

        $statement = $this->connectionFactory->getConnection()->prepare(
            'UPDATE prompts SET prompt_key = :prompt_key, description = :description, language = :language WHERE id = :id'
        );
        $statement->execute([
            'id' => $id,
            'prompt_key' => $promptKey,
            'description' => $description !== '' ? $description : null,
            'language' => $language,
        ]);

        return $id;
    }

    public function deletePrompt(string $promptId): bool
    {
        $this->ensureSchema();
        $connection = $this->connectionFactory->getConnection();
        $connection->beginTransaction();
        try {
            $connection->prepare('UPDATE prompts SET active_version_id = NULL WHERE id = :id')->execute(['id' => $promptId]);
            $connection->prepare('DELETE FROM prompt_versions WHERE prompt_id = :id')->execute(['id' => $promptId]);
            $statement = $connection->prepare('DELETE FROM prompts WHERE id = :id');
            $statement->execute(['id' => $promptId]);
            $connection->commit();

            return $statement->rowCount() > 0;
        } catch (\Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }
    }

    public function findVersions(string $promptId): array
    {
        $this->ensureSchema();
        $statement = $this->connectionFactory->getConnection()->prepare(
            'SELECT pv.*,
                    CASE WHEN p.active_version_id = pv.id THEN 1 ELSE 0 END AS is_active
             FROM prompt_versions pv
             JOIN prompts p ON p.id = pv.prompt_id
             WHERE pv.prompt_id = :prompt_id
             ORDER BY pv.version DESC, pv.created_at DESC'
        );
        $statement->execute(['prompt_id' => $promptId]);

        return $statement->fetchAll();
    }

    public function createOrUpdateVersion(array $payload): string
    {
        $this->ensureSchema();
        $id = trim((string) ($payload['id'] ?? ''));
        $promptId = $this->normalizeRequired((string) ($payload['prompt_id'] ?? ''));
        $content = $this->normalizeRequired((string) ($payload['content'] ?? ''));
        $createdBy = trim((string) ($payload['created_by'] ?? ''));
        $note = trim((string) ($payload['note'] ?? ''));
        $setActive = (bool) ($payload['set_active'] ?? false);
        $connection = $this->connectionFactory->getConnection();
        $connection->beginTransaction();

        try {
            if ($id === '') {
                $id = $this->generateUuid();
                $statement = $connection->prepare(
                    'INSERT INTO prompt_versions (id, prompt_id, version, content, created_by, note, created_at)
                     VALUES (:id, :prompt_id, :version, :content, :created_by, :note, NOW())'
                );
                $statement->execute([
                    'id' => $id,
                    'prompt_id' => $promptId,
                    'version' => $this->nextVersion($promptId),
                    'content' => $content,
                    'created_by' => $createdBy !== '' ? $createdBy : null,
                    'note' => $note !== '' ? $note : null,
                ]);
            } else {
                $statement = $connection->prepare(
                    'UPDATE prompt_versions SET content = :content, created_by = :created_by, note = :note WHERE id = :id AND prompt_id = :prompt_id'
                );
                $statement->execute([
                    'id' => $id,
                    'prompt_id' => $promptId,
                    'content' => $content,
                    'created_by' => $createdBy !== '' ? $createdBy : null,
                    'note' => $note !== '' ? $note : null,
                ]);
            }

            if ($setActive) {
                $this->setActiveVersion($promptId, $id);
            }
            $connection->commit();

            return $id;
        } catch (\Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }
    }

    public function setActiveVersion(string $promptId, ?string $versionId): void
    {
        $this->ensureSchema();
        $statement = $this->connectionFactory->getConnection()->prepare(
            'UPDATE prompts SET active_version_id = :version_id WHERE id = :prompt_id'
        );
        $statement->execute(['version_id' => $versionId, 'prompt_id' => $promptId]);
    }

    public function deleteVersion(string $versionId): ?string
    {
        $this->ensureSchema();
        $connection = $this->connectionFactory->getConnection();
        $statement = $connection->prepare('SELECT prompt_id FROM prompt_versions WHERE id = :id');
        $statement->execute(['id' => $versionId]);
        $promptId = (string) ($statement->fetchColumn() ?: '');
        if ($promptId === '') {
            return null;
        }

        $connection->beginTransaction();
        try {
            $connection->prepare('UPDATE prompts SET active_version_id = NULL WHERE active_version_id = :id')->execute(['id' => $versionId]);
            $connection->prepare('DELETE FROM prompt_versions WHERE id = :id')->execute(['id' => $versionId]);
            $connection->commit();

            return $promptId;
        } catch (\Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }
    }

    public function getActivePromptPayload(): array
    {
        $this->ensureSchema();
        $statement = $this->connectionFactory->getConnection()->query(
            'SELECT p.prompt_key, p.language, pv.content, pv.version
             FROM prompts p
             JOIN prompt_versions pv ON pv.id = p.active_version_id
             ORDER BY p.language ASC, p.prompt_key ASC'
        );

        return [
            'prompts' => array_map(static fn (array $row): array => [
                'prompt_key' => (string) $row['prompt_key'],
                'language' => (string) ($row['language'] ?? 'fr'),
                'content' => (string) $row['content'],
                'version' => (int) ($row['version'] ?? 1),
            ], $statement->fetchAll()),
        ];
    }

    private function ensureSchema(): void
    {
        if ($this->schemaEnsured) {
            return;
        }

        $connection = $this->connectionFactory->getConnection();
        $connection->exec(
            'CREATE TABLE IF NOT EXISTS prompts (
                id CHAR(36) NOT NULL PRIMARY KEY,
                prompt_key VARCHAR(128) NOT NULL,
                description TEXT DEFAULT NULL,
                active_version_id CHAR(36) DEFAULT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                language VARCHAR(10) NOT NULL DEFAULT "fr",
                UNIQUE KEY uq_prompt_key_language_runtime (prompt_key, language)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
        if (!$this->columnExists('prompts', 'language')) {
            $connection->exec('ALTER TABLE prompts ADD COLUMN language VARCHAR(10) NOT NULL DEFAULT "fr"');
        }
        $connection->exec(
            'CREATE TABLE IF NOT EXISTS prompt_versions (
                id CHAR(36) NOT NULL PRIMARY KEY,
                prompt_id CHAR(36) NOT NULL,
                version INT NOT NULL,
                content LONGTEXT NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                created_by VARCHAR(255) DEFAULT NULL,
                note TEXT DEFAULT NULL,
                INDEX idx_prompt_versions_prompt_runtime (prompt_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
        $this->schemaEnsured = true;
    }

    private function columnExists(string $table, string $column): bool
    {
        $statement = $this->connectionFactory->getConnection()->prepare(
            'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column'
        );
        $statement->execute(['table' => $table, 'column' => $column]);

        return (int) $statement->fetchColumn() > 0;
    }

    private function nextVersion(string $promptId): int
    {
        $statement = $this->connectionFactory->getConnection()->prepare(
            'SELECT COALESCE(MAX(version), 0) + 1 FROM prompt_versions WHERE prompt_id = :prompt_id'
        );
        $statement->execute(['prompt_id' => $promptId]);

        return max(1, (int) $statement->fetchColumn());
    }

    private function normalizeRequired(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            throw new \InvalidArgumentException('Champ obligatoire manquant.');
        }

        return $value;
    }

    private function normalizeLanguage(string $language): string
    {
        return str_starts_with(strtolower(trim($language)), 'en') ? 'en' : 'fr';
    }

    private function generateUuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
