<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\ProduitEtatEnum;
use App\Enum\ProduitStatutEnum;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'produits')]
class Produit
{
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

    #[ORM\Column(nullable: true)]
    private ?float $largeur = null;

    #[ORM\Column(nullable: true)]
    private ?float $hauteur = null;

    #[ORM\Column(nullable: true)]
    private ?float $profondeur = null;

    #[ORM\Column(enumType: ProduitStatutEnum::class)]
    private ProduitStatutEnum $statut;

    #[ORM\OneToMany(mappedBy: 'produit', targetEntity: LigneCommande::class)]
    private Collection $lignesCommande;

    #[ORM\OneToMany(mappedBy: 'produit', targetEntity: LignePanier::class)]
    private Collection $lignesPanier;

    public function __construct()
    {
        $this->lignesCommande = new ArrayCollection();
        $this->lignesPanier = new ArrayCollection();
        $this->etat = ProduitEtatEnum::BON;
        $this->statut = ProduitStatutEnum::DISPONIBLE;
    }

    public function getId(): ?int
    {
        return $this->id;
    }
}
