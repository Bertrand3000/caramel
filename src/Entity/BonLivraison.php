<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'bons_livraison')]
class BonLivraison
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'bonLivraison')]
    #[ORM\JoinColumn(nullable: false)]
    private Commande $commande;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $dateImpression;

    #[ORM\Column]
    private bool $signe = false;

    public function __construct()
    {
        $this->dateImpression = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }
}
