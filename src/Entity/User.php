<?php

namespace App\Entity;

use App\Repository\ForumUserRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ForumUserRepository::class)]
#[ORM\Table(name: 'sf_users')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(name: 'username', length: 100, unique: true)]
    #[Assert\NotBlank(message: 'Le nom d\'utilisateur est obligatoire.')]
    #[Assert\Length(max: 100, maxMessage: 'Max {{ limit }} caractères.')]
    private ?string $username = null;

    #[ORM\Column(name: 'profile_photo_path', length: 255, nullable: true)]
    private ?string $profilePhotoPath = null;

    #[ORM\Column(name: 'profile_photo_blob', type: 'blob', nullable: true)]
    private $profilePhotoBlob = null;

    #[ORM\Column(name: 'profile_photo_mime_type', length: 100, nullable: true)]
    private ?string $profilePhotoMimeType = null;

    #[ORM\Column(length: 255)]
    private string $password = '';

    #[ORM\Column(length: 180, nullable: true)]
    #[Assert\Email(message: 'Email invalide.')]
    private ?string $email = null;

    // Short free-text description shown under the stats row on the profile
    // (own + public view). Capped to 30 chars in the form AND the column so
    // a rogue direct DB write still can't blow past the limit.
    #[ORM\Column(length: 30, nullable: true)]
    private ?string $bio = null;

    #[ORM\Column(type: 'json')]
    private array $roles = [];

    #[ORM\Column(name: 'is_private', type: 'boolean', options: ['default' => false])]
    private bool $isPrivate = false;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable', options: ['default' => 'CURRENT_TIMESTAMP'])]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(string $username): static
    {
        $this->username = $username;
        return $this;
    }

    public function getUserIdentifier(): string
    {
        return (string) $this->username;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;
        return $this;
    }

    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';
        return array_unique($roles);
    }

    public function setRoles(array $roles): static
    {
        $this->roles = $roles;
        return $this;
    }

    public function getProfilePhotoPath(): ?string
    {
        return $this->profilePhotoPath;
    }

    public function setProfilePhotoPath(?string $profilePhotoPath): static
    {
        $this->profilePhotoPath = $profilePhotoPath;
        return $this;
    }

    public function getProfilePhotoBlob()
    {
        return $this->profilePhotoBlob;
    }

    public function setProfilePhotoBlob($profilePhotoBlob): static
    {
        $this->profilePhotoBlob = $profilePhotoBlob;
        return $this;
    }

    public function getProfilePhotoMimeType(): ?string
    {
        return $this->profilePhotoMimeType;
    }

    public function setProfilePhotoMimeType(?string $profilePhotoMimeType): static
    {
        $this->profilePhotoMimeType = $profilePhotoMimeType;
        return $this;
    }

    public function hasProfilePhotoBlob(): bool
    {
        return $this->profilePhotoBlob !== null;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getBio(): ?string
    {
        return $this->bio;
    }

    public function setBio(?string $bio): static
    {
        // Trim so empty inputs ("   ") are stored as NULL instead of blank.
        $bio = $bio !== null ? trim($bio) : null;
        $this->bio = $bio === '' ? null : $bio;
        return $this;
    }

    public function isPrivate(): bool
    {
        return $this->isPrivate;
    }

    public function setIsPrivate(bool $isPrivate): static
    {
        $this->isPrivate = $isPrivate;
        return $this;
    }

    public function eraseCredentials(): void
    {
    }

    public function __toString(): string
    {
        return $this->username ?? '';
    }
}
