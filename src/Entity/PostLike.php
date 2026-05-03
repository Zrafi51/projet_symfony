<?php

namespace App\Entity;

/**
 * Tracks which user has liked which post.
 *
 * Enforces "one like per user per post" via a unique (post_id, username) constraint.
 * `username` is stored as a plain string (same convention as Post::auteur and
 * Comment::auteur), not a User FK — see ProfileController::edit() for the
 * rename-cascade logic.
 */
class PostLike
{
    private ?int $id = null;
    private ?Post $post = null;
    private ?int $postId = null;
    private ?string $username = null;
    private ?\DateTimeInterface $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }
    public function setId(?int $id): static { $this->id = $id; return $this; }

    public function getPost(): ?Post { return $this->post; }
    public function setPost(?Post $post): static
    {
        $this->post = $post;
        $this->postId = $post?->getId();
        return $this;
    }
    public function getPostId(): ?int { return $this->postId; }
    public function setPostId(?int $id): static { $this->postId = $id; return $this; }

    public function getUsername(): ?string { return $this->username; }
    public function setUsername(string|User|null $username): static
    {
        $this->username = $username instanceof User ? $username->getUsername() : $username;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface { return $this->createdAt; }
    public function setCreatedAt(?\DateTimeInterface $d): static { $this->createdAt = $d; return $this; }
}
