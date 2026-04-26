<?php

namespace App\Repository;

use App\Database\PdoConnectionFactory;

final class AdminProfilePreferenceRepository
{
    private const TABLE_NAME = 'admin_profile_preferences';

    private bool $schemaEnsured = false;

    public function __construct(private readonly PdoConnectionFactory $connectionFactory)
    {
    }

    public function findByEmail(string $email): array
    {
        $this->ensureSchema();
        if (trim($email) === '') {
            return $this->getDefaultPreferences();
        }

        $statement = $this->connectionFactory->getConnection()->prepare(
            'SELECT job_title, company, bio
             FROM '.self::TABLE_NAME.'
             WHERE LOWER(user_email) = LOWER(:email)
             LIMIT 1'
        );
        $statement->execute([
            'email' => strtolower(trim($email)),
        ]);
        $row = $statement->fetch();

        if (!$row) {
            return $this->getDefaultPreferences();
        }

        return [
            'job_title' => trim((string) ($row['job_title'] ?? 'Super Admin')),
            'company' => trim((string) ($row['company'] ?? 'EasyTravel')),
            'bio' => trim((string) ($row['bio'] ?? '')),
        ];
    }

    public function save(string $email, array $payload): bool
    {
        $this->ensureSchema();
        if (trim($email) === '') {
            return false;
        }

        $statement = $this->connectionFactory->getConnection()->prepare(
            'INSERT INTO '.self::TABLE_NAME.' (
                user_email, job_title, company, bio
            ) VALUES (
                :user_email, :job_title, :company, :bio
            ) ON DUPLICATE KEY UPDATE
                job_title = VALUES(job_title),
                company = VALUES(company),
                bio = VALUES(bio)'
        );

        return $statement->execute([
            'user_email' => strtolower(trim($email)),
            'job_title' => trim((string) ($payload['job_title'] ?? 'Super Admin')),
            'company' => trim((string) ($payload['company'] ?? 'EasyTravel')),
            'bio' => trim((string) ($payload['bio'] ?? '')),
        ]);
    }

    public function migrateEmail(string $previousEmail, string $newEmail): void
    {
        $this->ensureSchema();
        if (trim($previousEmail) === '' || trim($newEmail) === '' || strcasecmp($previousEmail, $newEmail) === 0) {
            return;
        }

        $connection = $this->connectionFactory->getConnection();
        $connection->prepare(
            'INSERT INTO '.self::TABLE_NAME.' (
                user_email, job_title, company, bio
            )
            SELECT
                :new_email,
                job_title,
                company,
                bio
            FROM '.self::TABLE_NAME.'
            WHERE LOWER(user_email) = LOWER(:previous_email)
            LIMIT 1
            ON DUPLICATE KEY UPDATE
                job_title = VALUES(job_title),
                company = VALUES(company),
                bio = VALUES(bio)'
        )->execute([
            'new_email' => strtolower(trim($newEmail)),
            'previous_email' => strtolower(trim($previousEmail)),
        ]);

        $connection->prepare(
            'DELETE FROM '.self::TABLE_NAME.'
             WHERE LOWER(user_email) = LOWER(:previous_email)'
        )->execute([
            'previous_email' => strtolower(trim($previousEmail)),
        ]);
    }

    private function ensureSchema(): void
    {
        if ($this->schemaEnsured) {
            return;
        }

        $this->connectionFactory->getConnection()->exec(
            "CREATE TABLE IF NOT EXISTS ".self::TABLE_NAME." (
                id INT NOT NULL AUTO_INCREMENT,
                user_email VARCHAR(100) NOT NULL,
                job_title VARCHAR(100) NOT NULL DEFAULT 'Super Admin',
                company VARCHAR(100) NOT NULL DEFAULT 'EasyTravel',
                bio TEXT DEFAULT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uk_admin_profile_preferences_email (user_email)
            )"
        );

        $this->schemaEnsured = true;
    }

    private function getDefaultPreferences(): array
    {
        return [
            'job_title' => 'Super Admin',
            'company' => 'EasyTravel',
            'bio' => 'Admin principal de la console EasyTravel. Gestion des operations, du support et des contenus premium.',
        ];
    }
}
