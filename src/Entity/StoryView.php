<?php

namespace App\Entity;

/**
 * One row per (story, viewer).
 */
class StoryView
{
    private ?int $id = null;
    private ?Story $story = null;
    private ?int $storyId = null;
    private string $viewerUsername = '';
    private \DateTimeImmutable $viewedAt;

    public function __construct()
    {
        $this->viewedAt = new \DateTimeImmutable();
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

    public function getViewerUsername(): string { return $this->viewerUsername; }
    public function setViewerUsername(string $u): static { $this->viewerUsername = $u; return $this; }

    public function getViewedAt(): \DateTimeImmutable { return $this->viewedAt; }
    public function setViewedAt(\DateTimeImmutable $d): static { $this->viewedAt = $d; return $this; }
}
