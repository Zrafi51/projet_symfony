<?php

namespace App\Entity;

use App\Repository\QuizAnswerRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: QuizAnswerRepository::class)]
#[ORM\Table(name: 'quiz_answer')]
class QuizAnswer
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $sessionId = null;

    #[ORM\Column(type: 'integer')]
    private ?int $voyageId = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $userAnswer = null;

    #[ORM\Column(type: 'boolean')]
    private ?bool $isCorrect = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $createdAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSessionId(): ?string
    {
        return $this->sessionId;
    }

    public function setSessionId(string $sessionId): self
    {
        $this->sessionId = $sessionId;

        return $this;
    }

    public function getVoyageId(): ?int
    {
        return $this->voyageId;
    }

    public function setVoyageId(int $voyageId): self
    {
        $this->voyageId = $voyageId;

        return $this;
    }

    public function getUserAnswer(): ?string
    {
        return $this->userAnswer;
    }

    public function setUserAnswer(string $userAnswer): self
    {
        $this->userAnswer = $userAnswer;

        return $this;
    }

    public function getIsCorrect(): ?bool
    {
        return $this->isCorrect;
    }

    public function setIsCorrect(bool $isCorrect): self
    {
        $this->isCorrect = $isCorrect;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }
}
