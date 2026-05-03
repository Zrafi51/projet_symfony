<?php

namespace App\Database;

/**
 * Returns a singleton PDO connection built from the DATABASE_URL environment
 * variable. Replaces Doctrine DBAL/ORM in this project.
 */
class PdoConnectionFactory
{
    private ?\PDO $pdo = null;

    public function __construct(
        private readonly ?string $databaseUrl = null
    ) {
    }

    public function getConnection(): \PDO
    {
        if ($this->pdo instanceof \PDO) {
            return $this->pdo;
        }

        $url = $this->databaseUrl ?? $_ENV['DATABASE_URL'] ?? $_SERVER['DATABASE_URL'] ?? getenv('DATABASE_URL');
        if (!$url) {
            throw new \RuntimeException('DATABASE_URL is not configured.');
        }

        $parts = parse_url($url);
        if ($parts === false || empty($parts['host']) || empty($parts['path'])) {
            throw new \RuntimeException('Invalid DATABASE_URL: ' . $url);
        }

        $scheme = $parts['scheme'] ?? 'mysql';
        $host   = $parts['host'];
        $port   = $parts['port'] ?? 3306;
        $db     = ltrim($parts['path'], '/');
        $user   = $parts['user'] ?? 'root';
        $pass   = $parts['pass'] ?? '';

        $charset = 'utf8mb4';
        if (!empty($parts['query'])) {
            parse_str($parts['query'], $q);
            if (!empty($q['charset'])) {
                $charset = $q['charset'];
            }
        }

        $driver = $scheme === 'mysql' ? 'mysql' : $scheme;
        $dsn = sprintf('%s:host=%s;port=%d;dbname=%s;charset=%s', $driver, $host, $port, $db, $charset);

        $this->pdo = new \PDO($dsn, $user, $pass, [
            \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES   => false,
        ]);

        return $this->pdo;
    }
}
