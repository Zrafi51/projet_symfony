<?php

namespace App\Entity;

use App\Repository\ProctorLogRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProctorLogRepository::class)]
#[ORM\Table(name: 'proctor_log')]
class ProctorLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $sessionId = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $voyageId = null;

    #[ORM\Column(type: 'string', length: 100)]
    private ?string $violationType = null;

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

    public function setVoyageId(?int $voyageId): self
    {
        $this->voyageId = $voyageId;

        return $this;
    }

    public function getViolationType(): ?string
    {
        return $this->violationType;
    }

    public function setViolationType(string $violationType): self
    {
        $this->violationType = $violationType;

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
