<?php

namespace App\Repository;

use App\Database\PdoConnectionFactory;
use App\Entity\LocationPin;

class LocationPinRepository
{
    public function __construct(private PdoConnectionFactory $pdoFactory) {}

    private function pdo(): \PDO
    {
        return $this->pdoFactory->getConnection();
    }

    private function hydrate(array $row): LocationPin
    {
        $p = (new LocationPin())
            ->setUsername((string) $row['username'])
            ->setLatitude((float) $row['latitude'])
            ->setLongitude((float) $row['longitude'])
            ->setLabel($row['label'] ?? null);
        $p->setId((int) $row['id']);
        if (!empty($row['expires_at'])) {
            try { $p->setExpiresAt(new \DateTime((string) $row['expires_at'])); } catch (\Exception) {}
        }
        if (!empty($row['created_at'])) {
            try { $p->setCreatedAt(new \DateTime((string) $row['created_at'])); } catch (\Exception) {}
        }
        return $p;
    }

    public function findActiveFor(string $username): ?LocationPin
    {
        $stmt = $this->pdo()->prepare('SELECT * FROM location_pins WHERE username = :u LIMIT 1');
        $stmt->execute(['u' => $username]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) return null;
        $pin = $this->hydrate($row);
        return $pin->isExpired() ? null : $pin;
    }

    public function upsertFor(string $username, float $lat, float $lng, \DateTimeInterface $expiresAt, ?string $label = null): LocationPin
    {
        $pdo = $this->pdo();
        $stmt = $pdo->prepare('SELECT * FROM location_pins WHERE username = :u LIMIT 1');
        $stmt->execute(['u' => $username]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($row) {
            $stmt = $pdo->prepare(
                'UPDATE location_pins SET latitude = :lat, longitude = :lng, label = :lbl, expires_at = :exp WHERE id = :id'
            );
            $stmt->execute([
                'id' => $row['id'],
                'lat' => $lat,
                'lng' => $lng,
                'lbl' => $label,
                'exp' => $expiresAt->format('Y-m-d H:i:s'),
            ]);
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO location_pins (username, latitude, longitude, label, expires_at, created_at)
                 VALUES (:u, :lat, :lng, :lbl, :exp, :ca)'
            );
            $stmt->execute([
                'u' => $username,
                'lat' => $lat,
                'lng' => $lng,
                'lbl' => $label,
                'exp' => $expiresAt->format('Y-m-d H:i:s'),
                'ca' => (new \DateTime())->format('Y-m-d H:i:s'),
            ]);
        }
        return $this->findActiveFor($username) ?? $this->forceFetch($username);
    }

    private function forceFetch(string $username): LocationPin
    {
        $stmt = $this->pdo()->prepare('SELECT * FROM location_pins WHERE username = :u LIMIT 1');
        $stmt->execute(['u' => $username]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $this->hydrate($row);
    }

    public function removeFor(string $username): void
    {
        $stmt = $this->pdo()->prepare('DELETE FROM location_pins WHERE username = :u');
        $stmt->execute(['u' => $username]);
    }

    /**
     * @param string[] $followingUsernames
     * @return LocationPin[]
     */
    public function findActiveForUsers(array $followingUsernames): array
    {
        if (empty($followingUsernames)) return [];
        $ph = implode(',', array_fill(0, count($followingUsernames), '?'));
        $sql = "SELECT * FROM location_pins WHERE username IN ($ph) AND expires_at > NOW() ORDER BY created_at DESC";
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute(array_values($followingUsernames));
        return array_map(fn($r) => $this->hydrate($r), $stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    public function purgeExpired(): int
    {
        $stmt = $this->pdo()->prepare('DELETE FROM location_pins WHERE expires_at <= NOW()');
        $stmt->execute();
        return $stmt->rowCount();
    }
}
