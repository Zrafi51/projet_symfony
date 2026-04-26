<?php

namespace App\Entity;

use App\Repository\PostRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PostRepository::class)]
#[ORM\Table(name: 'sf_posts')]
#[ORM\HasLifecycleCallbacks]
class Post
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Username of the author (string FK to users.username). */
    #[ORM\Column(length: 100)]
    private ?string $auteur = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: 'La description est obligatoire.')]
    #[Assert\Length(min: 5, minMessage: 'Au moins {{ limit }} caractères.')]
    private ?string $description = null;

    #[ORM\Column(name: 'chemin_photo', length: 500)]
    private ?string $cheminPhoto = '';

    #[ORM\Column]
    private int $likes = 0;

    #[ORM\Column(name: 'date_creation', type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $dateCreation = null;

    /** @var Collection<int, Reaction> */
    #[ORM\OneToMany(targetEntity: Reaction::class, mappedBy: 'post', orphanRemoval: true, cascade: ['remove'])]
    private Collection $reactions;

    /** @var Collection<int, Comment> */
    #[ORM\OneToMany(targetEntity: Comment::class, mappedBy: 'post', orphanRemoval: true, cascade: ['remove'])]
    #[ORM\OrderBy(['dateCommentaire' => 'DESC'])]
    private Collection $comments;

    /** @var Collection<int, Image> */
    #[ORM\OneToMany(targetEntity: Image::class, mappedBy: 'post', orphanRemoval: true, cascade: ['persist', 'remove'])]
    #[ORM\OrderBy(['position' => 'ASC', 'id' => 'ASC'])]
    private Collection $images;

    /** @var Collection<int, Video> */
    #[ORM\OneToMany(targetEntity: Video::class, mappedBy: 'post', orphanRemoval: true, cascade: ['persist', 'remove'])]
    #[ORM\OrderBy(['position' => 'ASC', 'id' => 'ASC'])]
    private Collection $videos;

    /** Optional background music attached to the post (admin-curated playlist). */
    #[ORM\ManyToOne(targetEntity: Music::class)]
    #[ORM\JoinColumn(name: 'music_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Music $music = null;

    public function __construct()
    {
        $this->reactions = new ArrayCollection();
        $this->comments = new ArrayCollection();
        $this->images = new ArrayCollection();
        $this->videos = new ArrayCollection();
        $this->dateCreation = new \DateTime();
    }

    #[ORM\PrePersist]
    public function setDateCreationValue(): void
    {
        if ($this->dateCreation === null) {
            $this->dateCreation = new \DateTime();
        }
    }

    public function getId(): ?int { return $this->id; }

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

    /** @return Collection<int, Reaction> */
    public function getReactions(): Collection { return $this->reactions; }

    public function addReaction(Reaction $reaction): static
    {
        if (!$this->reactions->contains($reaction)) {
            $this->reactions->add($reaction);
            $reaction->setPost($this);
        }
        return $this;
    }

    public function removeReaction(Reaction $reaction): static
    {
        $this->reactions->removeElement($reaction);
        return $this;
    }

    /** @return Collection<int, Comment> */
    public function getComments(): Collection { return $this->comments; }

    public function addComment(Comment $comment): static
    {
        if (!$this->comments->contains($comment)) {
            $this->comments->add($comment);
            $comment->setPost($this);
        }
        return $this;
    }

    public function removeComment(Comment $comment): static
    {
        $this->comments->removeElement($comment);
        return $this;
    }

    public function getTotalReactions(): int
    {
        return $this->reactions->count();
    }

    public function getReactionCounts(): array
    {
        $counts = [];
        foreach ($this->reactions as $reaction) {
            $type = $reaction->getReactionType();
            $counts[$type] = ($counts[$type] ?? 0) + 1;
        }
        return $counts;
    }

    /** @return Collection<int, Image> */
    public function getImages(): Collection { return $this->images; }

    public function addImage(Image $image): static
    {
        if (!$this->images->contains($image)) {
            $this->images->add($image);
            $image->setPost($this);
        }
        return $this;
    }

    public function removeImage(Image $image): static
    {
        if ($this->images->removeElement($image)) {
            if ($image->getPost() === $this) {
                $image->setPost(null);
            }
        }
        return $this;
    }

    public function getImageCount(): int
    {
        return $this->images->count();
    }

    /** @return Collection<int, Video> */
    public function getVideos(): Collection { return $this->videos; }

    public function addVideo(Video $video): static
    {
        if (!$this->videos->contains($video)) {
            $this->videos->add($video);
            $video->setPost($this);
        }
        return $this;
    }

    public function removeVideo(Video $video): static
    {
        if ($this->videos->removeElement($video)) {
            if ($video->getPost() === $this) {
                $video->setPost(null);
            }
        }
        return $this;
    }

    public function getVideoCount(): int
    {
        return $this->videos->count();
    }

    public function getMusic(): ?Music { return $this->music; }
    public function setMusic(?Music $music): static { $this->music = $music; return $this; }
}
