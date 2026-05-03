<?php

namespace App\Entity;

class Follow
{
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_PENDING  = 'pending';

    private ?int $id = null;
    private string $followerUsername = '';
    private string $followingUsername = '';
    private string $status = self::STATUS_ACCEPTED;
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function setId(?int $id): static { $this->id = $id; return $this; }
    public function getFollowerUsername(): string { return $this->followerUsername; }
    public function setFollowerUsername(string $v): static { $this->followerUsername = $v; return $this; }
    public function getFollowingUsername(): string { return $this->followingUsername; }
    public function setFollowingUsername(string $v): static { $this->followingUsername = $v; return $this; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $v): static { $this->status = $v; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(\DateTimeImmutable $d): static { $this->createdAt = $d; return $this; }
    public function isAccepted(): bool { return $this->status === self::STATUS_ACCEPTED; }
    public function isPending(): bool  { return $this->status === self::STATUS_PENDING; }
}
