<?php

namespace App\Entity;

use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    private ?int $id = null;

    #[Assert\NotBlank(message: 'Le nom d\'utilisateur est obligatoire.')]
    #[Assert\Length(max: 100, maxMessage: 'Max {{ limit }} caractères.')]
    private ?string $username = null;

    private ?string $profilePhotoPath = null;
    private string $password = '';

    #[Assert\Email(message: 'Email invalide.')]
    private ?string $email = null;

    private ?string $bio = null;
    private array $roles = [];
    private bool $isPrivate = false;
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function setId(?int $id): static { $this->id = $id; return $this; }

    public function getUsername(): ?string { return $this->username; }
    public function setUsername(string $username): static { $this->username = $username; return $this; }

    public function getUserIdentifier(): string { return (string) $this->username; }

    public function getPassword(): string { return $this->password; }
    public function setPassword(string $password): static { $this->password = $password; return $this; }

    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';
        return array_unique($roles);
    }

    public function setRoles(array $roles): static { $this->roles = $roles; return $this; }

    public function getProfilePhotoPath(): ?string { return $this->profilePhotoPath; }
    public function setProfilePhotoPath(?string $profilePhotoPath): static { $this->profilePhotoPath = $profilePhotoPath; return $this; }

    public function getEmail(): ?string { return $this->email; }
    public function setEmail(?string $email): static { $this->email = $email; return $this; }

    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(\DateTimeImmutable $createdAt): static { $this->createdAt = $createdAt; return $this; }

    public function getBio(): ?string { return $this->bio; }
    public function setBio(?string $bio): static
    {
        $bio = $bio !== null ? trim($bio) : null;
        $this->bio = $bio === '' ? null : $bio;
        return $this;
    }

    public function isPrivate(): bool { return $this->isPrivate; }
    public function setIsPrivate(bool $isPrivate): static { $this->isPrivate = $isPrivate; return $this; }

    public function eraseCredentials(): void {}

    public function __toString(): string { return $this->username ?? ''; }
}
