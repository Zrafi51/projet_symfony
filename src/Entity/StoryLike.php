<?php

namespace App\Entity;

/**
 * Heart-toggle on a story.
 */
class StoryLike
{
    private ?int $id = null;
    private ?Story $story = null;
    private ?int $storyId = null;
    private string $likerUsername = '';
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function setId(?int $id): static { $this->id = $id; return $this; }

    public function getStory(): ?Story { return $this->story; }
    public function setStory(?Story $s): static
    {
        $this->story = $s;
        $this->storyId = $s?->getId();
        return $this;
    }
    public function getStoryId(): ?int { return $this->storyId; }
    public function setStoryId(?int $id): static { $this->storyId = $id; return $this; }

    public function getLikerUsername(): string { return $this->likerUsername; }
    public function setLikerUsername(string $u): static { $this->likerUsername = $u; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(\DateTimeImmutable $d): static { $this->createdAt = $d; return $this; }
}
