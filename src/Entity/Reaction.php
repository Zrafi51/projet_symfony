<?php

namespace App\Entity;

class Reaction
{
    public const TYPES = [
        '❤️' => 'J\'adore',
        '😂' => 'Haha',
        '😮' => 'Wow',
        '😢' => 'Triste',
        '✈️' => 'Voyage',
    ];

    private ?int $id = null;
    private ?Post $post = null;
    private ?int $postId = null;
    private ?string $username = null;
    private ?string $reactionType = null;
    private ?\DateTimeInterface $createdAt = null;

    public function getId(): ?int { return $this->id; }
    public function setId(?int $id): static { $this->id = $id; return $this; }
    public function getCreatedAt(): ?\DateTimeInterface { return $this->createdAt; }
    public function setCreatedAt(?\DateTimeInterface $d): static { $this->createdAt = $d; return $this; }
    public function getPost(): ?Post { return $this->post; }
    public function setPost(?Post $post): static { $this->post = $post; $this->postId = $post?->getId(); return $this; }
    public function getPostId(): ?int { return $this->postId; }
    public function setPostId(?int $id): static { $this->postId = $id; return $this; }

    public function getUsername(): ?string { return $this->username; }
    public function setUsername(string|User|null $user): static
    {
        $this->username = $user instanceof User ? $user->getUsername() : $user;
        return $this;
    }
    public function setUser(User $user): static { return $this->setUsername($user); }
    public function getUser(): ?string { return $this->username; }

    public function getReactionType(): ?string { return $this->reactionType; }
    public function setReactionType(string $reactionType): static { $this->reactionType = $reactionType; return $this; }

    public function getLabel(): string
    {
        return self::TYPES[$this->reactionType] ?? $this->reactionType;
    }
}
