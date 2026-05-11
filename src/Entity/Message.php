<?php

namespace App\Entity;

use App\Repository\MessageRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Direct message between two users. Username-based FK (matches the rest of the
 * app). The `is_read` flag is flipped when the receiver opens the conversation.
 */
#[ORM\Entity(repositoryClass: MessageRepository::class)]
#[ORM\Table(name: 'sf_messages')]
class Message
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(name: 'sender_username', length: 100)]
    private string $senderUsername;

    #[ORM\Column(name: 'receiver_username', length: 100)]
    private string $receiverUsername;

    #[ORM\Column(type: 'text')]
    #[Assert\NotBlank(message: 'Le message ne peut pas être vide.')]
    #[Assert\Length(max: 5000, maxMessage: 'Le message ne peut pas dépasser {{ limit }} caractères.')]
    private string $content = '';

    // When the message was composed from a story-reply input, this points at
    // the referenced Story so the thread can render its thumbnail (à la
    // Instagram "Vous avez répondu à sa story"). NULL for regular DMs; set to
    // NULL automatically by the FK when the story expires / is deleted.
    #[ORM\ManyToOne(targetEntity: Story::class)]
    #[ORM\JoinColumn(name: 'story_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Story $story = null;

    #[ORM\Column(name: 'is_read', type: 'boolean', options: ['default' => false])]
    private bool $isRead = false;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable', options: ['default' => 'CURRENT_TIMESTAMP'])]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getSenderUsername(): string { return $this->senderUsername; }
    public function setSenderUsername(string $v): static { $this->senderUsername = $v; return $this; }

    public function getReceiverUsername(): string { return $this->receiverUsername; }
    public function setReceiverUsername(string $v): static { $this->receiverUsername = $v; return $this; }

    public function getContent(): string { return $this->content; }
    public function setContent(string $v): static { $this->content = $v; return $this; }

    public function getStory(): ?Story { return $this->story; }
    public function setStory(?Story $s): static { $this->story = $s; return $this; }

    public function isRead(): bool { return $this->isRead; }
    public function setIsRead(bool $v): static { $this->isRead = $v; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
