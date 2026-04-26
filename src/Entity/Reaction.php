<?php

namespace App\Entity;

use App\Repository\ReactionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ReactionRepository::class)]
#[ORM\Table(name: 'sf_reactions')]
#[ORM\HasLifecycleCallbacks]
class Reaction
{
    public const TYPES = [
        '❤️' => 'J\'adore',
        '😂' => 'Haha',
        '😮' => 'Wow',
        '😢' => 'Triste',
        '✈️' => 'Voyage',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Post::class, inversedBy: 'reactions')]
    #[ORM\JoinColumn(name: 'post_id', referencedColumnName: 'id', nullable: false)]
    private ?Post $post = null;

    /** Username of the user who reacted (string column). */
    #[ORM\Column(length: 100)]
    private ?string $username = null;

    #[ORM\Column(name: 'reaction_type', length: 20)]
    private ?string $reactionType = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        if ($this->createdAt === null) {
            $this->createdAt = new \DateTime();
        }
    }

    public function getCreatedAt(): ?\DateTimeInterface { return $this->createdAt; }

    public function getId(): ?int { return $this->id; }

    public function getPost(): ?Post { return $this->post; }
    public function setPost(?Post $post): static { $this->post = $post; return $this; }

    public function getUsername(): ?string { return $this->username; }
    public function setUsername(string|User|null $user): static
    {
        $this->username = $user instanceof User ? $user->getUsername() : $user;
        return $this;
    }

    /** Alias kept for backward compatibility with existing controllers. */
    public function setUser(User $user): static { return $this->setUsername($user); }
    public function getUser(): ?string { return $this->username; }

    public function getReactionType(): ?string { return $this->reactionType; }
    public function setReactionType(string $reactionType): static { $this->reactionType = $reactionType; return $this; }

    public function getLabel(): string
    {
        return self::TYPES[$this->reactionType] ?? $this->reactionType;
    }
}
