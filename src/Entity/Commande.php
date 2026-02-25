<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\CommandeProfilEnum;
use App\Enum\CommandeStatutEnum;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'commandes')]
class Commande
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'commandes')]
    #[ORM\JoinColumn(nullable: false)]
    private Utilisateur $utilisateur;

    #[ORM\Column(length: 255)]
    private string $sessionId = '';

    #[ORM\Column(length: 5, nullable: true)]
    private ?string $numeroAgent = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $nom = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $prenom = null;

    #[ORM\ManyToOne(inversedBy: 'commandes')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Creneau $creneau = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $dateValidation;

    #[ORM\Column(enumType: CommandeStatutEnum::class)]
    private CommandeStatutEnum $statut;

    #[ORM\Column(length: 20, enumType: CommandeProfilEnum::class)]
    private CommandeProfilEnum $profilCommande;

    #[ORM\OneToMany(mappedBy: 'commande', targetEntity: LigneCommande::class)]
    private Collection $lignesCommande;

    #[ORM\OneToOne(mappedBy: 'commande', targetEntity: BonLivraison::class)]
    private ?BonLivraison $bonLivraison = null;

    #[ORM\OneToOne(mappedBy: 'commande', targetEntity: CommandeContactTmp::class)]
    private ?CommandeContactTmp $commandeContactTmp = null;

    public function __construct()
    {
        $this->lignesCommande = new ArrayCollection();
        $this->statut = CommandeStatutEnum::EN_ATTENTE_VALIDATION;
        $this->profilCommande = CommandeProfilEnum::AGENT;
        $this->dateValidation = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUtilisateur(): Utilisateur
    {
        return $this->utilisateur;
    }

    public function setUtilisateur(Utilisateur $utilisateur): self
    {
        $this->utilisateur = $utilisateur;

        return $this;
    }

    public function getSessionId(): string
    {
        return $this->sessionId;
    }

    public function setSessionId(string $sessionId): self
    {
        $this->sessionId = $sessionId;

        return $this;
    }

    public function getNumeroAgent(): ?string
    {
        return $this->numeroAgent;
    }

    public function setNumeroAgent(?string $numeroAgent): self
    {
        $this->numeroAgent = $numeroAgent;

        return $this;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(?string $nom): self
    {
        $this->nom = $nom;

        return $this;
    }

    public function getPrenom(): ?string
    {
        return $this->prenom;
    }

    public function setPrenom(?string $prenom): self
    {
        $this->prenom = $prenom;

        return $this;
    }

    public function getCreneau(): ?Creneau
    {
        return $this->creneau;
    }

    public function setCreneau(?Creneau $creneau): self
    {
        $this->creneau = $creneau;

        return $this;
    }

    public function getStatut(): CommandeStatutEnum
    {
        return $this->statut;
    }

    public function setStatut(CommandeStatutEnum $statut): self
    {
        $this->statut = $statut;

        return $this;
    }

    public function getStatutValue(): string
    {
        return $this->statut->value;
    }

    public function setStatutValue(string $statutValue): self
    {
        $this->statut = CommandeStatutEnum::from($statutValue);

        return $this;
    }

    public function getProfilCommande(): CommandeProfilEnum
    {
        return $this->profilCommande;
    }

    public function setProfilCommande(CommandeProfilEnum $profilCommande): self
    {
        $this->profilCommande = $profilCommande;

        return $this;
    }

    /** @return Collection<int, LigneCommande> */
    public function getLignesCommande(): Collection
    {
        return $this->lignesCommande;
    }

    public function setDateValidation(\DateTimeInterface $dateValidation): self
    {
        $this->dateValidation = $dateValidation;

        return $this;
    }

    public function getDateValidation(): \DateTimeInterface
    {
        return $this->dateValidation;
    }

    public function getBonLivraison(): ?BonLivraison
    {
        return $this->bonLivraison;
    }

    public function setBonLivraison(?BonLivraison $bonLivraison): self
    {
        $this->bonLivraison = $bonLivraison;

        return $this;
    }

    public function getCommandeContactTmp(): ?CommandeContactTmp
    {
        return $this->commandeContactTmp;
    }

    public function setCommandeContactTmp(?CommandeContactTmp $commandeContactTmp): self
    {
        $this->commandeContactTmp = $commandeContactTmp;

        return $this;
    }
}
