<?php

namespace App\Entity;

use Symfony\Component\Validator\Constraints as Assert;

class Image
{
    private ?int $id = null;
    private ?Post $post = null;
    private ?int $postId = null;
    private ?string $filename = null;

    #[Assert\Length(max: 255, maxMessage: 'La description ne peut dépasser {{ limit }} caractères.')]
    private ?string $description = null;

    private int $position = 0;

    public function getId(): ?int { return $this->id; }
    public function setId(?int $id): static { $this->id = $id; return $this; }
    public function getPost(): ?Post { return $this->post; }
    public function setPost(?Post $post): static { $this->post = $post; $this->postId = $post?->getId(); return $this; }
    public function getPostId(): ?int { return $this->postId; }
    public function setPostId(?int $id): static { $this->postId = $id; return $this; }
    public function getFilename(): ?string { return $this->filename; }
    public function setFilename(string $filename): static { $this->filename = $filename; return $this; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): static { $this->description = $description; return $this; }
    public function getPosition(): int { return $this->position; }
    public function setPosition(int $position): static { $this->position = $position; return $this; }
}
