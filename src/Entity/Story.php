<?php

namespace App\Entity;

use App\Repository\StoryRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * A 24-hour ephemeral story (Instagram/Facebook-style).
 *
 * Each story carries exactly ONE media item — either an image or a video —
 * plus an optional background track from the admin-curated Music playlist.
 * `expiresAt` is set to createdAt + 24h at insertion; expired rows are hidden
 * by repository queries and periodically purged (see StoryRepository).
 */
#[ORM\Entity(repositoryClass: StoryRepository::class)]
#[ORM\Table(name: 'sf_stories')]
#[ORM\HasLifecycleCallbacks]
class Story
{
    public const TYPE_IMAGE = 'image';
    public const TYPE_VIDEO = 'video';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Username of the author (matches Post::auteur shape — string FK to users.username). */
    #[ORM\Column(length: 100)]
    private ?string $auteur = null;

    /** 'image' or 'video'. */
    #[ORM\Column(name: 'media_type', length: 10)]
    private string $mediaType = self::TYPE_IMAGE;

    /** Stored filename under /uploads/stories. */
    #[ORM\Column(length: 500)]
    private ?string $filename = null;

    /** Optional background music (ManyToOne to the admin playlist). */
    #[ORM\ManyToOne(targetEntity: Music::class)]
    #[ORM\JoinColumn(name: 'music_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Music $music = null;

    /**
     * Start offset (in seconds) of the music clip. The story plays only the
     * segment [musicStart, musicStart + duration] where duration is:
     *   - the video's length if the story is a video, OR
     *   - 15 s if the story is an image.
     * Null when no music is attached or when the user didn't trim.
     */
    #[ORM\Column(name: 'music_start', type: 'float', nullable: true)]
    private ?float $musicStart = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(name: 'expires_at', type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $expiresAt = null;

    #[ORM\PrePersist]
    public function initTimestamps(): void
    {
        if ($this->createdAt === null) {
            $this->createdAt = new \DateTime();
        }
        if ($this->expiresAt === null) {
            $this->expiresAt = (clone $this->createdAt)->modify('+24 hours');
        }
    }

    public function getId(): ?int { return $this->id; }

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
    public function setMusic(?Music $music): static { $this->music = $music; return $this; }

    public function getMusicStart(): ?float { return $this->musicStart; }
    public function setMusicStart(?float $musicStart): static { $this->musicStart = $musicStart; return $this; }

    public function getCreatedAt(): ?\DateTimeInterface { return $this->createdAt; }

    public function getExpiresAt(): ?\DateTimeInterface { return $this->expiresAt; }

    public function isExpired(): bool
    {
        return $this->expiresAt !== null && $this->expiresAt < new \DateTime();
    }

    /** True when the story is an image. */
    public function isImage(): bool { return $this->mediaType === self::TYPE_IMAGE; }
    public function isVideo(): bool { return $this->mediaType === self::TYPE_VIDEO; }
}
