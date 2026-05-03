<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Validator\Constraints as Assert;

class Post
{
    private ?int $id = null;
    private ?string $auteur = null;

    #[Assert\NotBlank(message: 'La description est obligatoire.')]
    #[Assert\Length(min: 5, minMessage: 'Au moins {{ limit }} caractères.')]
    private ?string $description = null;

    private ?string $cheminPhoto = '';
    private int $likes = 0;
    private ?\DateTimeInterface $dateCreation = null;

    /** @var Collection<int, Reaction> */
    private Collection $reactions;
    /** @var Collection<int, Comment> */
    private Collection $comments;
    /** @var Collection<int, Image> */
    private Collection $images;
    /** @var Collection<int, Video> */
    private Collection $videos;

    private ?Music $music = null;
    private ?int $musicId = null;

    public function __construct()
    {
        $this->reactions = new ArrayCollection();
        $this->comments = new ArrayCollection();
        $this->images = new ArrayCollection();
        $this->videos = new ArrayCollection();
        $this->dateCreation = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }
    public function setId(?int $id): static { $this->id = $id; return $this; }

    public function getAuteur(): ?string { return $this->auteur; }
    public function setAuteur(string|User|null $auteur): static
    {
        $this->auteur = $auteur instanceof User ? $auteur->getUsername() : $auteur;
        return $this;
    }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(string $description): static { $this->description = $description; return $this; }

    public function getCheminPhoto(): ?string { return $this->cheminPhoto; }
    public function setCheminPhoto(?string $cheminPhoto): static { $this->cheminPhoto = $cheminPhoto ?? ''; return $this; }

    public function getLikes(): int { return $this->likes; }
    public function setLikes(int $likes): static { $this->likes = $likes; return $this; }
    public function incrementLikes(): static { $this->likes++; return $this; }

    public function getDateCreation(): ?\DateTimeInterface { return $this->dateCreation; }
    public function setDateCreation(\DateTimeInterface $dateCreation): static { $this->dateCreation = $dateCreation; return $this; }

    public function getReactions(): Collection { return $this->reactions; }
    public function setReactions(Collection|array $reactions): static
    {
        $this->reactions = is_array($reactions) ? new ArrayCollection($reactions) : $reactions;
        return $this;
    }
    public function addReaction(Reaction $reaction): static
    {
        if (!$this->reactions->contains($reaction)) { $this->reactions->add($reaction); $reaction->setPost($this); }
        return $this;
    }
    public function removeReaction(Reaction $reaction): static { $this->reactions->removeElement($reaction); return $this; }

    public function getComments(): Collection { return $this->comments; }
    public function setComments(Collection|array $comments): static
    {
        $this->comments = is_array($comments) ? new ArrayCollection($comments) : $comments;
        return $this;
    }
    public function addComment(Comment $comment): static
    {
        if (!$this->comments->contains($comment)) { $this->comments->add($comment); $comment->setPost($this); }
        return $this;
    }
    public function removeComment(Comment $comment): static { $this->comments->removeElement($comment); return $this; }

    public function getTotalReactions(): int { return $this->reactions->count(); }

    public function getReactionCounts(): array
    {
        $counts = [];
        foreach ($this->reactions as $reaction) {
            $type = $reaction->getReactionType();
            $counts[$type] = ($counts[$type] ?? 0) + 1;
        }
        return $counts;
    }

    public function getImages(): Collection { return $this->images; }
    public function setImages(Collection|array $images): static
    {
        $this->images = is_array($images) ? new ArrayCollection($images) : $images;
        return $this;
    }
    public function addImage(Image $image): static
    {
        if (!$this->images->contains($image)) { $this->images->add($image); $image->setPost($this); }
        return $this;
    }
    public function removeImage(Image $image): static
    {
        if ($this->images->removeElement($image)) {
            if ($image->getPost() === $this) { $image->setPost(null); }
        }
        return $this;
    }
    public function getImageCount(): int { return $this->images->count(); }

    public function getVideos(): Collection { return $this->videos; }
    public function setVideos(Collection|array $videos): static
    {
        $this->videos = is_array($videos) ? new ArrayCollection($videos) : $videos;
        return $this;
    }
    public function addVideo(Video $video): static
    {
        if (!$this->videos->contains($video)) { $this->videos->add($video); $video->setPost($this); }
        return $this;
    }
    public function removeVideo(Video $video): static
    {
        if ($this->videos->removeElement($video)) {
            if ($video->getPost() === $this) { $video->setPost(null); }
        }
        return $this;
    }
    public function getVideoCount(): int { return $this->videos->count(); }

    public function getMusic(): ?Music { return $this->music; }
    public function setMusic(?Music $music): static
    {
        $this->music = $music;
        $this->musicId = $music?->getId();
        return $this;
    }

    public function getMusicId(): ?int { return $this->musicId; }
    public function setMusicId(?int $id): static { $this->musicId = $id; return $this; }
}
