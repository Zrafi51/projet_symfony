<?php

namespace App\Repository;

use App\Database\PdoConnectionFactory;
use PDO;

final class FactureRepository
{
    private const TABLE_NAME = 'factures';

    private bool $schemaEnsured = false;

    public function __construct(private readonly PdoConnectionFactory $connectionFactory)
    {
    }

    public function findAll(string $search = ''): array
    {
        $this->ensureSchema();
        $search = trim($search);

        if ($search === '') {
            return $this->connectionFactory->getConnection()
                ->query('SELECT * FROM '.self::TABLE_NAME.' ORDER BY date_emission DESC, id DESC')
                ->fetchAll();
        }

        $pattern = '%'.mb_strtolower($search).'%';
        $statement = $this->connectionFactory->getConnection()->prepare(
            'SELECT * FROM '.self::TABLE_NAME.'
             WHERE LOWER(numero_facture) LIKE :pattern
                OR LOWER(client_nom) LIKE :pattern
                OR LOWER(client_email) LIKE :pattern
                OR LOWER(destination) LIKE :pattern
             ORDER BY date_emission DESC, id DESC'
        );
        $statement->execute(['pattern' => $pattern]);

        return $statement->fetchAll();
    }

    public function find(int $id): ?array
    {
        $this->ensureSchema();
        $statement = $this->connectionFactory->getConnection()->prepare(
            'SELECT * FROM '.self::TABLE_NAME.' WHERE id = :id LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();

        return $row ?: null;
    }

    public function findByNumero(string $numero): ?array
    {
        $this->ensureSchema();
        $statement = $this->connectionFactory->getConnection()->prepare(
            'SELECT * FROM '.self::TABLE_NAME.' WHERE numero_facture = :numero LIMIT 1'
        );
        $statement->execute(['numero' => trim($numero)]);
        $row = $statement->fetch();

        return $row ?: null;
    }

    public function findByPaiementId(int $paiementId): ?array
    {
        $this->ensureSchema();
        $statement = $this->connectionFactory->getConnection()->prepare(
            'SELECT * FROM '.self::TABLE_NAME.' WHERE paiement_id = :paiement_id ORDER BY id DESC LIMIT 1'
        );
        $statement->execute(['paiement_id' => $paiementId]);
        $row = $statement->fetch();

        return $row ?: null;
    }

    public function findByClientEmail(string $clientEmail): array
    {
        $this->ensureSchema();
        $statement = $this->connectionFactory->getConnection()->prepare(
            'SELECT * FROM '.self::TABLE_NAME.'
             WHERE LOWER(client_email) = LOWER(:client_email) AND UPPER(statut) = :status
             ORDER BY date_emission DESC, id DESC'
        );
        $statement->execute([
            'client_email' => trim($clientEmail),
            'status' => 'ENVOYEE',
        ]);

        return $statement->fetchAll();
    }

    public function create(array $payload): int
    {
        $this->ensureSchema();
        $statement = $this->connectionFactory->getConnection()->prepare(
            'INSERT INTO '.self::TABLE_NAME.' (
                numero_facture, date_emission, client_nom, client_email, client_adresse,
                destination, montant_transport, montant_hebergement, montant_activites,
                montant_total, statut, paiement_id, type_voyage, nb_personnes, date_debut, date_fin
            ) VALUES (
                :numero_facture, :date_emission, :client_nom, :client_email, :client_adresse,
                :destination, :montant_transport, :montant_hebergement, :montant_activites,
                :montant_total, :statut, :paiement_id, :type_voyage, :nb_personnes, :date_debut, :date_fin
            )'
        );
        $statement->execute($this->normalizePayload($payload));

        return (int) $this->connectionFactory->getConnection()->lastInsertId();
    }

    public function update(int $id, array $payload): bool
    {
        $this->ensureSchema();
        $statement = $this->connectionFactory->getConnection()->prepare(
            'UPDATE '.self::TABLE_NAME.'
             SET numero_facture = :numero_facture,
                 date_emission = :date_emission,
                 client_nom = :client_nom,
                 client_email = :client_email,
                 client_adresse = :client_adresse,
                 destination = :destination,
                 montant_transport = :montant_transport,
                 montant_hebergement = :montant_hebergement,
                 montant_activites = :montant_activites,
                 montant_total = :montant_total,
                 statut = :statut,
                 paiement_id = :paiement_id,
                 type_voyage = :type_voyage,
                 nb_personnes = :nb_personnes,
                 date_debut = :date_debut,
                 date_fin = :date_fin
             WHERE id = :id'
        );

        return $statement->execute([
            ...$this->normalizePayload($payload, false),
            'id' => $id,
        ]);
    }

    public function delete(int $id): bool
    {
        $this->ensureSchema();
        $statement = $this->connectionFactory->getConnection()->prepare(
            'DELETE FROM '.self::TABLE_NAME.' WHERE id = :id'
        );

        return $statement->execute(['id' => $id]);
    }

    public function getStats(): array
    {
        $this->ensureSchema();

        return [
            'total' => (int) $this->fetchScalar('SELECT COUNT(*) FROM '.self::TABLE_NAME, 0),
            'sent' => (int) $this->fetchScalar(
                "SELECT COUNT(*) FROM ".self::TABLE_NAME." WHERE UPPER(statut) = 'ENVOYEE'",
                0
            ),
            'draft' => (int) $this->fetchScalar(
                "SELECT COUNT(*) FROM ".self::TABLE_NAME." WHERE UPPER(statut) <> 'ENVOYEE'",
                0
            ),
            'total_amount' => (float) $this->fetchScalar(
                'SELECT COALESCE(SUM(montant_total), 0) FROM '.self::TABLE_NAME,
                0
            ),
        ];
    }

    private function normalizePayload(array $payload, bool $generateNumber = true): array
    {
        $numeroFacture = trim((string) ($payload['numero_facture'] ?? ''));
        if ($numeroFacture === '' && $generateNumber) {
            $numeroFacture = $this->generateInvoiceNumber();
        }

        return [
            'numero_facture' => $numeroFacture !== '' ? $numeroFacture : $this->generateInvoiceNumber(),
            'date_emission' => trim((string) ($payload['date_emission'] ?? '')) !== ''
                ? trim((string) $payload['date_emission'])
                : date('Y-m-d'),
            'client_nom' => trim((string) ($payload['client_nom'] ?? '')),
            'client_email' => trim((string) ($payload['client_email'] ?? '')),
            'client_adresse' => trim((string) ($payload['client_adresse'] ?? '')),
            'destination' => trim((string) ($payload['destination'] ?? '')),
            'montant_transport' => round((float) ($payload['montant_transport'] ?? 0), 2),
            'montant_hebergement' => round((float) ($payload['montant_hebergement'] ?? 0), 2),
            'montant_activites' => round((float) ($payload['montant_activites'] ?? 0), 2),
            'montant_total' => round((float) ($payload['montant_total'] ?? 0), 2),
            'statut' => strtoupper(trim((string) ($payload['statut'] ?? 'EMISE'))) ?: 'EMISE',
            'paiement_id' => max(0, (int) ($payload['paiement_id'] ?? 0)),
            'type_voyage' => trim((string) ($payload['type_voyage'] ?? '')),
            'nb_personnes' => max(1, (int) ($payload['nb_personnes'] ?? 1)),
            'date_debut' => trim((string) ($payload['date_debut'] ?? '')),
            'date_fin' => trim((string) ($payload['date_fin'] ?? '')),
        ];
    }

    private function generateInvoiceNumber(): string
    {
        return 'FAC-'.date('Y').'-'.date('YmdHis').'-'.random_int(10, 99);
    }

    private function fetchScalar(string $sql, int|float|string $fallback): int|float|string
    {
        $value = $this->connectionFactory->getConnection()->query($sql)->fetchColumn();

        return $value !== false && $value !== null ? $value : $fallback;
    }

    private function ensureSchema(): void
    {
        if ($this->schemaEnsured) {
            return;
        }

        $connection = $this->connectionFactory->getConnection();
        $connection->exec(
            'CREATE TABLE IF NOT EXISTS '.self::TABLE_NAME.' (
                id INT PRIMARY KEY AUTO_INCREMENT,
                numero_facture VARCHAR(120) NOT NULL,
                date_emission DATE NOT NULL,
                client_nom VARCHAR(150) NOT NULL,
                client_email VARCHAR(180) DEFAULT NULL,
                client_adresse VARCHAR(255) DEFAULT NULL,
                destination VARCHAR(150) NOT NULL,
                montant_transport DECIMAL(10,2) NOT NULL DEFAULT 0,
                montant_hebergement DECIMAL(10,2) NOT NULL DEFAULT 0,
                montant_activites DECIMAL(10,2) NOT NULL DEFAULT 0,
                montant_total DECIMAL(10,2) NOT NULL DEFAULT 0,
                statut VARCHAR(40) NOT NULL DEFAULT "EMISE",
                paiement_id INT NOT NULL DEFAULT 0,
                type_voyage VARCHAR(80) DEFAULT NULL,
                nb_personnes INT NOT NULL DEFAULT 1,
                date_debut VARCHAR(40) DEFAULT NULL,
                date_fin VARCHAR(40) DEFAULT NULL
            )'
        );

        $columns = [
            'numero_facture' => 'ALTER TABLE '.self::TABLE_NAME.' ADD COLUMN numero_facture VARCHAR(120) NOT NULL DEFAULT ""',
            'date_emission' => 'ALTER TABLE '.self::TABLE_NAME.' ADD COLUMN date_emission DATE NOT NULL DEFAULT "'.date('Y-m-d').'"',
            'client_nom' => 'ALTER TABLE '.self::TABLE_NAME.' ADD COLUMN client_nom VARCHAR(150) NOT NULL DEFAULT ""',
            'client_email' => 'ALTER TABLE '.self::TABLE_NAME.' ADD COLUMN client_email VARCHAR(180) DEFAULT NULL',
            'client_adresse' => 'ALTER TABLE '.self::TABLE_NAME.' ADD COLUMN client_adresse VARCHAR(255) DEFAULT NULL',
            'destination' => 'ALTER TABLE '.self::TABLE_NAME.' ADD COLUMN destination VARCHAR(150) NOT NULL DEFAULT ""',
            'montant_transport' => 'ALTER TABLE '.self::TABLE_NAME.' ADD COLUMN montant_transport DECIMAL(10,2) NOT NULL DEFAULT 0',
            'montant_hebergement' => 'ALTER TABLE '.self::TABLE_NAME.' ADD COLUMN montant_hebergement DECIMAL(10,2) NOT NULL DEFAULT 0',
            'montant_activites' => 'ALTER TABLE '.self::TABLE_NAME.' ADD COLUMN montant_activites DECIMAL(10,2) NOT NULL DEFAULT 0',
            'montant_total' => 'ALTER TABLE '.self::TABLE_NAME.' ADD COLUMN montant_total DECIMAL(10,2) NOT NULL DEFAULT 0',
            'statut' => 'ALTER TABLE '.self::TABLE_NAME.' ADD COLUMN statut VARCHAR(40) NOT NULL DEFAULT "EMISE"',
            'paiement_id' => 'ALTER TABLE '.self::TABLE_NAME.' ADD COLUMN paiement_id INT NOT NULL DEFAULT 0',
            'type_voyage' => 'ALTER TABLE '.self::TABLE_NAME.' ADD COLUMN type_voyage VARCHAR(80) DEFAULT NULL',
            'nb_personnes' => 'ALTER TABLE '.self::TABLE_NAME.' ADD COLUMN nb_personnes INT NOT NULL DEFAULT 1',
            'date_debut' => 'ALTER TABLE '.self::TABLE_NAME.' ADD COLUMN date_debut VARCHAR(40) DEFAULT NULL',
            'date_fin' => 'ALTER TABLE '.self::TABLE_NAME.' ADD COLUMN date_fin VARCHAR(40) DEFAULT NULL',
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
