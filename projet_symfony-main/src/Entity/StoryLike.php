<?php

namespace App\Entity;

use App\Repository\StoryLikeRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Heart-toggle on a story. Click the heart → one row is inserted; click again
 * → the row is removed. Unique on (story, liker) so the UI always has a clean
 * "liked / not liked" boolean to paint.
 */
#[ORM\Entity(repositoryClass: StoryLikeRepository::class)]
#[ORM\Table(name: 'sf_story_likes')]
#[ORM\UniqueConstraint(name: 'uk_story_liker', columns: ['story_id', 'liker_username'])]
class StoryLike
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Story::class)]
    #[ORM\JoinColumn(name: 'story_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Story $story = null;

    #[ORM\Column(name: 'liker_username', length: 100)]
    private string $likerUsername;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable', options: ['default' => 'CURRENT_TIMESTAMP'])]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getStory(): ?Story { return $this->story; }
    public function setStory(Story $s): static { $this->story = $s; return $this; }
    public function getLikerUsername(): string { return $this->likerUsername; }
    public function setLikerUsername(string $u): static { $this->likerUsername = $u; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
