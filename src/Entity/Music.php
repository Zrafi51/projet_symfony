<?php

namespace App\Entity;

use Symfony\Component\Validator\Constraints as Assert;

class Music
{
    private ?int $id = null;

    #[Assert\NotBlank(message: 'Le titre est obligatoire.')]
    #[Assert\Length(max: 150, maxMessage: 'Titre trop long (max {{ limit }}).')]
    private ?string $title = null;

    #[Assert\Length(max: 150, maxMessage: 'Artiste trop long (max {{ limit }}).')]
    private ?string $artist = null;

    private ?string $filename = null;
    private ?\DateTimeInterface $uploadedAt = null;

    public function getId(): ?int { return $this->id; }
    public function setId(?int $id): static { $this->id = $id; return $this; }
    public function getTitle(): ?string { return $this->title; }
    public function setTitle(string $title): static { $this->title = $title; return $this; }
    public function getArtist(): ?string { return $this->artist; }
    public function setArtist(?string $artist): static { $this->artist = $artist; return $this; }
    public function getFilename(): ?string { return $this->filename; }
    public function setFilename(string $filename): static { $this->filename = $filename; return $this; }
    public function getUploadedAt(): ?\DateTimeInterface { return $this->uploadedAt; }
    public function setUploadedAt(?\DateTimeInterface $d): static { $this->uploadedAt = $d; return $this; }

    public function getDisplayName(): string
    {
        return $this->artist
            ? sprintf('%s — %s', $this->artist, $this->title)
            : (string) $this->title;
    }
}
