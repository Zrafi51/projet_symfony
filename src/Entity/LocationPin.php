<?php

namespace App\Entity;

/**
 * Instagram-style location pin — one active pin per user on the shared map.
 */
class LocationPin
{
    private ?int $id = null;
    private string $username = '';
    private string $latitude = '0';
    private string $longitude = '0';
    private ?string $label = null;
    private \DateTimeInterface $expiresAt;
    private \DateTimeInterface $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->expiresAt = (clone $this->createdAt)->modify('+4 hours');
    }

    public function getId(): ?int { return $this->id; }
    public function setId(?int $id): static { $this->id = $id; return $this; }
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
    public function setCreatedAt(\DateTimeInterface $d): static { $this->createdAt = $d; return $this; }

    public function isExpired(): bool
    {
        return $this->expiresAt <= new \DateTime();
    }
}
