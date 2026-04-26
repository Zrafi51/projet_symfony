<?php

namespace App\Repository;

use App\Database\PdoConnectionFactory;

final class DestinationRepository
{
    public function __construct(private readonly PdoConnectionFactory $connectionFactory)
    {
    }

    public function count(): int
    {
        return (int) $this->connectionFactory->getConnection()
            ->query('SELECT COUNT(*) FROM destinations')
            ->fetchColumn();
    }

    public function findAll(): array
    {
        $statement = $this->connectionFactory->getConnection()
            ->query('SELECT * FROM destinations ORDER BY id DESC');

        return $statement->fetchAll();
    }

    public function findLatest(int $limit = 3): array
    {
        $statement = $this->connectionFactory->getConnection()->prepare(
            'SELECT * FROM destinations ORDER BY id DESC LIMIT :limit'
        );
        $statement->bindValue('limit', max(1, $limit), \PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }

    public function findForSelect(): array
    {
        $statement = $this->connectionFactory->getConnection()
            ->query('SELECT id, nom, pays FROM destinations ORDER BY nom ASC, pays ASC');

        return $statement->fetchAll();
    }

    public function find(int $id): ?array
    {
        $statement = $this->connectionFactory->getConnection()->prepare(
            'SELECT * FROM destinations WHERE id = :id'
        );
        $statement->execute(['id' => $id]);

        $destination = $statement->fetch();

        return $destination ?: null;
    }

    public function create(array $payload): void
    {
        $statement = $this->connectionFactory->getConnection()->prepare(
            'INSERT INTO destinations (nom, pays, continent, prix_base, description)
             VALUES (:nom, :pays, :continent, :prix_base, :description)'
        );

        $statement->execute($this->normalizePayload($payload));
    }

    public function update(int $id, array $payload): bool
    {
        $statement = $this->connectionFactory->getConnection()->prepare(
            'UPDATE destinations
             SET nom = :nom, pays = :pays, continent = :continent, prix_base = :prix_base, description = :description
             WHERE id = :id'
        );

        return $statement->execute([
            ...$this->normalizePayload($payload),
            'id' => $id,
        ]);
    }

    public function delete(int $id): void
    {
        $statement = $this->connectionFactory->getConnection()->prepare(
            'DELETE FROM destinations WHERE id = :id'
        );

        $statement->execute(['id' => $id]);
    }

    private function normalizePayload(array $payload): array
    {
        return [
            'nom' => trim((string) ($payload['nom'] ?? '')),
            'pays' => trim((string) ($payload['pays'] ?? '')),
            'continent' => trim((string) ($payload['continent'] ?? '')),
            'prix_base' => (float) ($payload['prix_base'] ?? 0),
            'description' => trim((string) ($payload['description'] ?? '')),
        ];
    }
}
