<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'paniers')]
class Panier
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'panier')]
    #[ORM\JoinColumn(nullable: false)]
    private Utilisateur $utilisateur;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $dateExpiration;

    #[ORM\OneToMany(mappedBy: 'panier', targetEntity: LignePanier::class)]
    private Collection $lignesPanier;

    public function __construct()
    {
        $this->lignesPanier = new ArrayCollection();
        $this->dateExpiration = new \DateTimeImmutable('+30 minutes');
    }

    public function getId(): ?int
    {
        return $this->id;
    }
}
