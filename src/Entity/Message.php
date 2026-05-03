<?php

namespace App\Entity;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Direct message between two users. Username-based FK (matches the rest of the
 * app). The `is_read` flag is flipped when the receiver opens the conversation.
 */
class Message
{
    private ?int $id = null;
    private string $senderUsername = '';
    private string $receiverUsername = '';

    #[Assert\NotBlank(message: 'Le message ne peut pas être vide.')]
    #[Assert\Length(max: 5000, maxMessage: 'Le message ne peut pas dépasser {{ limit }} caractères.')]
    private string $content = '';

    private ?Story $story = null;
    private ?int $storyId = null;

    private bool $isRead = false;
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function setId(?int $id): static { $this->id = $id; return $this; }

    public function getSenderUsername(): string { return $this->senderUsername; }
    public function setSenderUsername(string $v): static { $this->senderUsername = $v; return $this; }

    public function getReceiverUsername(): string { return $this->receiverUsername; }
    public function setReceiverUsername(string $v): static { $this->receiverUsername = $v; return $this; }

    public function getContent(): string { return $this->content; }
    public function setContent(string $v): static { $this->content = $v; return $this; }

    public function getStory(): ?Story { return $this->story; }
    public function setStory(?Story $s): static
    {
        $this->story = $s;
        $this->storyId = $s?->getId();
        return $this;
    }
    public function getStoryId(): ?int { return $this->storyId; }
    public function setStoryId(?int $id): static { $this->storyId = $id; return $this; }

    public function isRead(): bool { return $this->isRead; }
    public function setIsRead(bool $v): static { $this->isRead = $v; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(\DateTimeImmutable $d): static { $this->createdAt = $d; return $this; }
}
