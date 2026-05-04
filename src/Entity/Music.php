<?php

namespace App\Entity;

use App\Repository\MusicRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A track in the admin-curated playlist. Users can attach one of these to
 * a post (Instagram-style background music). Only admins create/delete tracks.
 */
#[ORM\Entity(repositoryClass: MusicRepository::class)]
#[ORM\Table(name: 'sf_music')]
#[ORM\HasLifecycleCallbacks]
class Music
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 150)]
    #[Assert\NotBlank(message: 'Le titre est obligatoire.')]
    #[Assert\Length(max: 150, maxMessage: 'Titre trop long (max {{ limit }}).')]
    private ?string $title = null;

    #[ORM\Column(length: 150, nullable: true)]
    #[Assert\Length(max: 150, maxMessage: 'Artiste trop long (max {{ limit }}).')]
    private ?string $artist = null;

    #[ORM\Column(length: 500)]
    private ?string $filename = null;

    #[ORM\Column(name: 'uploaded_at', type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $uploadedAt = null;

    #[ORM\PrePersist]
    public function setUploadedAtValue(): void
    {
        if ($this->uploadedAt === null) {
            $this->uploadedAt = new \DateTime();
        }
    }

    public function getId(): ?int { return $this->id; }

    public function getTitle(): ?string { return $this->title; }
    public function setTitle(string $title): static { $this->title = $title; return $this; }

    public function getArtist(): ?string { return $this->artist; }
    public function setArtist(?string $artist): static { $this->artist = $artist; return $this; }

    public function getFilename(): ?string { return $this->filename; }
    public function setFilename(string $filename): static { $this->filename = $filename; return $this; }

    public function getUploadedAt(): ?\DateTimeInterface { return $this->uploadedAt; }

    public function getDisplayName(): string
    {
        return $this->artist
            ? sprintf('%s — %s', $this->artist, $this->title)
            : (string) $this->title;
    }
}
