<?php

namespace App\Repository;

use App\Database\PdoConnectionFactory;
use PDO;

final class DestinationRepository
{
    private bool $schemaEnsured = false;

    public function __construct(private readonly PdoConnectionFactory $connectionFactory)
    {
    }

    public function count(): int
    {
        $this->ensureSchema();

        return (int) $this->connectionFactory->getConnection()
            ->query("SELECT COUNT(*) FROM destinations WHERE workflow_place <> 'archived'")
            ->fetchColumn();
    }

    public function findAll(): array
    {
        $this->ensureSchema();

        $statement = $this->connectionFactory->getConnection()->query(
            "SELECT * FROM destinations
             WHERE workflow_place = 'published'
             ORDER BY catalog_priority DESC, quality_score DESC, id DESC"
        );

        return array_map($this->hydrateDestinationRow(...), $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    public function findAllForManagement(): array
    {
        $this->ensureSchema();

        $statement = $this->connectionFactory->getConnection()->query(
            "SELECT * FROM destinations
             ORDER BY
                CASE workflow_place
                    WHEN 'in_review' THEN 0
                    WHEN 'draft' THEN 1
                    WHEN 'rejected' THEN 2
                    WHEN 'published' THEN 3
                    WHEN 'archived' THEN 4
                    ELSE 5
                END,
                catalog_priority DESC,
                updated_at DESC,
                id DESC"
        );

        return array_map($this->hydrateDestinationRow(...), $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    public function findLatest(int $limit = 3): array
    {
        $this->ensureSchema();

        $statement = $this->connectionFactory->getConnection()->prepare(
            "SELECT * FROM destinations
             WHERE workflow_place = 'published'
             ORDER BY catalog_priority DESC, quality_score DESC, id DESC
             LIMIT :limit"
        );
        $statement->bindValue('limit', max(1, $limit), PDO::PARAM_INT);
        $statement->execute();

        return array_map($this->hydrateDestinationRow(...), $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    public function findForSelect(): array
    {
        $this->ensureSchema();

        $statement = $this->connectionFactory->getConnection()->query(
            "SELECT id, nom, pays, workflow_place
             FROM destinations
             WHERE workflow_place <> 'archived'
             ORDER BY nom ASC, pays ASC"
        );

        return array_map($this->hydrateDestinationRow(...), $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    public function find(int $id): ?array
    {
        $this->ensureSchema();

        $statement = $this->connectionFactory->getConnection()->prepare(
            'SELECT * FROM destinations WHERE id = :id'
        );
        $statement->execute(['id' => $id]);

        $destination = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($destination) ? $this->hydrateDestinationRow($destination) : null;
    }

    public function create(array $payload): int
    {
        $this->ensureSchema();

        $normalized = $this->normalizePayload($payload);
        $statement = $this->connectionFactory->getConnection()->prepare(
            'INSERT INTO destinations (
                nom, slug, pays, continent, prix_base, description,
                workflow_place, cover_image_path, hero_video_path, travel_mood,
                best_period, duration_days, max_travelers, interest_tags,
                audience_tags, catalog_priority, quality_score
            ) VALUES (
                :nom, :slug, :pays, :continent, :prix_base, :description,
                :workflow_place, :cover_image_path, :hero_video_path, :travel_mood,
                :best_period, :duration_days, :max_travelers, :interest_tags,
                :audience_tags, :catalog_priority, :quality_score
            )'
        );

        $statement->execute($normalized);

        return (int) $this->connectionFactory->getConnection()->lastInsertId();
    }

    public function update(int $id, array $payload): bool
    {
        $this->ensureSchema();

        $normalized = $this->normalizePayload($payload, (string) ($payload['workflow_place'] ?? 'published'));
        $statement = $this->connectionFactory->getConnection()->prepare(
            'UPDATE destinations
             SET nom = :nom,
                 slug = :slug,
                 pays = :pays,
                 continent = :continent,
                 prix_base = :prix_base,
                 description = :description,
                 cover_image_path = :cover_image_path,
                 hero_video_path = :hero_video_path,
                 travel_mood = :travel_mood,
                 best_period = :best_period,
                 duration_days = :duration_days,
                 max_travelers = :max_travelers,
                 interest_tags = :interest_tags,
                 audience_tags = :audience_tags,
                 catalog_priority = :catalog_priority,
                 quality_score = :quality_score
             WHERE id = :id'
        );

        return $statement->execute([
            ...$normalized,
            'id' => $id,
        ]);
    }

    public function updateWorkflowPlace(int $id, string $workflowPlace): bool
    {
        $this->ensureSchema();

        $workflowPlace = $this->normalizeWorkflowPlace($workflowPlace);
        if ($workflowPlace === 'published') {
            $statement = $this->connectionFactory->getConnection()->prepare(
                "UPDATE destinations
                 SET workflow_place = :workflow_place,
                     published_at = COALESCE(published_at, NOW())
                 WHERE id = :id"
            );

            return $statement->execute([
                'id' => $id,
                'workflow_place' => $workflowPlace,
            ]);
        }

        $statement = $this->connectionFactory->getConnection()->prepare(
            'UPDATE destinations SET workflow_place = :workflow_place WHERE id = :id'
        );

        return $statement->execute([
            'id' => $id,
            'workflow_place' => $workflowPlace,
        ]);
    }

    public function delete(int $id): void
    {
        $this->ensureSchema();

        $statement = $this->connectionFactory->getConnection()->prepare(
            'DELETE FROM destinations WHERE id = :id'
        );

        $statement->execute(['id' => $id]);
    }

    private function ensureSchema(): void
    {
        if ($this->schemaEnsured) {
            return;
        }

        $connection = $this->connectionFactory->getConnection();
        $connection->exec(
            'CREATE TABLE IF NOT EXISTS destinations (
                id INT NOT NULL AUTO_INCREMENT,
                nom VARCHAR(100) NOT NULL,
                pays VARCHAR(100) DEFAULT NULL,
                continent VARCHAR(50) DEFAULT NULL,
                prix_base DECIMAL(10,2) DEFAULT 0,
                description TEXT DEFAULT NULL,
                PRIMARY KEY (id)
            )'
        );

        $this->addColumnIfMissing($connection, 'slug', 'ALTER TABLE destinations ADD COLUMN slug VARCHAR(160) NULL AFTER nom');
        $this->addColumnIfMissing($connection, 'workflow_place', "ALTER TABLE destinations ADD COLUMN workflow_place VARCHAR(30) NOT NULL DEFAULT 'published' AFTER description");
        $this->addColumnIfMissing($connection, 'cover_image_path', 'ALTER TABLE destinations ADD COLUMN cover_image_path VARCHAR(255) NULL AFTER workflow_place');
        $this->addColumnIfMissing($connection, 'hero_video_path', 'ALTER TABLE destinations ADD COLUMN hero_video_path VARCHAR(255) NULL AFTER cover_image_path');
        $this->addColumnIfMissing($connection, 'travel_mood', 'ALTER TABLE destinations ADD COLUMN travel_mood VARCHAR(60) NULL AFTER hero_video_path');
        $this->addColumnIfMissing($connection, 'best_period', 'ALTER TABLE destinations ADD COLUMN best_period VARCHAR(120) NULL AFTER travel_mood');
        $this->addColumnIfMissing($connection, 'duration_days', 'ALTER TABLE destinations ADD COLUMN duration_days INT NOT NULL DEFAULT 7 AFTER best_period');
        $this->addColumnIfMissing($connection, 'max_travelers', 'ALTER TABLE destinations ADD COLUMN max_travelers INT NOT NULL DEFAULT 4 AFTER duration_days');
        $this->addColumnIfMissing($connection, 'interest_tags', 'ALTER TABLE destinations ADD COLUMN interest_tags TEXT NULL AFTER max_travelers');
        $this->addColumnIfMissing($connection, 'audience_tags', 'ALTER TABLE destinations ADD COLUMN audience_tags TEXT NULL AFTER interest_tags');
        $this->addColumnIfMissing($connection, 'catalog_priority', 'ALTER TABLE destinations ADD COLUMN catalog_priority INT NOT NULL DEFAULT 50 AFTER audience_tags');
        $this->addColumnIfMissing($connection, 'quality_score', 'ALTER TABLE destinations ADD COLUMN quality_score DECIMAL(5,2) NOT NULL DEFAULT 0 AFTER catalog_priority');
        $this->addColumnIfMissing($connection, 'created_at', 'ALTER TABLE destinations ADD COLUMN created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER quality_score');
        $this->addColumnIfMissing($connection, 'updated_at', 'ALTER TABLE destinations ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at');
        $this->addColumnIfMissing($connection, 'published_at', 'ALTER TABLE destinations ADD COLUMN published_at TIMESTAMP NULL DEFAULT NULL AFTER updated_at');

        $this->backfillAdvancedColumns($connection);
        $this->schemaEnsured = true;
    }

    private function addColumnIfMissing(object $connection, string $columnName, string $sql): void
    {
        $statement = $connection->prepare('SHOW COLUMNS FROM destinations LIKE :column_name');
        $statement->execute(['column_name' => $columnName]);

        if ($statement->fetch(PDO::FETCH_ASSOC)) {
            return;
        }

        $connection->exec($sql);
    }

    private function backfillAdvancedColumns(object $connection): void
    {
        $rows = $connection->query(
            'SELECT id, nom, pays, continent, prix_base, description, slug, workflow_place, travel_mood,
                    best_period, duration_days, max_travelers, interest_tags, audience_tags, catalog_priority,
                    quality_score, published_at
             FROM destinations'
        )->fetchAll(PDO::FETCH_ASSOC);

        $update = $connection->prepare(
            'UPDATE destinations
             SET slug = :slug,
                 workflow_place = :workflow_place,
                 travel_mood = :travel_mood,
                 best_period = :best_period,
                 duration_days = :duration_days,
                 max_travelers = :max_travelers,
                 interest_tags = :interest_tags,
                 audience_tags = :audience_tags,
                 catalog_priority = :catalog_priority,
                 quality_score = :quality_score
             WHERE id = :id'
        );

        foreach ($rows as $row) {
            $normalized = $this->normalizePayload($row, (string) ($row['workflow_place'] ?? 'published'));
            $update->execute([
                'id' => (int) ($row['id'] ?? 0),
                'slug' => $normalized['slug'],
                'workflow_place' => $normalized['workflow_place'],
                'travel_mood' => $normalized['travel_mood'],
                'best_period' => $normalized['best_period'],
                'duration_days' => $normalized['duration_days'],
                'max_travelers' => $normalized['max_travelers'],
                'interest_tags' => $normalized['interest_tags'],
                'audience_tags' => $normalized['audience_tags'],
                'catalog_priority' => $normalized['catalog_priority'],
                'quality_score' => $normalized['quality_score'],
            ]);
        }

        $connection->exec(
            "UPDATE destinations
             SET published_at = COALESCE(published_at, NOW())
             WHERE workflow_place = 'published' AND published_at IS NULL"
        );
    }

    private function hydrateDestinationRow(array $destination): array
    {
        $workflowPlace = $this->normalizeWorkflowPlace((string) ($destination['workflow_place'] ?? 'published'));
        $interestTags = $this->parseTagString((string) ($destination['interest_tags'] ?? ''));
        $audienceTags = $this->parseTagString((string) ($destination['audience_tags'] ?? ''));
        $priceBase = max(0.0, (float) ($destination['prix_base'] ?? 0.0));

        return [
            ...$destination,
            'id' => (int) ($destination['id'] ?? 0),
            'slug' => trim((string) ($destination['slug'] ?? '')),
            'workflow_place' => $workflowPlace,
            'cover_image_path' => trim((string) ($destination['cover_image_path'] ?? '')),
            'hero_video_path' => trim((string) ($destination['hero_video_path'] ?? '')),
            'travel_mood' => trim((string) ($destination['travel_mood'] ?? '')),
            'best_period' => trim((string) ($destination['best_period'] ?? '')),
            'duration_days' => max(1, (int) ($destination['duration_days'] ?? 7)),
            'max_travelers' => max(1, (int) ($destination['max_travelers'] ?? 4)),
            'interest_tags' => implode(', ', $interestTags),
            'interest_tags_list' => $interestTags,
            'audience_tags' => implode(', ', $audienceTags),
            'audience_tags_list' => $audienceTags,
            'catalog_priority' => max(0, (int) ($destination['catalog_priority'] ?? 50)),
            'quality_score' => round((float) ($destination['quality_score'] ?? 0), 1),
            'prix_base' => $priceBase,
        ];
    }

    private function normalizePayload(array $payload, ?string $workflowPlace = null): array
    {
        $name = trim((string) ($payload['nom'] ?? ''));
        $country = trim((string) ($payload['pays'] ?? ''));
        $continent = trim((string) ($payload['continent'] ?? ''));
        $description = trim((string) ($payload['description'] ?? ''));
        $priceBase = max(0.0, (float) ($payload['prix_base'] ?? 0.0));
        $travelMood = trim((string) ($payload['travel_mood'] ?? ''));
        if ($travelMood === '') {
            $travelMood = $this->inferTravelMood($name, $country, $continent, $description);
        }

        $bestPeriod = trim((string) ($payload['best_period'] ?? ''));
        if ($bestPeriod === '') {
            $bestPeriod = $this->inferBestPeriod($continent);
        }

        $durationDays = max(1, (int) ($payload['duration_days'] ?? 0));
        if ($durationDays <= 1 && !isset($payload['duration_days'])) {
            $durationDays = $this->inferDurationDays($name, $country, $continent, $priceBase);
        }

        $interestTags = $this->normalizeTagString($payload['interest_tags'] ?? $payload['interest_tags_list'] ?? []);
        if ($interestTags === '') {
            $interestTags = implode(', ', $this->inferInterestTags($travelMood, $continent));
        }

        $audienceTags = $this->normalizeTagString($payload['audience_tags'] ?? $payload['audience_tags_list'] ?? []);
        if ($audienceTags === '') {
            $audienceTags = implode(', ', $this->inferAudienceTags($travelMood));
        }

        $maxTravelers = max(1, (int) ($payload['max_travelers'] ?? 0));
        if ($maxTravelers <= 1 && !isset($payload['max_travelers'])) {
            $maxTravelers = $this->inferMaxTravelers($audienceTags, $travelMood);
        }

        $catalogPriority = max(0, (int) ($payload['catalog_priority'] ?? 50));
        $coverImagePath = $this->normalizeMediaPath((string) ($payload['cover_image_path'] ?? ''));
        $heroVideoPath = $this->normalizeMediaPath((string) ($payload['hero_video_path'] ?? ''));
        $workflowPlace = $this->normalizeWorkflowPlace($workflowPlace ?? (string) ($payload['workflow_place'] ?? 'draft'));

        $slug = trim((string) ($payload['slug'] ?? ''));
        if ($slug === '') {
            $slug = $this->slugify($name.($country !== '' ? '-'.$country : ''));
        }

        return [
            'nom' => $name,
            'slug' => $slug,
            'pays' => $country,
            'continent' => $continent,
            'prix_base' => $priceBase,
            'description' => $description,
            'workflow_place' => $workflowPlace,
            'cover_image_path' => $coverImagePath,
            'hero_video_path' => $heroVideoPath,
            'travel_mood' => $travelMood,
            'best_period' => $bestPeriod,
            'duration_days' => max(1, $durationDays),
            'max_travelers' => max(1, $maxTravelers),
            'interest_tags' => $interestTags,
            'audience_tags' => $audienceTags,
            'catalog_priority' => $catalogPriority,
            'quality_score' => $this->computeQualityScore([
                'description' => $description,
                'cover_image_path' => $coverImagePath,
                'hero_video_path' => $heroVideoPath,
                'interest_tags' => $interestTags,
                'audience_tags' => $audienceTags,
                'prix_base' => $priceBase,
                'workflow_place' => $workflowPlace,
            ]),
        ];
    }

    private function normalizeWorkflowPlace(string $workflowPlace): string
    {
        $workflowPlace = trim($workflowPlace);

        return in_array($workflowPlace, ['draft', 'in_review', 'rejected', 'published', 'archived'], true)
            ? $workflowPlace
            : 'draft';
    }

    private function normalizeTagString(mixed $value): string
    {
        if (is_array($value)) {
            $items = $value;
        } else {
            $items = preg_split('/[,|]/', (string) $value) ?: [];
        }

        $normalized = [];
        foreach ($items as $item) {
            $item = trim((string) $item);
            if ($item === '') {
                continue;
            }
            $normalized[$this->slugify($item)] = $item;
        }

        return implode(', ', array_values($normalized));
    }

    private function parseTagString(string $value): array
    {
        $parts = preg_split('/[,|]/', $value) ?: [];

        return array_values(array_filter(array_map(
            static fn (string $item): string => trim($item),
            $parts
        )));
    }

    private function normalizeMediaPath(string $value): string
    {
        $value = trim(str_replace("\0", '', $value));

        return mb_substr($value, 0, 255);
    }

    private function computeQualityScore(array $payload): float
    {
        $score = 38.0;
        $descriptionLength = mb_strlen(trim((string) ($payload['description'] ?? '')));
        $score += min(22.0, $descriptionLength / 6.0);
        $score += trim((string) ($payload['cover_image_path'] ?? '')) !== '' ? 12.0 : 0.0;
        $score += trim((string) ($payload['hero_video_path'] ?? '')) !== '' ? 9.0 : 0.0;
        $score += max(0, count($this->parseTagString((string) ($payload['interest_tags'] ?? ''))) * 4);
        $score += max(0, count($this->parseTagString((string) ($payload['audience_tags'] ?? ''))) * 2);
        $score += (float) ($payload['prix_base'] ?? 0) > 0 ? 10.0 : 0.0;
        $score += ($payload['workflow_place'] ?? 'draft') === 'published' ? 7.0 : 0.0;

        return round(max(0.0, min(100.0, $score)), 1);
    }

    private function inferTravelMood(string $name, string $country, string $continent, string $description): string
    {
        $normalized = $this->slugify($name.' '.$country.' '.$continent.' '.$description);

        if (
            str_contains($normalized, 'plage')
            || str_contains($normalized, 'bali')
            || str_contains($normalized, 'maldives')
            || str_contains($normalized, 'santorini')
        ) {
            return 'Plage';
        }

        if (
            str_contains($normalized, 'safari')
            || str_contains($normalized, 'desert')
            || str_contains($normalized, 'kenya')
            || str_contains($normalized, 'maroc')
        ) {
            return 'Aventure';
        }

        if (
            str_contains($normalized, 'tokyo')
            || str_contains($normalized, 'paris')
            || str_contains($normalized, 'berlin')
            || str_contains($normalized, 'toronto')
        ) {
            return 'City Trip';
        }

        return match ($this->slugify($continent)) {
            'asie' => 'Plage',
            'afrique' => 'Aventure',
            'amerique' => 'City Trip',
            default => 'Culture',
        };
    }

    private function inferBestPeriod(string $continent): string
    {
        return match ($this->slugify($continent)) {
            'europe' => 'Avril a octobre',
            'asie' => 'Mars a mai',
            'afrique' => 'Juin a octobre',
            'oceanie' => 'Octobre a mars',
            'amerique' => 'Novembre a avril',
            default => 'Toute l annee',
        };
    }

    private function inferDurationDays(string $name, string $country, string $continent, float $priceBase): int
    {
        $normalized = $this->slugify($name.' '.$country.' '.$continent);

        if (str_contains($normalized, 'tokyo') || str_contains($normalized, 'japon')) {
            return 9;
        }
        if (str_contains($normalized, 'bali')) {
            return 8;
        }
        if (str_contains($normalized, 'maldives')) {
            return 10;
        }
        if (str_contains($normalized, 'santorini')) {
            return 7;
        }
        if ($priceBase >= 8000) {
            return 10;
        }
        if ($priceBase >= 3000) {
            return 8;
        }

        return $this->slugify($continent) === 'europe' ? 5 : 7;
    }

    private function inferInterestTags(string $travelMood, string $continent): array
    {
        $tags = match ($travelMood) {
            'Plage' => ['Plage', 'Detente', 'Nature'],
            'Aventure' => ['Aventure', 'Nature', 'Culture'],
            'City Trip' => ['Shopping', 'Gastronomie', 'Culture'],
            default => ['Culture', 'Nature', 'Detente'],
        };

        if ($this->slugify($continent) === 'afrique') {
            $tags[] = 'Safari';
        }

        return array_values(array_unique($tags));
    }

    private function inferAudienceTags(string $travelMood): array
    {
        return match ($travelMood) {
            'Plage' => ['Couple', 'Famille'],
            'City Trip' => ['Business', 'Solo', 'Couple'],
            'Aventure' => ['Solo', 'Couple', 'Famille'],
            default => ['Famille', 'Couple', 'Solo'],
        };
    }

    private function inferMaxTravelers(string $audienceTags, string $travelMood): int
    {
        $tags = array_map($this->slugify(...), $this->parseTagString($audienceTags));

        if (in_array('famille', $tags, true)) {
            return $travelMood === 'Plage' ? 5 : 6;
        }
        if (in_array('business', $tags, true)) {
            return 3;
        }

        return 2;
    }

    private function slugify(string $value): string
    {
        $value = trim($value);
        $transliterated = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if (is_string($transliterated) && $transliterated !== '') {
            $value = $transliterated;
        }

        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? $value;
        $value = trim($value, '-');

        return $value !== '' ? $value : 'destination';
    }
}
