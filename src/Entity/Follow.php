<?php

namespace App\Entity;

use App\Repository\FollowRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * A user (follower_username) following another user (following_username).
 * Username-based FK (not Doctrine associations) for consistency with the
 * rest of this app, which uses plain strings as the author key.
 */
#[ORM\Entity(repositoryClass: FollowRepository::class)]
#[ORM\Table(name: 'sf_follows')]
#[ORM\UniqueConstraint(name: 'uniq_follow', columns: ['follower_username', 'following_username'])]
class Follow
{
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_PENDING  = 'pending';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(name: 'follower_username', length: 100)]
    private string $followerUsername;

    #[ORM\Column(name: 'following_username', length: 100)]
    private string $followingUsername;

    #[ORM\Column(length: 20, options: ['default' => self::STATUS_ACCEPTED])]
    private string $status = self::STATUS_ACCEPTED;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable', options: ['default' => 'CURRENT_TIMESTAMP'])]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getFollowerUsername(): string { return $this->followerUsername; }
    public function setFollowerUsername(string $v): static { $this->followerUsername = $v; return $this; }

    public function getFollowingUsername(): string { return $this->followingUsername; }
    public function setFollowingUsername(string $v): static { $this->followingUsername = $v; return $this; }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $v): static { $this->status = $v; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    public function isAccepted(): bool { return $this->status === self::STATUS_ACCEPTED; }
    public function isPending(): bool  { return $this->status === self::STATUS_PENDING; }
}
