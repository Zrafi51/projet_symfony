<?php

namespace App\Repository;

use App\Database\PdoConnectionFactory;
use PDO;

final class SupportRepository
{
    private const CONTACTS_TABLE = 'contacts';
    private const RECLAMATION_TABLE = 'reclamation';
    private const REPONSE_TABLE = 'reponse';

    private bool $contactsTableEnsured = false;

    public function __construct(private readonly PdoConnectionFactory $connectionFactory)
    {
    }

    public function submitGuestContact(string $name, string $email, ?string $phone, string $subject, string $message): bool
    {
        $this->ensureContactsTable();

        $statement = $this->connectionFactory->getConnection()->prepare(
            'INSERT INTO '.self::CONTACTS_TABLE.' (name, email, phone, message, created_at)
             VALUES (:name, :email, :phone, :message, NOW())'
        );

        return $statement->execute([
            'name' => trim($name),
            'email' => strtolower(trim($email)),
            'phone' => $this->nullableString($phone),
            'message' => $this->formatGuestContactMessage($subject, $message),
        ]);
    }

    public function createReclamation(int $userId, string $subject, string $description): bool
    {
        $statement = $this->connectionFactory->getConnection()->prepare(
            'INSERT INTO '.self::RECLAMATION_TABLE.' (user_id, sujet, description, statut)
             VALUES (:user_id, :sujet, :description, :statut)'
        );

        return $statement->execute([
            'user_id' => $userId,
            'sujet' => trim($subject),
            'description' => trim($description),
            'statut' => 'EN_ATTENTE',
        ]);
    }

    public function getAdminSupportSnapshot(): array
    {
        $entries = $this->fetchReclamations();
        $responsesByReclamation = $this->fetchResponsesByReclamationIds(array_column($entries, 'id'));

        return $this->buildSnapshot($entries, $responsesByReclamation);
    }

    public function getUserSupportSnapshot(int $userId): array
    {
        $entries = $this->fetchReclamations('WHERE r.user_id = :user_id', ['user_id' => $userId]);
        $responsesByReclamation = $this->fetchResponsesByReclamationIds(array_column($entries, 'id'));

        return $this->buildSnapshot($entries, $responsesByReclamation);
    }

    public function getReclamationById(int $id): ?array
    {
        $rows = $this->fetchReclamations('WHERE r.id = :id', ['id' => $id], 'LIMIT 1');

        return $rows[0] ?? null;
    }

    public function updateReclamationStatus(int $id, string $status): bool
    {
        $statement = $this->connectionFactory->getConnection()->prepare(
            'UPDATE '.self::RECLAMATION_TABLE.' SET statut = :statut WHERE id = :id'
        );

        return $statement->execute([
            'id' => $id,
            'statut' => $this->normalizeStatus($status),
        ]);
    }

    public function addAdminResponse(int $reclamationId, int $adminId, string $content): bool
    {
        $statement = $this->connectionFactory->getConnection()->prepare(
            'INSERT INTO '.self::REPONSE_TABLE.' (reclamation_id, admin_id, contenu)
             VALUES (:reclamation_id, :admin_id, :contenu)'
        );

        return $statement->execute([
            'reclamation_id' => $reclamationId,
            'admin_id' => $adminId,
            'contenu' => trim($content),
        ]);
    }

    private function buildSnapshot(array $entries, array $responsesByReclamation): array
    {
        $counts = [
            'total' => count($entries),
            'pending' => 0,
            'in_progress' => 0,
            'resolved' => 0,
            'rejected' => 0,
            'answered' => 0,
        ];

        foreach ($entries as $entry) {
            $status = strtoupper((string) ($entry['statut'] ?? 'EN_ATTENTE'));
            if ($status === 'EN_ATTENTE') {
                ++$counts['pending'];
            } elseif ($status === 'EN_COURS') {
                ++$counts['in_progress'];
            } elseif ($status === 'RESOLUE') {
                ++$counts['resolved'];
            } elseif ($status === 'REJETEE') {
                ++$counts['rejected'];
            }

            if (($responsesByReclamation[$entry['id']] ?? []) !== []) {
                ++$counts['answered'];
            }
        }

        return [
            'counts' => $counts,
            'reclamations' => $entries,
            'responses_by_reclamation' => $responsesByReclamation,
        ];
    }

    private function fetchReclamations(
        string $whereSql = '',
        array $parameters = [],
        string $suffixSql = ''
    ): array {
        $statement = $this->connectionFactory->getConnection()->prepare(
            'SELECT r.*,
                    u.nom,
                    u.prenom,
                    u.email,
                    u.telephone,
                    u.role,
                    CONCAT(COALESCE(u.prenom, \'\'), \' \', COALESCE(u.nom, \'\')) AS user_name
             FROM '.self::RECLAMATION_TABLE.' r
             LEFT JOIN `user` u ON u.id = r.user_id
             '.$whereSql.'
             ORDER BY r.created_at DESC, r.id DESC
             '.$suffixSql
        );
        $statement->execute($parameters);

        return array_map(
            fn (array $row): array => $this->mapReclamation($row),
            $statement->fetchAll()
        );
    }

    private function fetchResponsesByReclamationIds(array $reclamationIds): array
    {
        $reclamationIds = array_values(array_filter(array_map('intval', $reclamationIds), static fn (int $id): bool => $id > 0));
        if ($reclamationIds === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($reclamationIds), '?'));
        $statement = $this->connectionFactory->getConnection()->prepare(
            'SELECT rep.*,
                    u.email AS admin_email,
                    CONCAT(COALESCE(u.prenom, \'\'), \' \', COALESCE(u.nom, \'\')) AS admin_name
             FROM '.self::REPONSE_TABLE.' rep
             LEFT JOIN `user` u ON u.id = rep.admin_id
             WHERE rep.reclamation_id IN ('.$placeholders.')
             ORDER BY rep.created_at ASC, rep.id ASC'
        );
        $statement->execute($reclamationIds);

        $rows = [];
        foreach ($statement->fetchAll() as $row) {
            $mapped = $this->mapResponse($row);
            $rows[$mapped['reclamation_id']][] = $mapped;
        }

        return $rows;
    }

    private function mapReclamation(array $row): array
    {
        $displayName = trim((string) ($row['user_name'] ?? ''));
        $displayName = $displayName !== '' ? $displayName : (string) ($row['email'] ?? 'Client');
        $status = $this->normalizeStatus((string) ($row['statut'] ?? 'EN_ATTENTE'));

        return [
            'id' => (int) ($row['id'] ?? 0),
            'user_id' => (int) ($row['user_id'] ?? 0),
            'sujet' => (string) ($row['sujet'] ?? ''),
            'description' => (string) ($row['description'] ?? ''),
            'statut' => $status,
            'status_label' => str_replace('_', ' ', $status),
            'status_tone' => $this->resolveStatusTone($status),
            'created_at' => $row['created_at'] ?? null,
            'nom' => (string) ($row['nom'] ?? ''),
            'prenom' => (string) ($row['prenom'] ?? ''),
            'email' => (string) ($row['email'] ?? ''),
            'telephone' => (string) ($row['telephone'] ?? ''),
            'role' => strtoupper((string) ($row['role'] ?? 'USER')),
            'display_name' => $displayName,
        ];
    }

    private function mapResponse(array $row): array
    {
        $adminName = trim((string) ($row['admin_name'] ?? ''));

        return [
            'id' => (int) ($row['id'] ?? 0),
            'reclamation_id' => (int) ($row['reclamation_id'] ?? 0),
            'admin_id' => (int) ($row['admin_id'] ?? 0),
            'contenu' => (string) ($row['contenu'] ?? ''),
            'created_at' => $row['created_at'] ?? null,
            'admin_email' => (string) ($row['admin_email'] ?? ''),
            'admin_name' => $adminName !== '' ? $adminName : 'Administration',
        ];
    }

    private function ensureContactsTable(): void
    {
        if ($this->contactsTableEnsured) {
            return;
        }

        $this->connectionFactory->getConnection()->exec(
            'CREATE TABLE IF NOT EXISTS '.self::CONTACTS_TABLE.' (
                id INT NOT NULL AUTO_INCREMENT,
                name VARCHAR(100) NOT NULL,
                email VARCHAR(100) NOT NULL,
                phone VARCHAR(20) DEFAULT NULL,
                message TEXT NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_contacts_email (email),
                KEY idx_contacts_created_at (created_at)
            )'
        );

        $this->contactsTableEnsured = true;
    }

    private function formatGuestContactMessage(string $subject, string $message): string
    {
        return 'Sujet: '.trim($subject).PHP_EOL.PHP_EOL.trim($message);
    }

    private function normalizeStatus(string $status): string
    {
        $status = strtoupper(trim($status));
        $allowed = ['EN_ATTENTE', 'EN_COURS', 'RESOLUE', 'REJETEE'];

        return in_array($status, $allowed, true) ? $status : 'EN_ATTENTE';
    }

    private function resolveStatusTone(string $status): string
    {
        return match ($status) {
            'RESOLUE' => 'green',
            'REJETEE' => 'red',
            'EN_COURS' => 'blue',
            default => 'orange',
        };
    }

    private function nullableString(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }
}
