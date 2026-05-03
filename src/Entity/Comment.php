<?php

namespace App\Entity;

use Symfony\Component\Validator\Constraints as Assert;

class Comment
{
    private ?int $id = null;
    private ?Post $post = null;
    private ?int $postId = null;
    private ?string $auteur = null;

    #[Assert\NotBlank(message: 'Le commentaire ne peut pas être vide.')]
    private ?string $contenu = null;

    private ?\DateTimeInterface $dateCommentaire = null;

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
    public function setPostId(?int $postId): static { $this->postId = $postId; return $this; }

    public function getAuteur(): ?string { return $this->auteur; }
    public function setAuteur(string|User|null $auteur): static
    {
        $this->auteur = $auteur instanceof User ? $auteur->getUsername() : $auteur;
        return $this;
    }

    public function getContenu(): ?string { return $this->contenu; }
    public function setContenu(string $contenu): static { $this->contenu = $contenu; return $this; }

    public function getDateCommentaire(): ?\DateTimeInterface { return $this->dateCommentaire; }
    public function setDateCommentaire(\DateTimeInterface $d): static { $this->dateCommentaire = $d; return $this; }
}
