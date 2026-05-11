<?php

namespace App\Service;

use App\Database\PdoConnectionFactory;
use App\Repository\UserRepository;
use App\ValueObject\NotificationPreferences;
use PDO;
use RuntimeException;

final class UserNotificationService
{
    private const PREFERENCES_TABLE = 'user_notification_preferences';
    private const NOTIFICATIONS_TABLE = 'user_notifications';

    private bool $tablesEnsured = false;

    public function __construct(
        private readonly PdoConnectionFactory $connectionFactory,
        private readonly UserRepository $userRepository,
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

    public function getPreferences(string $email, string $role): NotificationPreferences
    {
        $this->ensureTables();
        if (trim($email) === '') {
            return NotificationPreferences::defaults();
        }

        $statement = $this->connectionFactory->getConnection()->prepare(
            'SELECT notify_security, notify_booking, notify_forum, notify_offers
             FROM '.self::PREFERENCES_TABLE.'
             WHERE LOWER(user_email) = LOWER(:email)
             LIMIT 1'
        );
        $statement->execute([
            'email' => $this->normalizeEmail($email),
        ]);

        $row = $statement->fetch();
        if (!$row) {
            $defaults = NotificationPreferences::defaults();
            $this->savePreferences($email, $role, $defaults);

            return $defaults;
        }

        return new NotificationPreferences(
            (bool) ($row['notify_security'] ?? true),
            (bool) ($row['notify_booking'] ?? true),
            (bool) ($row['notify_forum'] ?? true),
            (bool) ($row['notify_offers'] ?? false),
        );
    }

    public function savePreferences(string $email, string $role, NotificationPreferences $preferences): bool
    {
        $this->ensureTables();
        if (trim($email) === '') {
            return false;
        }

        $statement = $this->connectionFactory->getConnection()->prepare(
            'INSERT INTO '.self::PREFERENCES_TABLE.' (
                user_email, user_role, notify_security, notify_booking, notify_forum, notify_offers
            ) VALUES (
                :user_email, :user_role, :notify_security, :notify_booking, :notify_forum, :notify_offers
            ) ON DUPLICATE KEY UPDATE
                user_role = VALUES(user_role),
                notify_security = VALUES(notify_security),
                notify_booking = VALUES(notify_booking),
                notify_forum = VALUES(notify_forum),
                notify_offers = VALUES(notify_offers)'
        );

        return $statement->execute([
            'user_email' => $this->normalizeEmail($email),
            'user_role' => $this->normalizeRole($role),
            'notify_security' => (int) $preferences->security(),
            'notify_booking' => (int) $preferences->booking(),
            'notify_forum' => (int) $preferences->forum(),
            'notify_offers' => (int) $preferences->offers(),
        ]);
    }

    public function migrateUserEmail(string $previousEmail, string $newEmail, string $role): void
    {
        $this->ensureTables();
        if (trim($previousEmail) === '' || trim($newEmail) === '' || strcasecmp($previousEmail, $newEmail) === 0) {
            return;
        }

        $connection = $this->connectionFactory->getConnection();
        $connection->prepare(
            'INSERT INTO '.self::PREFERENCES_TABLE.' (
                user_email, user_role, notify_security, notify_booking, notify_forum, notify_offers
            )
            SELECT
                :new_email,
                :user_role,
                notify_security,
                notify_booking,
                notify_forum,
                notify_offers
            FROM '.self::PREFERENCES_TABLE.'
            WHERE LOWER(user_email) = LOWER(:previous_email)
            LIMIT 1
            ON DUPLICATE KEY UPDATE
                user_role = VALUES(user_role),
                notify_security = VALUES(notify_security),
                notify_booking = VALUES(notify_booking),
                notify_forum = VALUES(notify_forum),
                notify_offers = VALUES(notify_offers)'
        )->execute([
            'new_email' => $this->normalizeEmail($newEmail),
            'user_role' => $this->normalizeRole($role),
            'previous_email' => $this->normalizeEmail($previousEmail),
        ]);

        $connection->prepare(
            'DELETE FROM '.self::PREFERENCES_TABLE.'
             WHERE LOWER(user_email) = LOWER(:previous_email)'
        )->execute([
            'previous_email' => $this->normalizeEmail($previousEmail),
        ]);

        $connection->prepare(
            'UPDATE '.self::NOTIFICATIONS_TABLE.'
             SET recipient_email = :new_email
             WHERE LOWER(recipient_email) = LOWER(:previous_email)'
        )->execute([
            'new_email' => $this->normalizeEmail($newEmail),
            'previous_email' => $this->normalizeEmail($previousEmail),
        ]);

        $connection->prepare(
            'UPDATE '.self::NOTIFICATIONS_TABLE.'
             SET sender_email = :new_email
             WHERE LOWER(sender_email) = LOWER(:previous_email)'
        )->execute([
            'new_email' => $this->normalizeEmail($newEmail),
            'previous_email' => $this->normalizeEmail($previousEmail),
        ]);
    }

    public function notifyAdmins(string $senderEmail, string $senderRole, string $category, string $title, string $message): void
    {
        foreach ($this->userRepository->getUsersByRole('ADMIN') as $admin) {
            if (($admin['is_active'] ?? false) !== true || trim((string) ($admin['email'] ?? '')) === '') {
                continue;
            }

            if (!$this->isCategoryAllowed((string) $admin['email'], (string) ($admin['role'] ?? 'ADMIN'), $category)) {
                continue;
            }

            $this->createNotification(
                (string) $admin['email'],
                $senderEmail,
                $senderRole,
                $category,
                $title,
                $message
            );
        }
    }

    public function notifyUser(
        string $recipientEmail,
        string $recipientRole,
        string $senderEmail,
        string $senderRole,
        string $category,
        string $title,
        string $message
    ): void {
        if (!$this->isCategoryAllowed($recipientEmail, $recipientRole, $category)) {
            return;
        }

        $this->createNotification($recipientEmail, $senderEmail, $senderRole, $category, $title, $message);
        $this->saveDefaultPreferences($recipientEmail, $recipientRole);
    }

    public function getLatestNotifications(string $email, int $limit = 5): array
    {
        $this->ensureTables();
        if (trim($email) === '') {
            return [];
        }

        $statement = $this->connectionFactory->getConnection()->prepare(
            'SELECT * FROM '.self::NOTIFICATIONS_TABLE.'
             WHERE LOWER(recipient_email) = LOWER(:email)
             ORDER BY created_at DESC
             LIMIT :limit'
        );
        $statement->bindValue('email', $this->normalizeEmail($email));
        $statement->bindValue('limit', max(1, $limit), PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }

    public function getUnreadCount(string $email): int
    {
        $this->ensureTables();
        if (trim($email) === '') {
            return 0;
        }

        $statement = $this->connectionFactory->getConnection()->prepare(
            'SELECT COUNT(*)
             FROM '.self::NOTIFICATIONS_TABLE.'
             WHERE LOWER(recipient_email) = LOWER(:email)
               AND is_read = 0'
        );
        $statement->execute([
            'email' => $this->normalizeEmail($email),
        ]);

        return (int) $statement->fetchColumn();
    }

    public function markAllAsRead(string $email): void
    {
        $this->ensureTables();
        if (trim($email) === '') {
            return;
        }

        $statement = $this->connectionFactory->getConnection()->prepare(
            'UPDATE '.self::NOTIFICATIONS_TABLE.'
             SET is_read = 1
             WHERE LOWER(recipient_email) = LOWER(:email)
               AND is_read = 0'
        );
        $statement->execute([
            'email' => $this->normalizeEmail($email),
        ]);
    }

    private function createNotification(
        string $recipientEmail,
        string $senderEmail,
        string $senderRole,
        string $category,
        string $title,
        string $message
    ): void {
        $this->ensureTables();
        $statement = $this->connectionFactory->getConnection()->prepare(
            'INSERT INTO '.self::NOTIFICATIONS_TABLE.' (
                recipient_email, sender_email, sender_role, category, title, message
            ) VALUES (
                :recipient_email, :sender_email, :sender_role, :category, :title, :message
            )'
        );

        $statement->execute([
            'recipient_email' => $this->normalizeEmail($recipientEmail),
            'sender_email' => trim($senderEmail) === '' ? null : $this->normalizeEmail($senderEmail),
            'sender_role' => trim($senderRole) === '' ? null : $this->normalizeRole($senderRole),
            'category' => $this->safeCategory($category),
            'title' => trim($title),
            'message' => trim($message),
        ]);
    }

    private function saveDefaultPreferences(string $email, string $role): void
    {
        $this->ensureTables();
        $statement = $this->connectionFactory->getConnection()->prepare(
            'INSERT INTO '.self::PREFERENCES_TABLE.' (
                user_email, user_role, notify_security, notify_booking, notify_forum, notify_offers
            ) VALUES (
                :user_email, :user_role, 1, 1, 1, 0
            ) ON DUPLICATE KEY UPDATE user_role = VALUES(user_role)'
        );

        $statement->execute([
            'user_email' => $this->normalizeEmail($email),
            'user_role' => $this->normalizeRole($role),
        ]);
    }

    private function ensureTables(): void
    {
        if ($this->tablesEnsured || !$this->isDatabaseAvailable()) {
            return;
        }

        $connection = $this->connectionFactory->getConnection();
        $connection->exec(
            'CREATE TABLE IF NOT EXISTS '.self::PREFERENCES_TABLE.' (
                id INT NOT NULL AUTO_INCREMENT,
                user_email VARCHAR(100) NOT NULL,
                user_role VARCHAR(20) NOT NULL,
                notify_security TINYINT(1) NOT NULL DEFAULT 1,
                notify_booking TINYINT(1) NOT NULL DEFAULT 1,
                notify_forum TINYINT(1) NOT NULL DEFAULT 1,
                notify_offers TINYINT(1) NOT NULL DEFAULT 0,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uk_user_notification_preferences_email (user_email)
            )'
        );

        $connection->exec(
            "CREATE TABLE IF NOT EXISTS ".self::NOTIFICATIONS_TABLE." (
                id INT NOT NULL AUTO_INCREMENT,
                recipient_email VARCHAR(100) NOT NULL,
                sender_email VARCHAR(100) DEFAULT NULL,
                sender_role VARCHAR(20) DEFAULT NULL,
                category VARCHAR(30) NOT NULL DEFAULT 'ACCOUNT',
                title VARCHAR(150) NOT NULL,
                message TEXT NOT NULL,
                is_read TINYINT(1) NOT NULL DEFAULT 0,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_user_notifications_recipient (recipient_email),
                KEY idx_user_notifications_read (is_read),
                KEY idx_user_notifications_created (created_at)
            )"
        );

        $this->tablesEnsured = true;
    }

    private function isCategoryAllowed(string $email, string $role, string $category): bool
    {
        $preferences = $this->getPreferences($email, $role);

        return match ($this->safeCategory($category)) {
            'BOOKING' => $preferences->booking(),
            'FORUM' => $preferences->forum(),
            'OFFERS' => $preferences->offers(),
            default => $preferences->security(),
        };
    }

    private function normalizeEmail(string $value): string
    {
        return strtolower(trim($value));
    }

    private function normalizeRole(string $value): string
    {
        $normalized = strtoupper(trim($value));

        return $normalized !== '' ? $normalized : 'USER';
    }

    private function safeCategory(string $value): string
    {
        $normalized = strtoupper(trim($value));

        return $normalized !== '' ? $normalized : 'ACCOUNT';
    }
}
