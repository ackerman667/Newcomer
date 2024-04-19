<?php

namespace App\Entity;

use App\Repository\DemandesRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DemandesRepository::class)]
class Demandes
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $date = null;

    #[ORM\Column(length: 255)]
    private ?string $statuts = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $dateAutorisation = null;

    #[ORM\ManyToOne(inversedBy: 'demandes')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $IDutilisateur = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $heureSoumission = null;

    #[ORM\Column(length: 255)]
    private ?string $titre = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $uid_valideur = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDate(): ?\DateTimeInterface
    {
        return $this->date;
    }

    public function setDate(\DateTimeInterface $date): static
    {
        $this->date = $date;

        return $this;
    }

    public function getStatuts(): ?string
    {
        return $this->statuts;
    }

    public function setStatuts(string $statuts): static
    {
        $this->statuts = $statuts;

        return $this;
    }

    public function getDateAutorisation(): ?\DateTimeInterface
    {
        return $this->dateAutorisation;
    }

    public function setDateAutorisation(\DateTimeInterface $dateAutorisation): static
    {
        $this->dateAutorisation = $dateAutorisation;

        return $this;
    }

    public function getIDutilisateur(): ?User
    {
        return $this->IDutilisateur;
    }

    public function setIDutilisateur(?User $IDutilisateur): static
    {
        $this->IDutilisateur = $IDutilisateur;

        return $this;
    }

    public function getHeureSoumission(): ?\DateTimeInterface
    {
        return $this->heureSoumission;
    }

    public function setHeureSoumission(\DateTimeInterface $heureSoumission): static
    {
        $this->heureSoumission = $heureSoumission;

        return $this;
    }

    public function getTitre(): ?string
    {
        return $this->titre;
    }

    public function setTitre(string $titre): static
    {
        $this->titre = $titre;

        return $this;
    }

    public function getUidValideur(): ?string
    {
        return $this->uid_valideur;
    }

    public function setUidValideur(?string $uid_valideur): static
    {
        $this->uid_valideur = $uid_valideur;

        return $this;
    }
}
