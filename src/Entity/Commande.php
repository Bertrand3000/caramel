<?php

declare(strict_types=1);

namespace App\Entity;

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

    #[ORM\Column(length: 5, nullable: true)]
    private ?string $numeroAgent = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $nom = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $prenom = null;

    #[ORM\ManyToOne(inversedBy: 'commandes')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Creneau $creneau = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $dateValidation = null;

    #[ORM\Column(enumType: CommandeStatutEnum::class)]
    private CommandeStatutEnum $statut;

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
    }

    public function getId(): ?int
    {
        return $this->id;
    }
}
