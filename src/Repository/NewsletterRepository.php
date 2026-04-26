<?php

namespace App\Repository;

use App\Database\PdoConnectionFactory;

final class NewsletterRepository
{
    private const TABLE = 'newsletter';

    private bool $tableEnsured = false;

    public function __construct(private readonly PdoConnectionFactory $connectionFactory)
    {
    }

    public function subscribe(string $email): bool
    {
        $this->ensureTable();

        $statement = $this->connectionFactory->getConnection()->prepare(
            'INSERT INTO '.self::TABLE.' (email, created_at)
             VALUES (:email, NOW())
             ON DUPLICATE KEY UPDATE created_at = NOW()'
        );

        return $statement->execute([
            'email' => strtolower(trim($email)),
        ]);
    }

    private function ensureTable(): void
    {
        if ($this->tableEnsured) {
            return;
        }

        $this->connectionFactory->getConnection()->exec(
            'CREATE TABLE IF NOT EXISTS '.self::TABLE.' (
                id INT NOT NULL AUTO_INCREMENT,
                email VARCHAR(100) NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uq_newsletter_email (email),
                KEY idx_newsletter_created_at (created_at)
            )'
        );

        $this->tableEnsured = true;
    }
}
