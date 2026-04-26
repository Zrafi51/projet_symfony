<?php

namespace App\Repository;

use App\Database\PdoConnectionFactory;

final class ActiviteRepository
{
    public function __construct(private readonly PdoConnectionFactory $connectionFactory)
    {
    }

    public function count(): int
    {
        return (int) $this->connectionFactory->getConnection()
            ->query('SELECT COUNT(*) FROM activites')
            ->fetchColumn();
    }

    public function findAll(): array
    {
        $statement = $this->connectionFactory->getConnection()->query(
            'SELECT a.*, d.nom AS destination_nom
             FROM activites a
             LEFT JOIN destinations d ON d.id = a.destination_id
             ORDER BY a.id DESC'
        );

        return $statement->fetchAll();
    }

    public function find(int $id): ?array
    {
        $statement = $this->connectionFactory->getConnection()->prepare(
            'SELECT * FROM activites WHERE id = :id'
        );
        $statement->execute(['id' => $id]);

        $activite = $statement->fetch();

        return $activite ?: null;
    }

    public function create(array $payload): void
    {
        $statement = $this->connectionFactory->getConnection()->prepare(
            'INSERT INTO activites (nom, destination_id, categorie, prix, duree_heures, description)
             VALUES (:nom, :destination_id, :categorie, :prix, :duree_heures, :description)'
        );

        $statement->execute($this->normalizePayload($payload));
    }

    public function update(int $id, array $payload): bool
    {
        $statement = $this->connectionFactory->getConnection()->prepare(
            'UPDATE activites
             SET nom = :nom, destination_id = :destination_id, categorie = :categorie, prix = :prix,
                 duree_heures = :duree_heures, description = :description
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
            'DELETE FROM activites WHERE id = :id'
        );

        $statement->execute(['id' => $id]);
    }

    private function normalizePayload(array $payload): array
    {
        return [
            'nom' => trim((string) ($payload['nom'] ?? '')),
            'destination_id' => (int) ($payload['destination_id'] ?? 0),
            'categorie' => trim((string) ($payload['categorie'] ?? '')),
            'prix' => (float) ($payload['prix'] ?? 0),
            'duree_heures' => (int) ($payload['duree_heures'] ?? 0),
            'description' => trim((string) ($payload['description'] ?? '')),
        ];
    }
}
