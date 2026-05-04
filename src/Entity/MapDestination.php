<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'map_destinations')]
class MapDestination
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 100)]
    private ?string $city = null;

    #[ORM\Column(type: 'string', length: 100)]
    private ?string $country = null;

    #[ORM\Column(type: 'string', length: 50)]
    private ?string $continent = null;

    #[ORM\Column(type: 'string', length: 150)]
    private ?string $packageName = null;

    #[ORM\Column(name: 'duration', type: 'string', length: 50)]
    private ?string $duration = null;

    #[ORM\Column(name: 'price', type: 'string', length: 50)]
    private ?string $price = null;

    #[ORM\Column(name: 'original_price', type: 'string', length: 50)]
    private ?string $originalPrice = null;

    #[ORM\Column(name: 'image_path', type: 'string', length: 255)]
    private ?string $imagePath = null;

    #[ORM\Column(type: 'text')]
    private ?string $description = null;

    #[ORM\Column(type: 'string', length: 100)]
    private ?string $bestPeriod = null;

    #[ORM\Column(type: 'text')]
    private ?string $includes = null;

    #[ORM\Column(name: 'highlight_1', type: 'text')]
    private ?string $highlight1 = null;

    #[ORM\Column(name: 'highlight_2', type: 'text')]
    private ?string $highlight2 = null;

    #[ORM\Column(name: 'highlight_3', type: 'text')]
    private ?string $highlight3 = null;

    #[ORM\Column(type: 'decimal', precision: 5, scale: 3)]
    private ?float $xPercent = null;

    #[ORM\Column(type: 'decimal', precision: 5, scale: 3)]
    private ?float $yPercent = null;

    #[ORM\Column(name: 'ai_score', type: 'decimal', precision: 5, scale: 2)]
    private ?float $aiScore = null;

    #[ORM\Column(name: 'ai_recommended', type: 'boolean')]
    private ?bool $aiRecommended = null;

    #[ORM\Column(name: 'is_active', type: 'boolean')]
    private ?bool $isActive = null;

    #[ORM\Column(name: 'display_order', type: 'integer')]
    private ?int $displayOrder = null;

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private ?\DateTimeInterface $createdAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setCity(string $city): self
    {
        $this->city = $city;

        return $this;
    }

    public function getCountry(): ?string
    {
        return $this->country;
    }

    public function setCountry(string $country): self
    {
        $this->country = $country;

        return $this;
    }

    public function getImagePath(): ?string
    {
        return $this->imagePath;
    }

    public function setImagePath(string $imagePath): self
    {
        $this->imagePath = $imagePath;

        return $this;
    }

    public function getIsActive(): ?bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): self
    {
        $this->isActive = $isActive;

        return $this;
    }
}
