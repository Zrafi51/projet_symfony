<?php

namespace App\Database;

use PDO;
use PDOException;
use RuntimeException;

final class PdoConnectionFactory
{
    private ?PDO $connection = null;

    public function __construct(
        private readonly string $databaseUrl = '',
        private readonly string $host = '',
        private readonly int $port = 3306,
        private readonly string $database = '',
        private readonly string $user = '',
        private readonly string $password = '',
    ) {
    }

    public function getConnection(): PDO
    {
        if ($this->connection instanceof PDO) {
            return $this->connection;
        }

        [$dsn, $user, $password] = $this->buildConnectionConfig();

        try {
            $this->connection = new PDO($dsn, $user, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } catch (PDOException $exception) {
            throw new RuntimeException(
                'Connexion MySQL impossible. Verifie DATABASE_URL ou les variables DB_*. Details: '.$exception->getMessage(),
                previous: $exception,
            );
        }

        return $this->connection;
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    private function buildConnectionConfig(): array
    {
        $databaseUrl = trim($this->databaseUrl);

        if ($databaseUrl !== '') {
            return $this->buildConfigFromUrl($databaseUrl);
        }

        if (trim($this->host) === '' || trim($this->database) === '' || trim($this->user) === '') {
            throw new RuntimeException('Configuration MySQL incomplete. Definis DATABASE_URL ou DB_HOST, DB_NAME et DB_USER.');
        }

        return [
            sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                $this->host,
                $this->port,
                $this->database,
            ),
            $this->user,
            $this->password,
        ];
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    private function buildConfigFromUrl(string $databaseUrl): array
    {
        $parts = parse_url($databaseUrl);

        if ($parts === false) {
            throw new RuntimeException('DATABASE_URL est invalide.');
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        if (!in_array($scheme, ['mysql', 'mysql2', 'mariadb'], true)) {
            throw new RuntimeException('DATABASE_URL doit utiliser mysql:// ou mariadb://.');
        }

        $host = (string) ($parts['host'] ?? '');
        $port = (int) ($parts['port'] ?? 3306);
        $database = rawurldecode(ltrim((string) ($parts['path'] ?? ''), '/'));
        $user = rawurldecode((string) ($parts['user'] ?? ''));
        $password = rawurldecode((string) ($parts['pass'] ?? ''));

        if ($host === '' || $database === '' || $user === '') {
            throw new RuntimeException('DATABASE_URL doit contenir host, database et user.');
        }

        return [
            sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $database),
            $user,
            $password,
        ];
    }
}
