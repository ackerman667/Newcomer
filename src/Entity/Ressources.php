<?php

namespace App\Entity;

use App\Repository\RessourcesRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RessourcesRepository::class)]
class Ressources
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?bool $emailaca = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function isEmailaca(): ?bool
    {
        return $this->emailaca;
    }

    public function setEmailaca(bool $emailaca): static
    {
        $this->emailaca = $emailaca;

        return $this;
    }
}
