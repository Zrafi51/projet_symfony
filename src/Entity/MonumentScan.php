<?php

namespace App\Entity;

use App\Repository\MonumentScanRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MonumentScanRepository::class)]
#[ORM\HasLifecycleCallbacks]
class MonumentScan
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private int $userId;

    #[ORM\Column(length: 255)]
    private string $monumentName = '';

    #[ORM\Column(length: 255)]
    private string $city = '';

    #[ORM\Column(length: 255)]
    private string $country = '';

    #[ORM\Column(type: Types::TEXT)]
    private string $description = '';

    #[ORM\Column(length: 255)]
    private string $imageFilename = '';

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private bool $addedToRequest = false;

    #[ORM\Column(length: 50)]
    private string $scanStatus = 'pending'; // pending, completed, failed

    #[ORM\Column(type: Types::DECIMAL, precision: 3, scale: 2)]
    private float $confidenceScore = 0.0;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $apiProvider = null; // google_vision, openai, fallback

    public function __construct(int $userId)
    {
        $this->userId = $userId;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function setUserId(int $userId): static
    {
        $this->userId = $userId;

        return $this;
    }

    public function getMonumentName(): string
    {
        return $this->monumentName;
    }

    public function setMonumentName(string $monumentName): static
    {
        $this->monumentName = $monumentName;

        return $this;
    }

    public function getCity(): string
    {
        return $this->city;
    }

    public function setCity(string $city): static
    {
        $this->city = $city;

        return $this;
    }

    public function getCountry(): string
    {
        return $this->country;
    }

    public function setCountry(string $country): static
    {
        $this->country = $country;

        return $this;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getImageFilename(): string
    {
        return $this->imageFilename;
    }

    public function setImageFilename(string $imageFilename): static
    {
        $this->imageFilename = $imageFilename;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    #[ORM\PrePersist]
    public function setCreatedAt(): void
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function isAddedToRequest(): bool
    {
        return $this->addedToRequest;
    }

    public function setAddedToRequest(bool $addedToRequest): static
    {
        $this->addedToRequest = $addedToRequest;

        return $this;
    }

    public function getScanStatus(): string
    {
        return $this->scanStatus;
    }

    public function setScanStatus(string $scanStatus): static
    {
        $this->scanStatus = $scanStatus;

        return $this;
    }

    public function getConfidenceScore(): float
    {
        return $this->confidenceScore;
    }

    public function setConfidenceScore(float $confidenceScore): static
    {
        $this->confidenceScore = $confidenceScore;

        return $this;
    }

    public function getApiProvider(): ?string
    {
        return $this->apiProvider;
    }

    public function setApiProvider(?string $apiProvider): static
    {
        $this->apiProvider = $apiProvider;

        return $this;
    }

    /**
     * Get the public path to the image
     */
    public function getImagePath(): string
    {
        return '/uploads/monuments/' . $this->imageFilename;
    }

    /**
     * Get the absolute path to the image
     */
    public function getImageAbsolutePath(): string
    {
        return dirname(__DIR__, 2) . '/public/uploads/monuments/' . $this->imageFilename;
    }

    /**
     * Check if the scan was successful
     */
    public function isSuccessful(): bool
    {
        return $this->scanStatus === 'completed' && $this->monumentName !== '';
    }

    /**
     * Get formatted location (city, country)
     */
    public function getFormattedLocation(): string
    {
        if ($this->city && $this->country) {
            return $this->city . ', ' . $this->country;
        }
        return $this->city ?: $this->country;
    }
}
