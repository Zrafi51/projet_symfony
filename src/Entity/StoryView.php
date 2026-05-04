<?php

namespace App\Entity;

use App\Repository\StoryViewRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * One row per (story, viewer). Records the first time a user opened a story
 * in the viewer so the story author can see "X a vu votre story" in their
 * notification bell. Re-opens don't create additional rows (unique index).
 */
#[ORM\Entity(repositoryClass: StoryViewRepository::class)]
#[ORM\Table(name: 'sf_story_views')]
#[ORM\UniqueConstraint(name: 'uk_story_viewer', columns: ['story_id', 'viewer_username'])]
class StoryView
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Story::class)]
    #[ORM\JoinColumn(name: 'story_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Story $story = null;

    #[ORM\Column(name: 'viewer_username', length: 100)]
    private string $viewerUsername;

    #[ORM\Column(name: 'viewed_at', type: 'datetime_immutable', options: ['default' => 'CURRENT_TIMESTAMP'])]
    private \DateTimeImmutable $viewedAt;

    public function __construct()
    {
        $this->viewedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getStory(): ?Story { return $this->story; }
    public function setStory(Story $s): static { $this->story = $s; return $this; }
    public function getViewerUsername(): string { return $this->viewerUsername; }
    public function setViewerUsername(string $u): static { $this->viewerUsername = $u; return $this; }
    public function getViewedAt(): \DateTimeImmutable { return $this->viewedAt; }
}
