<?php

namespace App\Entity;

/**
 * A 24-hour ephemeral story (Instagram/Facebook-style).
 */
class Story
{
    public const TYPE_IMAGE = 'image';
    public const TYPE_VIDEO = 'video';

    private ?int $id = null;
    private ?string $auteur = null;
    private string $mediaType = self::TYPE_IMAGE;
    private ?string $filename = null;
    private ?Music $music = null;
    private ?int $musicId = null;
    private ?float $musicStart = null;
    private ?\DateTimeInterface $createdAt = null;
    private ?\DateTimeInterface $expiresAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->expiresAt = (clone $this->createdAt)->modify('+24 hours');
    }

    public function getId(): ?int { return $this->id; }
    public function setId(?int $id): static { $this->id = $id; return $this; }

    public function getAuteur(): ?string { return $this->auteur; }
    public function setAuteur(string|User|null $auteur): static
    {
        $this->auteur = $auteur instanceof User ? $auteur->getUsername() : $auteur;
        return $this;
    }

    public function getMediaType(): string { return $this->mediaType; }
    public function setMediaType(string $mediaType): static
    {
        if (!in_array($mediaType, [self::TYPE_IMAGE, self::TYPE_VIDEO], true)) {
            throw new \InvalidArgumentException('Invalid media type: ' . $mediaType);
        }
        $this->mediaType = $mediaType;
        return $this;
    }

    public function getFilename(): ?string { return $this->filename; }
    public function setFilename(string $filename): static { $this->filename = $filename; return $this; }

    public function getMusic(): ?Music { return $this->music; }
    public function setMusic(?Music $music): static
    {
        $this->music = $music;
        $this->musicId = $music?->getId();
        return $this;
    }
    public function getMusicId(): ?int { return $this->musicId; }
    public function setMusicId(?int $id): static { $this->musicId = $id; return $this; }

    public function getMusicStart(): ?float { return $this->musicStart; }
    public function setMusicStart(?float $musicStart): static { $this->musicStart = $musicStart; return $this; }

    public function getCreatedAt(): ?\DateTimeInterface { return $this->createdAt; }
    public function setCreatedAt(?\DateTimeInterface $d): static { $this->createdAt = $d; return $this; }

    public function getExpiresAt(): ?\DateTimeInterface { return $this->expiresAt; }
    public function setExpiresAt(?\DateTimeInterface $d): static { $this->expiresAt = $d; return $this; }

    public function isExpired(): bool
    {
        return $this->expiresAt !== null && $this->expiresAt < new \DateTime();
    }

    public function isImage(): bool { return $this->mediaType === self::TYPE_IMAGE; }
    public function isVideo(): bool { return $this->mediaType === self::TYPE_VIDEO; }
}
