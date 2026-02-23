<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'lignes_panier')]
class LignePanier
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'lignesPanier')]
    #[ORM\JoinColumn(nullable: false)]
    private Panier $panier;

    #[ORM\ManyToOne(inversedBy: 'lignesPanier')]
    #[ORM\JoinColumn(nullable: false)]
    private Produit $produit;

    public function getId(): ?int
    {
        return $this->id;
    }
}
