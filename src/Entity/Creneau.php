<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\CreneauTypeEnum;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'creneaux')]
class Creneau
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private \DateTimeInterface $date;

    #[ORM\Column(type: Types::TIME_MUTABLE)]
    private \DateTimeInterface $heureDebut;

    #[ORM\Column(type: Types::TIME_MUTABLE)]
    private \DateTimeInterface $heureFin;

    #[ORM\Column]
    private int $capaciteMax = 10;

    #[ORM\Column]
    private int $capaciteUtilisee = 0;

    #[ORM\Column(enumType: CreneauTypeEnum::class)]
    private CreneauTypeEnum $type;

    #[ORM\OneToMany(mappedBy: 'creneau', targetEntity: Commande::class)]
    private Collection $commandes;

    public function __construct()
    {
        $this->commandes = new ArrayCollection();
        $this->type = CreneauTypeEnum::GENERAL;
    }

    public function getId(): ?int
    {
        return $this->id;
    }
}
