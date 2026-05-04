<?php

namespace App\Service;

use App\Database\PdoConnectionFactory;
use RuntimeException;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class LegacyRememberMeService
{
    private const TABLE_NAME = 'user_remember_me';
    private const ENCRYPTION_CONTEXT = 'EasyTravelRememberMe';

    private bool $tableEnsured = false;

    public function __construct(
        private readonly PdoConnectionFactory $connectionFactory,
        private readonly string $secret,
        private readonly string $cookieName,
        private readonly int $durationDays,
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

    public function loadRememberedCredentials(Request $request): ?array
    {
        if (!$this->isDatabaseAvailable()) {
            return null;
        }

        $this->ensureTable();
        $deviceKey = trim((string) $request->cookies->get($this->cookieName, ''));
        if ($deviceKey === '') {
            return null;
        }

        $statement = $this->connectionFactory->getConnection()->prepare(
            'SELECT user_email, encrypted_password FROM '.self::TABLE_NAME.' WHERE device_key = :device_key LIMIT 1'
        );
        $statement->execute([
            'device_key' => $deviceKey,
        ]);
        $row = $statement->fetch();

        if (!$row) {
            return null;
        }

        $password = $this->decryptPassword((string) $row['encrypted_password'], $deviceKey);
        if ($password === '') {
            return null;
        }

        return [
            'email' => (string) ($row['user_email'] ?? ''),
            'password' => $password,
        ];
    }

    public function getRememberedIdentity(Request $request): ?array
    {
        if (!$this->isDatabaseAvailable()) {
            return null;
        }

        $this->ensureTable();
        $deviceKey = trim((string) $request->cookies->get($this->cookieName, ''));
        if ($deviceKey === '') {
            return null;
        }

        $statement = $this->connectionFactory->getConnection()->prepare(
            'SELECT user_email, user_role FROM '.self::TABLE_NAME.' WHERE device_key = :device_key LIMIT 1'
        );
        $statement->execute([
            'device_key' => $deviceKey,
        ]);
        $row = $statement->fetch();

        if (!$row) {
            return null;
        }

        return [
            'email' => (string) ($row['user_email'] ?? ''),
            'role' => (string) ($row['user_role'] ?? 'USER'),
        ];
    }

    public function hasRememberedDevice(Request $request, ?string $expectedEmail = null): bool
    {
        $rememberedIdentity = $this->getRememberedIdentity($request);
        if ($rememberedIdentity === null) {
            return false;
        }

        $expectedEmail = strtolower(trim((string) $expectedEmail));
        if ($expectedEmail === '') {
            return true;
        }

        return strtolower(trim((string) ($rememberedIdentity['email'] ?? ''))) === $expectedEmail;
    }

    public function remember(Request $request, Response $response, array $user, string $plainPassword): void
    {
        if (!$this->isDatabaseAvailable()) {
            return;
        }

        $this->ensureTable();
        $deviceKey = trim((string) $request->cookies->get($this->cookieName, ''));
        if ($deviceKey === '') {
            $deviceKey = bin2hex(random_bytes(32));
        }

        $statement = $this->connectionFactory->getConnection()->prepare(
            'INSERT INTO '.self::TABLE_NAME.' (device_key, user_email, encrypted_password, user_role)
             VALUES (:device_key, :user_email, :encrypted_password, :user_role)
             ON DUPLICATE KEY UPDATE
                user_email = VALUES(user_email),
                encrypted_password = VALUES(encrypted_password),
                user_role = VALUES(user_role),
                updated_at = CURRENT_TIMESTAMP'
        );

        $statement->execute([
            'device_key' => $deviceKey,
            'user_email' => strtolower(trim((string) ($user['email'] ?? ''))),
            'encrypted_password' => $this->encryptPassword($plainPassword, $deviceKey),
            'user_role' => strtoupper(trim((string) ($user['role'] ?? 'USER'))) ?: 'USER',
        ]);

        $response->headers->setCookie($this->buildCookie($deviceKey));
    }

    public function clear(Request $request, Response $response): void
    {
        $deviceKey = trim((string) $request->cookies->get($this->cookieName, ''));
        if ($deviceKey !== '' && $this->isDatabaseAvailable()) {
            $this->ensureTable();
            $statement = $this->connectionFactory->getConnection()->prepare(
                'DELETE FROM '.self::TABLE_NAME.' WHERE device_key = :device_key'
            );
            $statement->execute([
                'device_key' => $deviceKey,
            ]);
        }

        $response->headers->clearCookie($this->cookieName, '/');
    }

    public function updateRememberedIdentity(Request $request, string $previousEmail, array $user): void
    {
        if (!$this->isDatabaseAvailable()) {
            return;
        }

        $deviceKey = trim((string) $request->cookies->get($this->cookieName, ''));
        $newEmail = strtolower(trim((string) ($user['email'] ?? '')));
        $newRole = strtoupper(trim((string) ($user['role'] ?? 'USER'))) ?: 'USER';
        if ($deviceKey === '' || trim($previousEmail) === '' || $newEmail === '') {
            return;
        }

        $this->ensureTable();
        $statement = $this->connectionFactory->getConnection()->prepare(
            'UPDATE '.self::TABLE_NAME.'
             SET user_email = :user_email,
                 user_role = :user_role,
                 updated_at = CURRENT_TIMESTAMP
             WHERE device_key = :device_key AND LOWER(user_email) = LOWER(:previous_email)'
        );
        $statement->execute([
            'user_email' => $newEmail,
            'user_role' => $newRole,
            'device_key' => $deviceKey,
            'previous_email' => trim($previousEmail),
        ]);
    }

    public function updateRememberedPassword(Request $request, string $email, string $plainPassword): void
    {
        if (!$this->isDatabaseAvailable()) {
            return;
        }

        $deviceKey = trim((string) $request->cookies->get($this->cookieName, ''));
        $email = strtolower(trim($email));
        if ($deviceKey === '' || $email === '' || trim($plainPassword) === '') {
            return;
        }

        $encryptedPassword = $this->encryptPassword($plainPassword, $deviceKey);
        if ($encryptedPassword === '') {
            return;
        }

        $this->ensureTable();
        $statement = $this->connectionFactory->getConnection()->prepare(
            'UPDATE '.self::TABLE_NAME.'
             SET encrypted_password = :encrypted_password,
                 updated_at = CURRENT_TIMESTAMP
             WHERE device_key = :device_key AND LOWER(user_email) = LOWER(:email)'
        );
        $statement->execute([
            'encrypted_password' => $encryptedPassword,
            'device_key' => $deviceKey,
            'email' => $email,
        ]);
    }

    private function ensureTable(): void
    {
        if ($this->tableEnsured) {
            return;
        }

        $this->connectionFactory->getConnection()->exec(
            "CREATE TABLE IF NOT EXISTS ".self::TABLE_NAME." (
                id INT NOT NULL AUTO_INCREMENT,
                device_key VARCHAR(120) NOT NULL,
                user_email VARCHAR(100) NOT NULL,
                encrypted_password TEXT NOT NULL,
                user_role VARCHAR(20) NOT NULL DEFAULT 'USER',
                remembered_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uk_user_remember_me_device (device_key),
                KEY idx_user_remember_me_email (user_email)
            )"
        );

        $this->tableEnsured = true;
    }

    private function buildCookie(string $deviceKey): Cookie
    {
        return Cookie::create(
            $this->cookieName,
            $deviceKey,
            new \DateTimeImmutable('+'.$this->durationDays.' days'),
            '/',
            null,
            false,
            true,
            false,
            Cookie::SAMESITE_LAX
        );
    }

    private function encryptPassword(string $password, string $deviceKey): string
    {
        $iv = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt(
            $password,
            'aes-256-gcm',
            $this->buildSecretKey($deviceKey),
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if (!is_string($ciphertext) || $ciphertext === '') {
            return '';
        }

        return base64_encode($iv.$tag.$ciphertext);
    }

    private function decryptPassword(string $payload, string $deviceKey): string
    {
        $decoded = base64_decode($payload, true);
        if ($decoded === false || strlen($decoded) <= 28) {
            return '';
        }

        $iv = substr($decoded, 0, 12);
        $tag = substr($decoded, 12, 16);
        $ciphertext = substr($decoded, 28);

        $plaintext = openssl_decrypt(
            $ciphertext,
            'aes-256-gcm',
            $this->buildSecretKey($deviceKey),
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        return is_string($plaintext) ? $plaintext : '';
    }

    private function buildSecretKey(string $deviceKey): string
    {
        return hash('sha256', self::ENCRYPTION_CONTEXT.'|'.$this->secret.'|'.$deviceKey, true);
    }
}
