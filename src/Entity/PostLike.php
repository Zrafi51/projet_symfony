<?php

namespace App\Entity;

use App\Repository\PostLikeRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Tracks which user has liked which post.
 *
 * Enforces "one like per user per post" via a unique (post_id, username) constraint.
 * `username` is stored as a plain string (same convention as Post::auteur and
 * Comment::auteur), not a User FK — see ProfileController::edit() for the
 * rename-cascade logic.
 */
#[ORM\Entity(repositoryClass: PostLikeRepository::class)]
#[ORM\Table(name: 'sf_post_likes')]
#[ORM\UniqueConstraint(name: 'uniq_post_user', columns: ['post_id', 'username'])]
#[ORM\HasLifecycleCallbacks]
class PostLike
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Post::class)]
    #[ORM\JoinColumn(name: 'post_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Post $post = null;

    #[ORM\Column(length: 100)]
    private ?string $username = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        if ($this->createdAt === null) {
            $this->createdAt = new \DateTime();
        }
    }

    public function getId(): ?int { return $this->id; }

    public function getPost(): ?Post { return $this->post; }
    public function setPost(?Post $post): static { $this->post = $post; return $this; }

    public function getUsername(): ?string { return $this->username; }
    public function setUsername(string|User|null $username): static
    {
        $this->username = $username instanceof User ? $username->getUsername() : $username;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface { return $this->createdAt; }
}
