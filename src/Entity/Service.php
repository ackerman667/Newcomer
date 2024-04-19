<?php

namespace App\Entity;

use App\Repository\ServiceRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ServiceRepository::class)]
class Service
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $nom = null;

    #[ORM\Column(length: 255)]
    private ?string $valideur = null;

    #[ORM\Column]
    private ?int $id_service = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;

        return $this;
    }

    public function getValideur(): ?string
    {
        return $this->valideur;
    }

    public function setValideur(string $valideur): static
    {
        $this->valideur = $valideur;

        return $this;
    }

    public function getIdService(): ?int
    {
        return $this->id_service;
    }

    public function setIdService(int $id_service): static
    {
        $this->id_service = $id_service;

        return $this;
    }
}
