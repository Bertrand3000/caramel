<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\ProduitEtatEnum;
use App\Enum\ProduitStatutEnum;
use App\Repository\ProduitRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProduitRepository::class)]
#[ORM\Table(name: 'produits')]
class Produit
{
    public function __construct()
    {
        $this->lignesCommande = new ArrayCollection();
        $this->lignesPanier = new ArrayCollection();
        $this->etat = ProduitEtatEnum::BON;
    }

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $numeroInventaire = null;

    #[ORM\Column(length: 255)]
    private string $libelle;

    #[ORM\Column(length: 255)]
    private string $photoProduit;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $photoNumeroInventaire = null;

    #[ORM\Column(enumType: ProduitEtatEnum::class)]
    private ProduitEtatEnum $etat;

    #[ORM\Column]
    private bool $tagTeletravailleur = false;

    #[ORM\Column(length: 20)]
    private string $etage;

    #[ORM\Column(length: 20)]
    private string $porte;

    #[ORM\Column]
    private float $largeur;

    #[ORM\Column]
    private float $hauteur;

    #[ORM\Column]
    private float $profondeur;

    #[ORM\Column(enumType: ProduitStatutEnum::class)]
    private ProduitStatutEnum $statut = ProduitStatutEnum::DISPONIBLE;

    #[ORM\Column(options: ['default' => 1])]
    private int $quantite = 1;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;


    #[ORM\OneToMany(mappedBy: 'produit', targetEntity: LigneCommande::class)]
    private iterable $lignesCommande;

    #[ORM\OneToMany(mappedBy: 'produit', targetEntity: LignePanier::class)]
    private iterable $lignesPanier;

    public function getId(): ?int { return $this->id; }
    public function getNumeroInventaire(): ?string { return $this->numeroInventaire; }
    public function setNumeroInventaire(?string $numeroInventaire): self { $this->numeroInventaire = $numeroInventaire; return $this; }
    public function getLibelle(): string { return $this->libelle; }
    public function setLibelle(string $libelle): self { $this->libelle = $libelle; return $this; }
    public function getPhotoProduit(): string { return $this->photoProduit; }
    public function setPhotoProduit(string $photoProduit): self { $this->photoProduit = $photoProduit; return $this; }
    public function getPhotoNumeroInventaire(): ?string { return $this->photoNumeroInventaire; }
    public function setPhotoNumeroInventaire(?string $photoNumeroInventaire): self { $this->photoNumeroInventaire = $photoNumeroInventaire; return $this; }
    public function getPhotoProduitPublicPath(): string
    {
        if (str_starts_with($this->photoProduit, '/uploads/') || str_starts_with($this->photoProduit, 'uploads/')) {
            return ltrim($this->photoProduit, '/');
        }

        return 'uploads/produits/'.ltrim($this->photoProduit, '/');
    }
    public function getPhotoNumeroInventairePublicPath(): ?string
    {
        if ($this->photoNumeroInventaire === null || $this->photoNumeroInventaire === '') {
            return null;
        }
        if (str_starts_with($this->photoNumeroInventaire, '/uploads/') || str_starts_with($this->photoNumeroInventaire, 'uploads/')) {
            return ltrim($this->photoNumeroInventaire, '/');
        }

        return 'uploads/inventaire/'.ltrim($this->photoNumeroInventaire, '/');
    }
    public function getEtat(): ProduitEtatEnum { return $this->etat; }
    public function setEtat(ProduitEtatEnum $etat): self { $this->etat = $etat; return $this; }
    public function isTagTeletravailleur(): bool { return $this->tagTeletravailleur; }
    public function setTagTeletravailleur(bool $tagTeletravailleur): self { $this->tagTeletravailleur = $tagTeletravailleur; return $this; }
    public function getEtage(): string { return $this->etage; }
    public function setEtage(string $etage): self { $this->etage = $etage; return $this; }
    public function getPorte(): string { return $this->porte; }
    public function setPorte(string $porte): self { $this->porte = $porte; return $this; }
    public function getLargeur(): float { return $this->largeur; }
    public function setLargeur(float $largeur): self { $this->largeur = $largeur; return $this; }
    public function getHauteur(): float { return $this->hauteur; }
    public function setHauteur(float $hauteur): self { $this->hauteur = $hauteur; return $this; }
    public function getProfondeur(): float { return $this->profondeur; }
    public function setProfondeur(float $profondeur): self { $this->profondeur = $profondeur; return $this; }
    public function getStatut(): ProduitStatutEnum { return $this->statut; }
    public function setStatut(ProduitStatutEnum $statut): self { $this->statut = $statut; return $this; }
    public function getQuantite(): int { return $this->quantite; }
    public function setQuantite(int $quantite): self { $this->quantite = $quantite; return $this; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): self { $this->description = $description; return $this; }
}
