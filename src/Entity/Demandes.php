<?php

namespace App\Entity;

use App\Repository\DemandesRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
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

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $statuts = null;

    #[ORM\ManyToOne(inversedBy: 'demandes')]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $IDutilisateur = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $heureSoumission = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $titre = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $uid_valideur = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $token = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $tokenExpiration = null;

    

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $nom_remplacant = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $prenom_remplacant = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $telephone_remplacant = null;

    #[ORM\Column(nullable: true)]
    private ?bool $depart = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $affectation_remplacant = null;



    #[ORM\OneToMany(mappedBy: 'demande', targetEntity: HistoriqueDemande::class)]
    private Collection $historiqueDemandes;

    #[ORM\Column(nullable: true)]
    private ?bool $remplacant = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $commentaire = null;

    #[ORM\OneToMany(mappedBy: 'demande_id', targetEntity: Ressources::class)]
    private Collection $ressources;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $service = null;

    public function __construct()
    {
        $this->historiqueDemandes = new ArrayCollection();
        $this->ressources = new ArrayCollection();
    }

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

  

    public function getToken(): ?string
    {
        return $this->token;
    }

    public function setToken(?string $token): static
    {
        $this->token = $token;

        return $this;
    }

    public function getTokenExpiration(): ?\DateTimeImmutable
    {
        return $this->tokenExpiration;
    }

    public function setTokenExpiration(?\DateTimeImmutable $tokenExpiration): static
    {
        $this->tokenExpiration = $tokenExpiration;

        return $this;
    }

   

    public function getNomRemplacant(): ?string
    {
        return $this->nom_remplacant;
    }

    public function setNomRemplacant(?string $nom_remplacant): static
    {
        $this->nom_remplacant = $nom_remplacant;

        return $this;
    }

    public function getPrenomRemplacant(): ?string
    {
        return $this->prenom_remplacant;
    }

    public function setPrenomRemplacant(?string $prenom_remplacant): static
    {
        $this->prenom_remplacant = $prenom_remplacant;

        return $this;
    }

    public function getTelephoneRemplacant(): ?string
    {
        return $this->telephone_remplacant;
    }

    public function setTelephoneRemplacant(?string $telephone_remplacant): static
    {
        $this->telephone_remplacant = $telephone_remplacant;

        return $this;
    }

    public function isDepart(): ?bool
    {
        return $this->depart;
    }

    public function setDepart(?bool $depart): static
    {
        $this->depart = $depart;

        return $this;
    }

    public function getAffectationRemplacant(): ?string
    {
        return $this->affectation_remplacant;
    }

    public function setAffectationRemplacant(?string $affectation_remplacant): static
    {
        $this->affectation_remplacant = $affectation_remplacant;

        return $this;
    }

   

    /**
     * @return Collection<int, HistoriqueDemande>
     */
    public function getHistoriqueDemandes(): Collection
    {
        return $this->historiqueDemandes;
    }

    public function addHistoriqueDemande(HistoriqueDemande $historiqueDemande): static
    {
        if (!$this->historiqueDemandes->contains($historiqueDemande)) {
            $this->historiqueDemandes->add($historiqueDemande);
            $historiqueDemande->setDemande($this);
        }

        return $this;
    }

    public function removeHistoriqueDemande(HistoriqueDemande $historiqueDemande): static
    {
        if ($this->historiqueDemandes->removeElement($historiqueDemande)) {
            // set the owning side to null (unless already changed)
            if ($historiqueDemande->getDemande() === $this) {
                $historiqueDemande->setDemande(null);
            }
        }

        return $this;
    }

    public function isRemplacant(): ?bool
    {
        return $this->remplacant;
    }

    public function setRemplacant(?bool $remplacant): static
    {
        $this->remplacant = $remplacant;

        return $this;
    }

    public function getCommentaire(): ?string
    {
        return $this->commentaire;
    }

    public function setCommentaire(?string $commentaire): static
    {
        $this->commentaire = $commentaire;

        return $this;
    }

    /**
     * @return Collection<int, Ressources>
     */
    public function getRessources(): Collection
    {
        return $this->ressources;
    }

    public function addRessource(Ressources $ressource): static
    {
        if (!$this->ressources->contains($ressource)) {
            $this->ressources->add($ressource);
            $ressource->setDemandeId($this);
        }

        return $this;
    }

    public function removeRessource(Ressources $ressource): static
    {
        if ($this->ressources->removeElement($ressource)) {
            // set the owning side to null (unless already changed)
            if ($ressource->getDemandeId() === $this) {
                $ressource->setDemandeId(null);
            }
        }

        return $this;
    }

    public function getService(): ?string
    {
        return $this->service;
    }

    public function setService(?string $service): static
    {
        $this->service = $service;

        return $this;
    }
}
