<?php

namespace App\Entity;

use App\Repository\VoyageRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: VoyageRepository::class)]
#[ORM\Table(name: 'voyage')]
class Voyage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'idVoyage', type: 'integer')]
    private ?int $idVoyage = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $destination = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $pays = null;

    #[ORM\Column(name: 'dateDepart', type: 'string', length: 50, nullable: true)]
    private ?string $dateDepart = null;

    #[ORM\Column(name: 'dateRetour', type: 'string', length: 50, nullable: true)]
    private ?string $dateRetour = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $prix = null;

    #[ORM\Column(name: 'moyenTransport', type: 'string', length: 50, nullable: true)]
    private ?string $moyenTransport = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $hotel = null;

    #[ORM\Column(name: 'nbPlaces', type: 'integer', nullable: true)]
    private ?int $nbPlaces = null;

    #[ORM\Column(type: 'boolean', nullable: true)]
    private ?bool $disponible = null;

    public function getIdVoyage(): ?int
    {
        return $this->idVoyage;
    }

    public function setIdVoyage(int $idVoyage): self
    {
        $this->idVoyage = $idVoyage;

        return $this;
    }

    public function getDestination(): ?string
    {
        return $this->destination;
    }

    public function setDestination(?string $destination): self
    {
        $this->destination = $destination;

        return $this;
    }

    public function getPays(): ?string
    {
        return $this->pays;
    }

    public function setPays(?string $pays): self
    {
        $this->pays = $pays;

        return $this;
    }

    public function getDateDepart(): ?string
    {
        return $this->dateDepart;
    }

    public function setDateDepart(?string $dateDepart): self
    {
        $this->dateDepart = $dateDepart;

        return $this;
    }

    public function getDateRetour(): ?string
    {
        return $this->dateRetour;
    }

    public function setDateRetour(?string $dateRetour): self
    {
        $this->dateRetour = $dateRetour;

        return $this;
    }

    public function getPrix(): ?float
    {
        return $this->prix;
    }

    public function setPrix(?float $prix): self
    {
        $this->prix = $prix;

        return $this;
    }

    public function getMoyenTransport(): ?string
    {
        return $this->moyenTransport;
    }

    public function setMoyenTransport(?string $moyenTransport): self
    {
        $this->moyenTransport = $moyenTransport;

        return $this;
    }

    public function getHotel(): ?string
    {
        return $this->hotel;
    }

    public function setHotel(?string $hotel): self
    {
        $this->hotel = $hotel;

        return $this;
    }

    public function getNbPlaces(): ?int
    {
        return $this->nbPlaces;
    }

    public function setNbPlaces(?int $nbPlaces): self
    {
        $this->nbPlaces = $nbPlaces;

        return $this;
    }

    public function getDisponible(): ?bool
    {
        return $this->disponible;
    }

    public function setDisponible(?bool $disponible): self
    {
        $this->disponible = $disponible;

        return $this;
    }
}
