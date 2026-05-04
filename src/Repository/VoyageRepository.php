<?php

namespace App\Repository;

use App\Database\PdoConnectionFactory;
use PDO;

final class VoyageRepository
{
    private const TABLE_NAME = 'voyage';

    private bool $schemaEnsured = false;

    public function __construct(private readonly PdoConnectionFactory $connectionFactory)
    {
    }

    public function findByClientEmail(string $email): array
    {
        $this->ensureSchema();
        $normalizedEmail = mb_strtolower(trim($email));
        if ($normalizedEmail === '') {
            return [];
        }

        $statement = $this->connectionFactory->getConnection()->prepare(
            'SELECT * FROM '.self::TABLE_NAME.'
             WHERE LOWER(client_email) = :email
             ORDER BY dateDepart DESC, created_at DESC, idVoyage DESC'
        );
        $statement->execute(['email' => $normalizedEmail]);

        return $statement->fetchAll();
    }

    public function findByClientName(string $name): array
    {
        $this->ensureSchema();
        $normalizedName = mb_strtolower(trim($name));
        if ($normalizedName === '') {
            return [];
        }

        $statement = $this->connectionFactory->getConnection()->prepare(
            'SELECT * FROM '.self::TABLE_NAME.'
             WHERE LOWER(client_nom) LIKE :name
             ORDER BY dateDepart DESC, created_at DESC, idVoyage DESC'
        );
        $statement->execute(['name' => '%'.$normalizedName.'%']);

        return $statement->fetchAll();
    }

    public function create(array $payload): int
    {
        $this->ensureSchema();
        $statement = $this->connectionFactory->getConnection()->prepare(
            'INSERT INTO '.self::TABLE_NAME.' (
                client_nom, client_email, destination, pays, dateDepart, dateRetour,
                prix, moyenTransport, hotel, nbPlaces, disponible, type_voyage,
                package_id, payment_reference
            ) VALUES (
                :client_nom, :client_email, :destination, :pays, :date_depart, :date_retour,
                :prix, :moyen_transport, :hotel, :nb_places, :disponible, :type_voyage,
                :package_id, :payment_reference
            )'
        );
        $statement->execute($this->normalizePayload($payload));

        return (int) $this->connectionFactory->getConnection()->lastInsertId();
    }

    private function normalizePayload(array $payload): array
    {
        return [
            'client_nom' => trim((string) ($payload['client_nom'] ?? '')),
            'client_email' => mb_strtolower(trim((string) ($payload['client_email'] ?? ''))),
            'destination' => trim((string) ($payload['destination'] ?? '')),
            'pays' => trim((string) ($payload['pays'] ?? '')),
            'date_depart' => trim((string) ($payload['date_depart'] ?? date('Y-m-d'))),
            'date_retour' => trim((string) ($payload['date_retour'] ?? date('Y-m-d', strtotime('+7 days')))),
            'prix' => round((float) ($payload['prix'] ?? 0), 2),
            'moyen_transport' => trim((string) ($payload['moyen_transport'] ?? 'Avion')) ?: 'Avion',
            'hotel' => trim((string) ($payload['hotel'] ?? 'Hotel Standard')) ?: 'Hotel Standard',
            'nb_places' => max(1, (int) ($payload['nb_places'] ?? 1)),
            'disponible' => !empty($payload['disponible']) ? 1 : 0,
            'type_voyage' => trim((string) ($payload['type_voyage'] ?? 'Aventure')) ?: 'Aventure',
            'package_id' => max(0, (int) ($payload['package_id'] ?? 0)),
            'payment_reference' => trim((string) ($payload['payment_reference'] ?? '')),
        ];
    }

    private function ensureSchema(): void
    {
        if ($this->schemaEnsured) {
            return;
        }

        $connection = $this->connectionFactory->getConnection();
        $connection->exec(
            'CREATE TABLE IF NOT EXISTS '.self::TABLE_NAME.' (
                idVoyage INT PRIMARY KEY AUTO_INCREMENT,
                client_nom VARCHAR(150) NOT NULL DEFAULT "",
                client_email VARCHAR(190) DEFAULT NULL,
                destination VARCHAR(160) NOT NULL,
                pays VARCHAR(160) DEFAULT NULL,
                dateDepart DATE NOT NULL,
                dateRetour DATE NOT NULL,
                prix DECIMAL(10,2) NOT NULL DEFAULT 0,
                moyenTransport VARCHAR(120) DEFAULT NULL,
                hotel VARCHAR(160) DEFAULT NULL,
                nbPlaces INT NOT NULL DEFAULT 1,
                disponible TINYINT(1) NOT NULL DEFAULT 1,
                type_voyage VARCHAR(80) DEFAULT NULL,
                package_id INT NOT NULL DEFAULT 0,
                payment_reference VARCHAR(120) DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_voyage_email (client_email),
                INDEX idx_voyage_depart (dateDepart),
                INDEX idx_voyage_payment_reference (payment_reference)
            )'
        );

        $columns = [
            'client_nom' => 'ALTER TABLE '.self::TABLE_NAME.' ADD COLUMN client_nom VARCHAR(150) NOT NULL DEFAULT "" AFTER idVoyage',
            'client_email' => 'ALTER TABLE '.self::TABLE_NAME.' ADD COLUMN client_email VARCHAR(190) DEFAULT NULL AFTER client_nom',
            'destination' => 'ALTER TABLE '.self::TABLE_NAME.' ADD COLUMN destination VARCHAR(160) NOT NULL DEFAULT "" AFTER client_email',
            'pays' => 'ALTER TABLE '.self::TABLE_NAME.' ADD COLUMN pays VARCHAR(160) DEFAULT NULL AFTER destination',
            'dateDepart' => 'ALTER TABLE '.self::TABLE_NAME.' ADD COLUMN dateDepart DATE NOT NULL DEFAULT CURRENT_DATE AFTER pays',
            'dateRetour' => 'ALTER TABLE '.self::TABLE_NAME.' ADD COLUMN dateRetour DATE NOT NULL DEFAULT CURRENT_DATE AFTER dateDepart',
            'prix' => 'ALTER TABLE '.self::TABLE_NAME.' ADD COLUMN prix DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER dateRetour',
            'moyenTransport' => 'ALTER TABLE '.self::TABLE_NAME.' ADD COLUMN moyenTransport VARCHAR(120) DEFAULT NULL AFTER prix',
            'hotel' => 'ALTER TABLE '.self::TABLE_NAME.' ADD COLUMN hotel VARCHAR(160) DEFAULT NULL AFTER moyenTransport',
            'nbPlaces' => 'ALTER TABLE '.self::TABLE_NAME.' ADD COLUMN nbPlaces INT NOT NULL DEFAULT 1 AFTER hotel',
            'disponible' => 'ALTER TABLE '.self::TABLE_NAME.' ADD COLUMN disponible TINYINT(1) NOT NULL DEFAULT 1 AFTER nbPlaces',
            'type_voyage' => 'ALTER TABLE '.self::TABLE_NAME.' ADD COLUMN type_voyage VARCHAR(80) DEFAULT NULL AFTER disponible',
            'package_id' => 'ALTER TABLE '.self::TABLE_NAME.' ADD COLUMN package_id INT NOT NULL DEFAULT 0 AFTER type_voyage',
            'payment_reference' => 'ALTER TABLE '.self::TABLE_NAME.' ADD COLUMN payment_reference VARCHAR(120) DEFAULT NULL AFTER package_id',
            'created_at' => 'ALTER TABLE '.self::TABLE_NAME.' ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER payment_reference',
        ];

        foreach ($columns as $column => $sql) {
            if (!$this->columnExists($connection, self::TABLE_NAME, $column)) {
                $connection->exec($sql);
            }
        }

        $this->schemaEnsured = true;
    }

    private function columnExists(PDO $connection, string $tableName, string $columnName): bool
    {
        $statement = $connection->prepare('SHOW COLUMNS FROM '.$tableName.' LIKE :column');
        $statement->execute(['column' => $columnName]);

        return (bool) $statement->fetch();
    }
}
