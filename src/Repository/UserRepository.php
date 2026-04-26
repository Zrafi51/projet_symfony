<?php

namespace App\Repository;

use App\Database\PdoConnectionFactory;
use App\Security\LegacyPasswordHasher;
use PDO;
use RuntimeException;

final class UserRepository
{
    private const USER_TABLE = '`user`';

    private bool $schemaEnsured = false;

    public function __construct(
        private readonly PdoConnectionFactory $connectionFactory,
        private readonly LegacyPasswordHasher $passwordHasher,
    ) {
    }

    public function isDatabaseAvailable(): bool
    {
        try {
            $this->connectionFactory->getConnection();

            return true;
        } catch (RuntimeException) {
            return false;
        }
    }

    public function getByEmail(string $email): ?array
    {
        $this->ensureSchema();
        $statement = $this->connectionFactory->getConnection()->prepare(
            'SELECT * FROM '.self::USER_TABLE.' WHERE LOWER(email) = LOWER(:email) LIMIT 1'
        );
        $statement->execute([
            'email' => $this->normalizeEmail($email),
        ]);

        $user = $statement->fetch();

        return $user ? $this->mapUser($user) : null;
    }

    public function getById(int $id): ?array
    {
        $this->ensureSchema();
        $statement = $this->connectionFactory->getConnection()->prepare(
            'SELECT * FROM '.self::USER_TABLE.' WHERE id = :id LIMIT 1'
        );
        $statement->execute([
            'id' => $id,
        ]);

        $user = $statement->fetch();

        return $user ? $this->mapUser($user) : null;
    }

    public function getAllUsers(): array
    {
        $this->ensureSchema();
        $statement = $this->connectionFactory->getConnection()->query(
            'SELECT * FROM '.self::USER_TABLE.' ORDER BY id DESC'
        );
        $rows = $statement->fetchAll();

        return array_map(fn (array $row): array => $this->mapUser($row), $rows);
    }

    public function search(string $criteria): array
    {
        $this->ensureSchema();
        $pattern = '%'.strtolower(trim($criteria)).'%';
        $statement = $this->connectionFactory->getConnection()->prepare(
            'SELECT * FROM '.self::USER_TABLE.'
             WHERE LOWER(nom) LIKE :pattern
                OR LOWER(prenom) LIKE :pattern
                OR LOWER(email) LIKE :pattern
             ORDER BY id DESC'
        );
        $statement->execute([
            'pattern' => $pattern,
        ]);
        $rows = $statement->fetchAll();

        return array_map(fn (array $row): array => $this->mapUser($row), $rows);
    }

    public function emailExists(string $email): bool
    {
        $this->ensureSchema();
        $statement = $this->connectionFactory->getConnection()->prepare(
            'SELECT COUNT(*) FROM '.self::USER_TABLE.' WHERE LOWER(email) = LOWER(:email)'
        );
        $statement->execute([
            'email' => $this->normalizeEmail($email),
        ]);

        return (int) $statement->fetchColumn() > 0;
    }

    public function getUsersByRole(string $role): array
    {
        $this->ensureSchema();
        $statement = $this->connectionFactory->getConnection()->prepare(
            'SELECT * FROM '.self::USER_TABLE.' WHERE role = :role ORDER BY id DESC'
        );
        $statement->execute([
            'role' => $this->normalizeRole($role),
        ]);

        $rows = $statement->fetchAll();

        return array_map(fn (array $row): array => $this->mapUser($row), $rows);
    }

    public function register(array $payload): bool
    {
        $this->ensureSchema();
        $connection = $this->connectionFactory->getConnection();
        $statement = $connection->prepare(
            'INSERT INTO '.self::USER_TABLE.' (
                id, nom, prenom, email, password, telephone, adresse, date_naissance,
                role, photo_url, is_active, is_validated, validated_at, date_inscription
            ) VALUES (
                :id, :nom, :prenom, :email, :password, :telephone, :adresse, :date_naissance,
                :role, :photo_url, :is_active, :is_validated, :validated_at, :date_inscription
            )'
        );

        $role = $this->normalizeRole((string) ($payload['role'] ?? 'USER'));
        $validated = $this->shouldAutoValidate($role, (bool) ($payload['is_validated'] ?? false));
        $validatedAt = $validated ? date('Y-m-d H:i:s') : null;

        return $statement->execute([
            'id' => $this->getNextId($connection),
            'nom' => trim((string) ($payload['nom'] ?? '')),
            'prenom' => trim((string) ($payload['prenom'] ?? '')),
            'email' => $this->normalizeEmail((string) ($payload['email'] ?? '')),
            'password' => $this->passwordHasher->hashPassword((string) ($payload['password'] ?? '')),
            'telephone' => $this->nullableString($payload['telephone'] ?? null),
            'adresse' => $this->nullableString($payload['adresse'] ?? null),
            'date_naissance' => $payload['date_naissance'] ?? null,
            'role' => $role,
            'photo_url' => $this->nullableString($payload['photo_url'] ?? null),
            'is_active' => (int) (($payload['is_active'] ?? true) ? 1 : 0),
            'is_validated' => (int) ($validated ? 1 : 0),
            'validated_at' => $validatedAt,
            'date_inscription' => $payload['date_inscription'] ?? date('Y-m-d'),
        ]);
    }

    public function updateUser(int $id, array $payload): bool
    {
        $this->ensureSchema();
        $statement = $this->connectionFactory->getConnection()->prepare(
            'UPDATE '.self::USER_TABLE.'
             SET nom = :nom,
                 prenom = :prenom,
                 email = :email,
                 telephone = :telephone,
                 adresse = :adresse,
                 date_naissance = :date_naissance,
                 role = :role,
                 photo_url = :photo_url,
                 is_active = :is_active
             WHERE id = :id'
        );

        return $statement->execute([
            'id' => $id,
            'nom' => trim((string) ($payload['nom'] ?? '')),
            'prenom' => trim((string) ($payload['prenom'] ?? '')),
            'email' => $this->normalizeEmail((string) ($payload['email'] ?? '')),
            'telephone' => $this->nullableString($payload['telephone'] ?? null),
            'adresse' => $this->nullableString($payload['adresse'] ?? null),
            'date_naissance' => $payload['date_naissance'] ?? null,
            'role' => $this->normalizeRole((string) ($payload['role'] ?? 'USER')),
            'photo_url' => $this->nullableString($payload['photo_url'] ?? null),
            'is_active' => (int) (($payload['is_active'] ?? true) ? 1 : 0),
        ]);
    }

    public function updateProfileByEmail(string $currentEmail, array $payload): bool
    {
        $this->ensureSchema();
        $statement = $this->connectionFactory->getConnection()->prepare(
            'UPDATE '.self::USER_TABLE.'
             SET nom = :nom,
                 prenom = :prenom,
                 email = :email,
                 telephone = :telephone,
                 adresse = :adresse,
                 date_naissance = :date_naissance,
                 role = :role,
                 photo_url = :photo_url,
                 is_active = :is_active
             WHERE LOWER(email) = LOWER(:current_email)'
        );

        return $statement->execute([
            'nom' => trim((string) ($payload['nom'] ?? '')),
            'prenom' => trim((string) ($payload['prenom'] ?? '')),
            'email' => $this->normalizeEmail((string) ($payload['email'] ?? '')),
            'telephone' => $this->nullableString($payload['telephone'] ?? null),
            'adresse' => $this->nullableString($payload['adresse'] ?? null),
            'date_naissance' => $payload['date_naissance'] ?? null,
            'role' => $this->normalizeRole((string) ($payload['role'] ?? 'USER')),
            'photo_url' => $this->nullableString($payload['photo_url'] ?? null),
            'is_active' => (int) (($payload['is_active'] ?? true) ? 1 : 0),
            'current_email' => $this->normalizeEmail($currentEmail),
        ]);
    }

    public function updatePassword(string $email, string $currentPassword, string $newPassword): bool
    {
        $user = $this->getByEmail($email);
        if ($user === null || !$this->passwordHasher->checkPassword($currentPassword, (string) ($user['password'] ?? ''))) {
            return false;
        }

        return $this->updatePasswordDirect($email, $newPassword);
    }

    public function updatePasswordDirect(string $email, string $newPassword): bool
    {
        $this->ensureSchema();
        $statement = $this->connectionFactory->getConnection()->prepare(
            'UPDATE '.self::USER_TABLE.' SET password = :password WHERE LOWER(email) = LOWER(:email)'
        );

        return $statement->execute([
            'password' => $this->passwordHasher->hashPassword($newPassword),
            'email' => $this->normalizeEmail($email),
        ]);
    }

    public function validateUserAccount(string $email): bool
    {
        $this->ensureSchema();
        $statement = $this->connectionFactory->getConnection()->prepare(
            'UPDATE '.self::USER_TABLE.'
             SET is_validated = 1,
                 is_active = 1,
                 validated_at = CURRENT_TIMESTAMP
             WHERE LOWER(email) = LOWER(:email)'
        );

        return $statement->execute([
            'email' => $this->normalizeEmail($email),
        ]);
    }

    public function suspendUserAccount(string $email): bool
    {
        return $this->updateActiveState($email, false);
    }

    public function reactivateUserAccount(string $email): bool
    {
        return $this->updateActiveState($email, true);
    }

    public function updateUserRole(string $email, string $newRole): bool
    {
        $this->ensureSchema();
        $statement = $this->connectionFactory->getConnection()->prepare(
            'UPDATE '.self::USER_TABLE.'
             SET role = :role,
                 is_active = 1,
                 is_validated = 1,
                 validated_at = COALESCE(validated_at, CURRENT_TIMESTAMP)
             WHERE LOWER(email) = LOWER(:email)'
        );

        return $statement->execute([
            'role' => $this->normalizeRole($newRole),
            'email' => $this->normalizeEmail($email),
        ]);
    }

    public function deleteUserAccount(string $email): bool
    {
        $this->ensureSchema();
        $statement = $this->connectionFactory->getConnection()->prepare(
            'DELETE FROM '.self::USER_TABLE.' WHERE LOWER(email) = LOWER(:email)'
        );

        return $statement->execute([
            'email' => $this->normalizeEmail($email),
        ]);
    }

    public function deleteById(int $id): bool
    {
        $this->ensureSchema();
        $statement = $this->connectionFactory->getConnection()->prepare(
            'DELETE FROM '.self::USER_TABLE.' WHERE id = :id'
        );

        return $statement->execute([
            'id' => $id,
        ]);
    }

    private function ensureSchema(): void
    {
        if ($this->schemaEnsured) {
            return;
        }

        $connection = $this->connectionFactory->getConnection();
        $this->addColumnIfMissing(
            $connection,
            'is_validated',
            'ALTER TABLE '.self::USER_TABLE.' ADD COLUMN is_validated TINYINT(1) NOT NULL DEFAULT 1 AFTER is_active'
        );
        $this->addColumnIfMissing(
            $connection,
            'validated_at',
            'ALTER TABLE '.self::USER_TABLE.' ADD COLUMN validated_at TIMESTAMP NULL DEFAULT NULL AFTER is_validated'
        );

        $this->schemaEnsured = true;
    }

    private function addColumnIfMissing(PDO $connection, string $columnName, string $sql): void
    {
        $statement = $connection->prepare('SHOW COLUMNS FROM '.self::USER_TABLE.' LIKE :columnName');
        $statement->execute(['columnName' => $columnName]);

        if ($statement->fetch()) {
            return;
        }

        $connection->exec($sql);
    }

    private function getNextId(PDO $connection): int
    {
        $statement = $connection->query('SELECT COALESCE(MAX(id), 0) + 1 FROM '.self::USER_TABLE);

        return (int) $statement->fetchColumn();
    }

    private function mapUser(array $row): array
    {
        $role = $this->normalizeRole((string) ($row['role'] ?? 'USER'));
        $isActive = (bool) ($row['is_active'] ?? true);
        $isValidated = array_key_exists('is_validated', $row) ? (bool) $row['is_validated'] : true;

        return [
            'id' => (int) ($row['id'] ?? 0),
            'nom' => (string) ($row['nom'] ?? ''),
            'prenom' => (string) ($row['prenom'] ?? ''),
            'email' => (string) ($row['email'] ?? ''),
            'password' => (string) ($row['password'] ?? ''),
            'telephone' => (string) ($row['telephone'] ?? ''),
            'adresse' => (string) ($row['adresse'] ?? ''),
            'date_naissance' => $row['date_naissance'] ?? null,
            'role' => $role,
            'photo_url' => (string) ($row['photo_url'] ?? ''),
            'is_active' => $isActive,
            'is_validated' => $isValidated,
            'validated_at' => $row['validated_at'] ?? null,
            'date_inscription' => $row['date_inscription'] ?? null,
            'display_name' => trim(((string) ($row['prenom'] ?? '')).' '.((string) ($row['nom'] ?? ''))) ?: (string) ($row['email'] ?? ''),
            'is_pending_validation' => $isActive && !$isValidated && !in_array($role, ['ADMIN', 'SUPER_ADMIN', 'AGENT'], true),
        ];
    }

    private function shouldAutoValidate(string $role, bool $validated): bool
    {
        if (in_array($role, ['ADMIN', 'SUPER_ADMIN', 'AGENT'], true)) {
            return true;
        }

        return $validated;
    }

    private function normalizeEmail(string $email): string
    {
        return strtolower(trim($email));
    }

    private function normalizeRole(string $role): string
    {
        $role = strtoupper(trim($role));

        return $role !== '' ? $role : 'USER';
    }

    private function updateActiveState(string $email, bool $active): bool
    {
        $this->ensureSchema();
        $statement = $this->connectionFactory->getConnection()->prepare(
            'UPDATE '.self::USER_TABLE.' SET is_active = :is_active WHERE LOWER(email) = LOWER(:email)'
        );

        return $statement->execute([
            'is_active' => (int) $active,
            'email' => $this->normalizeEmail($email),
        ]);
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }
}
