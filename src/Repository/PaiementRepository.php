<?php

namespace App\Repository;

use App\Database\PdoConnectionFactory;
use PDO;

final class PaiementRepository
{
    private const TABLE_NAME = 'paiements';

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
                ->query('SELECT * FROM '.self::TABLE_NAME.' ORDER BY date_paiement DESC, id DESC')
                ->fetchAll();
        }

        $pattern = '%'.mb_strtolower($search).'%';
        $statement = $this->connectionFactory->getConnection()->prepare(
            'SELECT * FROM '.self::TABLE_NAME.'
             WHERE LOWER(client_nom) LIKE :pattern
                OR LOWER(client_email) LIKE :pattern
                OR LOWER(destination) LIKE :pattern
                OR LOWER(reference_transaction) LIKE :pattern
             ORDER BY date_paiement DESC, id DESC'
        );
        $statement->execute(['pattern' => $pattern]);

        return $statement->fetchAll();
    }

    public function findPaidPayments(): array
    {
        $this->ensureSchema();
        $statement = $this->connectionFactory->getConnection()->prepare(
            'SELECT * FROM '.self::TABLE_NAME.'
             WHERE UPPER(statut) = :status
             ORDER BY date_paiement DESC, id DESC'
        );
        $statement->execute(['status' => 'PAYE']);

        return $statement->fetchAll();
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
             ORDER BY date_paiement DESC, id DESC'
        );
        $statement->execute(['email' => $normalizedEmail]);

        return $statement->fetchAll();
    }

    public function findByClientName(string $name): array
    {
        $this->ensureSchema();
        $statement = $this->connectionFactory->getConnection()->prepare(
            'SELECT * FROM '.self::TABLE_NAME.'
             WHERE LOWER(client_nom) LIKE :name
             ORDER BY date_paiement DESC, id DESC'
        );
        $statement->execute(['name' => '%'.mb_strtolower(trim($name)).'%']);

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

    public function findByReference(string $reference): ?array
    {
        $this->ensureSchema();
        $statement = $this->connectionFactory->getConnection()->prepare(
            'SELECT * FROM '.self::TABLE_NAME.' WHERE reference_transaction = :reference LIMIT 1'
        );
        $statement->execute(['reference' => trim($reference)]);
        $row = $statement->fetch();

        return $row ?: null;
    }

    public function create(array $payload): int
    {
        $this->ensureSchema();
        $statement = $this->connectionFactory->getConnection()->prepare(
            'INSERT INTO '.self::TABLE_NAME.' (
                client_nom, client_email, destination, montant, date_paiement, statut,
                reference_transaction, package_id, numero_carte_masque, type_voyage
            ) VALUES (
                :client_nom, :client_email, :destination, :montant, :date_paiement, :statut,
                :reference_transaction, :package_id, :numero_carte_masque, :type_voyage
            )'
        );
        $statement->execute($this->normalizePayload($payload));

        return (int) $this->connectionFactory->getConnection()->lastInsertId();
    }

    public function update(int $id, array $payload): bool
    {
        $this->ensureSchema();
        $current = $this->find($id) ?? [];
        $normalized = $this->normalizePayload([
            ...$current,
            ...$payload,
        ], false);
        $statement = $this->connectionFactory->getConnection()->prepare(
            'UPDATE '.self::TABLE_NAME.'
             SET client_nom = :client_nom,
                 client_email = :client_email,
                 destination = :destination,
                 montant = :montant,
                 date_paiement = :date_paiement,
                 statut = :statut,
                 reference_transaction = :reference_transaction,
                 package_id = :package_id,
                 numero_carte_masque = :numero_carte_masque,
                 type_voyage = :type_voyage
             WHERE id = :id'
        );

        return $statement->execute([
            ...$normalized,
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
            'paid' => (int) $this->fetchScalar(
                "SELECT COUNT(*) FROM ".self::TABLE_NAME." WHERE UPPER(statut) = 'PAYE'",
                0
            ),
            'revenue' => (float) $this->fetchScalar(
                "SELECT COALESCE(SUM(montant), 0) FROM ".self::TABLE_NAME." WHERE UPPER(statut) = 'PAYE'",
                0
            ),
            'last_payment_at' => (string) $this->fetchScalar(
                'SELECT COALESCE(MAX(date_paiement), "") FROM '.self::TABLE_NAME,
                ''
            ),
        ];
    }

    private function normalizePayload(array $payload, bool $generateReference = true): array
    {
        $reference = trim((string) ($payload['reference_transaction'] ?? ''));
        if ($reference === '' && $generateReference) {
            $reference = $this->generateReference();
        }

        return [
            'client_nom' => trim((string) ($payload['client_nom'] ?? '')),
            'client_email' => mb_strtolower(trim((string) ($payload['client_email'] ?? ''))),
            'destination' => trim((string) ($payload['destination'] ?? '')),
            'montant' => round((float) ($payload['montant'] ?? 0), 2),
            'date_paiement' => trim((string) ($payload['date_paiement'] ?? '')) !== ''
                ? trim((string) $payload['date_paiement'])
                : date('Y-m-d H:i:s'),
            'statut' => strtoupper(trim((string) ($payload['statut'] ?? 'PAYE'))) ?: 'PAYE',
            'reference_transaction' => $reference !== '' ? $reference : $this->generateReference(),
            'package_id' => max(0, (int) ($payload['package_id'] ?? 0)),
            'numero_carte_masque' => trim((string) ($payload['numero_carte_masque'] ?? '')),
            'type_voyage' => trim((string) ($payload['type_voyage'] ?? 'Aventure')),
        ];
    }

    private function generateReference(): string
    {
        return 'PAY-'.date('YmdHis').'-'.random_int(1000, 9999);
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
                client_nom VARCHAR(150) NOT NULL,
                client_email VARCHAR(190) DEFAULT NULL,
                destination VARCHAR(150) NOT NULL,
                montant DECIMAL(10,2) NOT NULL DEFAULT 0,
                date_paiement DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                statut VARCHAR(40) NOT NULL DEFAULT "PAYE",
                reference_transaction VARCHAR(120) NOT NULL,
                package_id INT NOT NULL DEFAULT 0,
                numero_carte_masque VARCHAR(40) DEFAULT NULL,
                type_voyage VARCHAR(80) DEFAULT NULL
            )'
        );

        $columns = [
            'client_nom' => 'ALTER TABLE '.self::TABLE_NAME.' ADD COLUMN client_nom VARCHAR(150) NOT NULL DEFAULT ""',
            'client_email' => 'ALTER TABLE '.self::TABLE_NAME.' ADD COLUMN client_email VARCHAR(190) DEFAULT NULL AFTER client_nom',
            'destination' => 'ALTER TABLE '.self::TABLE_NAME.' ADD COLUMN destination VARCHAR(150) NOT NULL DEFAULT ""',
            'montant' => 'ALTER TABLE '.self::TABLE_NAME.' ADD COLUMN montant DECIMAL(10,2) NOT NULL DEFAULT 0',
            'date_paiement' => 'ALTER TABLE '.self::TABLE_NAME.' ADD COLUMN date_paiement DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP',
            'statut' => 'ALTER TABLE '.self::TABLE_NAME.' ADD COLUMN statut VARCHAR(40) NOT NULL DEFAULT "PAYE"',
            'reference_transaction' => 'ALTER TABLE '.self::TABLE_NAME.' ADD COLUMN reference_transaction VARCHAR(120) NOT NULL DEFAULT ""',
            'package_id' => 'ALTER TABLE '.self::TABLE_NAME.' ADD COLUMN package_id INT NOT NULL DEFAULT 0',
            'numero_carte_masque' => 'ALTER TABLE '.self::TABLE_NAME.' ADD COLUMN numero_carte_masque VARCHAR(40) DEFAULT NULL',
            'type_voyage' => 'ALTER TABLE '.self::TABLE_NAME.' ADD COLUMN type_voyage VARCHAR(80) DEFAULT NULL',
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
