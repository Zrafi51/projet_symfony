<?php

namespace App\Database;

use PDO;
use PDOException;
use RuntimeException;

final class PdoConnectionFactory
{
    private ?PDO $connection = null;

    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly string $database,
        private readonly string $user,
        private readonly string $password,
    ) {
    }

    public function getConnection(): PDO
    {
        if ($this->connection instanceof PDO) {
            return $this->connection;
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $this->host,
            $this->port,
            $this->database,
        );

        try {
            $this->connection = new PDO($dsn, $this->user, $this->password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } catch (PDOException $exception) {
            throw new RuntimeException(
                'Connexion MySQL impossible. Demarre MySQL/XAMPP puis recharge la page. Details: '.$exception->getMessage(),
                previous: $exception,
            );
        }

        return $this->connection;
    }
}
