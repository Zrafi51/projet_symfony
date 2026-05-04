<?php

namespace App\Repository;

use App\Database\PdoConnectionFactory;
use PDO;

final class FavoriteRepository
{
    private const TABLE_NAME = 'travel_favorites';

    private bool $schemaEnsured = false;

    public function __construct(private readonly PdoConnectionFactory $connectionFactory)
    {
    }

    public function findByUser(int $userId): array
    {
        $this->ensureSchema();

        $statement = $this->connectionFactory->getConnection()->prepare(
            'SELECT * FROM '.self::TABLE_NAME.'
             WHERE user_id = :user_id
             ORDER BY created_at DESC, id DESC'
        );
        $statement->execute(['user_id' => $userId]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findKeysByUser(int $userId): array
    {
        $this->ensureSchema();

        $statement = $this->connectionFactory->getConnection()->prepare(
            'SELECT favorite_key FROM '.self::TABLE_NAME.' WHERE user_id = :user_id'
        );
        $statement->execute(['user_id' => $userId]);

        return array_values(array_filter(array_map(
            static fn (mixed $value): string => (string) $value,
            $statement->fetchAll(PDO::FETCH_COLUMN)
        )));
    }

    public function countByUser(int $userId): int
    {
        $this->ensureSchema();

        $statement = $this->connectionFactory->getConnection()->prepare(
            'SELECT COUNT(*) FROM '.self::TABLE_NAME.' WHERE user_id = :user_id'
        );
        $statement->execute(['user_id' => $userId]);

        return (int) $statement->fetchColumn();
    }

    public function toggleFavorite(int $userId, array $payload): array
    {
        $this->ensureSchema();

        $connection = $this->connectionFactory->getConnection();
        $favoriteKey = $this->buildFavoriteKey($payload);

        $existing = $connection->prepare(
            'SELECT id FROM '.self::TABLE_NAME.'
             WHERE user_id = :user_id AND favorite_key = :favorite_key
             LIMIT 1'
        );
        $existing->execute([
            'user_id' => $userId,
            'favorite_key' => $favoriteKey,
        ]);

        $existingId = (int) ($existing->fetchColumn() ?: 0);
        if ($existingId > 0) {
            $delete = $connection->prepare(
                'DELETE FROM '.self::TABLE_NAME.' WHERE id = :id AND user_id = :user_id'
            );
            $delete->execute([
                'id' => $existingId,
                'user_id' => $userId,
            ]);

            return [
                'favorite_key' => $favoriteKey,
                'is_favorite' => false,
                'count' => $this->countByUser($userId),
            ];
        }

        $normalized = $this->normalizePayload($payload, $favoriteKey);
        $insert = $connection->prepare(
            'INSERT INTO '.self::TABLE_NAME.' (
                user_id, favorite_key, destination_id, destination_name, country,
                continent, image_path, description, duration_label, price_amount,
                price_currency, source, destination_url
            ) VALUES (
                :user_id, :favorite_key, :destination_id, :destination_name, :country,
                :continent, :image_path, :description, :duration_label, :price_amount,
                :price_currency, :source, :destination_url
            )'
        );
        $insert->execute([
            'user_id' => $userId,
            ...$normalized,
        ]);

        return [
            'favorite_key' => $favoriteKey,
            'is_favorite' => true,
            'count' => $this->countByUser($userId),
        ];
    }

    public function buildFavoriteKey(array $payload): string
    {
        $provided = trim((string) ($payload['favorite_key'] ?? ''));
        if ($provided !== '') {
            return preg_replace('/[^a-zA-Z0-9:_-]+/', '-', $provided) ?: $provided;
        }

        $source = strtolower(trim((string) ($payload['source'] ?? 'database')));
        $destinationId = (int) ($payload['destination_id'] ?? $payload['package_id'] ?? $payload['id'] ?? 0);
        if ($source !== 'flask' && $destinationId > 0) {
            return 'db-'.$destinationId;
        }

        $name = $this->slug((string) ($payload['destination_name'] ?? $payload['name'] ?? $payload['destination'] ?? 'destination'));
        $country = $this->slug((string) ($payload['country'] ?? $payload['pays'] ?? 'voyage'));

        return ($source === 'flask' ? 'flask' : 'trip').'-'.$name.'-'.$country;
    }

    private function normalizePayload(array $payload, string $favoriteKey): array
    {
        $destinationId = (int) ($payload['destination_id'] ?? $payload['package_id'] ?? $payload['id'] ?? 0);
        $source = strtolower(trim((string) ($payload['source'] ?? 'database')));
        $priceCurrency = strtoupper(trim((string) ($payload['price_currency'] ?? 'TND')));
        if (!in_array($priceCurrency, ['TND', 'EUR', 'USD'], true)) {
            $priceCurrency = 'TND';
        }

        return [
            'favorite_key' => $favoriteKey,
            'destination_id' => $destinationId > 0 ? $destinationId : null,
            'destination_name' => mb_substr(trim((string) ($payload['destination_name'] ?? $payload['name'] ?? $payload['destination'] ?? 'Voyage EasyTravel')), 0, 160),
            'country' => mb_substr(trim((string) ($payload['country'] ?? $payload['pays'] ?? '')), 0, 120),
            'continent' => mb_substr(trim((string) ($payload['continent'] ?? '')), 0, 80),
            'image_path' => mb_substr(trim((string) ($payload['image_path'] ?? $payload['image'] ?? '')), 0, 255),
            'description' => trim((string) ($payload['description'] ?? '')),
            'duration_label' => mb_substr(trim((string) ($payload['duration_label'] ?? $payload['duration'] ?? '')), 0, 80),
            'price_amount' => max(0.0, (float) ($payload['price_amount'] ?? $payload['price'] ?? 0)),
            'price_currency' => $priceCurrency,
            'source' => mb_substr($source !== '' ? $source : 'database', 0, 40),
            'destination_url' => mb_substr(trim((string) ($payload['destination_url'] ?? '/destinations')), 0, 255),
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
                id INT NOT NULL AUTO_INCREMENT,
                user_id INT NOT NULL,
                favorite_key VARCHAR(180) NOT NULL,
                destination_id INT DEFAULT NULL,
                destination_name VARCHAR(160) NOT NULL,
                country VARCHAR(120) DEFAULT NULL,
                continent VARCHAR(80) DEFAULT NULL,
                image_path VARCHAR(255) DEFAULT NULL,
                description TEXT DEFAULT NULL,
                duration_label VARCHAR(80) DEFAULT NULL,
                price_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
                price_currency VARCHAR(3) NOT NULL DEFAULT \'TND\',
                source VARCHAR(40) NOT NULL DEFAULT \'database\',
                destination_url VARCHAR(255) NOT NULL DEFAULT \'/destinations\',
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uk_travel_favorites_user_key (user_id, favorite_key),
                KEY idx_travel_favorites_user (user_id),
                KEY idx_travel_favorites_created (created_at)
            )'
        );

        $this->schemaEnsured = true;
    }

    private function slug(string $value): string
    {
        $value = trim($value);
        $transliterated = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if (is_string($transliterated) && $transliterated !== '') {
            $value = $transliterated;
        }

        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? $value;
        $value = trim($value, '-');

        return $value !== '' ? $value : 'voyage';
    }
}
