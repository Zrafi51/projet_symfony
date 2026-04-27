<?php

namespace App\Entity;

use App\Repository\CommentRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CommentRepository::class)]
#[ORM\Table(name: 'sf_comments')]
#[ORM\HasLifecycleCallbacks]
class Comment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Post::class, inversedBy: 'comments')]
    #[ORM\JoinColumn(name: 'post_id', referencedColumnName: 'id', nullable: false)]
    private ?Post $post = null;

    /** Username of comment author (string). */
    #[ORM\Column(length: 100)]
    private ?string $auteur = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: 'Le commentaire ne peut pas être vide.')]
    private ?string $contenu = null;

    #[ORM\Column(name: 'date_commentaire', type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $dateCommentaire = null;

    #[ORM\PrePersist]
    public function setDateCommentaireValue(): void
    {
        if ($this->dateCommentaire === null) {
            $this->dateCommentaire = new \DateTime();
        }
    }

    public function getId(): ?int { return $this->id; }

    public function getPost(): ?Post { return $this->post; }
    public function setPost(?Post $post): static { $this->post = $post; return $this; }

    public function getAuteur(): ?string { return $this->auteur; }
    public function setAuteur(string|User|null $auteur): static
    {
        $this->auteur = $auteur instanceof User ? $auteur->getUsername() : $auteur;
        return $this;
    }

    public function getContenu(): ?string { return $this->contenu; }
    public function setContenu(string $contenu): static { $this->contenu = $contenu; return $this; }

    public function getDateCommentaire(): ?\DateTimeInterface { return $this->dateCommentaire; }
    public function setDateCommentaire(\DateTimeInterface $dateCommentaire): static { $this->dateCommentaire = $dateCommentaire; return $this; }
}
