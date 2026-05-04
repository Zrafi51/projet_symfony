<?php

namespace App\Entity;

use App\Repository\LocationPinRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Instagram-style location pin — one active pin per user on the shared map.
 * The pin lives until `expiresAt`; the repo filters expired rows at read time.
 * Re-pinning replaces the user's existing row (see repo::upsertFor), so there
 * is never more than one pin per username (unique index on `username`).
 */
#[ORM\Entity(repositoryClass: LocationPinRepository::class)]
#[ORM\Table(name: 'sf_location_pins')]
#[ORM\UniqueConstraint(name: 'uk_location_user', columns: ['username'])]
class LocationPin
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private string $username;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 7)]
    private string $latitude;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 7)]
    private string $longitude;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $label = null;

    #[ORM\Column(name: 'expires_at', type: 'datetime')]
    private \DateTimeInterface $expiresAt;

    #[ORM\Column(name: 'created_at', type: 'datetime', options: ['default' => 'CURRENT_TIMESTAMP'])]
    private \DateTimeInterface $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }
    public function getUsername(): string { return $this->username; }
    public function setUsername(string $u): static { $this->username = $u; return $this; }
    public function getLatitude(): float { return (float) $this->latitude; }
    public function setLatitude(float $v): static { $this->latitude = (string) $v; return $this; }
    public function getLongitude(): float { return (float) $this->longitude; }
    public function setLongitude(float $v): static { $this->longitude = (string) $v; return $this; }
    public function getLabel(): ?string { return $this->label; }
    public function setLabel(?string $l): static { $this->label = $l; return $this; }
    public function getExpiresAt(): \DateTimeInterface { return $this->expiresAt; }
    public function setExpiresAt(\DateTimeInterface $d): static { $this->expiresAt = $d; return $this; }
    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }

    public function isExpired(): bool
    {
        return $this->expiresAt <= new \DateTime();
    }
}
