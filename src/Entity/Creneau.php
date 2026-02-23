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

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $dateHeure;

    #[ORM\Column(type: Types::TIME_MUTABLE)]
    private \DateTimeInterface $heureDebut;

    #[ORM\Column(type: Types::TIME_MUTABLE)]
    private \DateTimeInterface $heureFin;

    #[ORM\Column]
    private int $capaciteMax = 10;

    #[ORM\Column]
    private int $capaciteUtilisee = 0;

    #[ORM\Column(enumType: CreneauTypeEnum::class)]
    private CreneauTypeEnum $type = CreneauTypeEnum::GENERAL;


    #[ORM\OneToMany(mappedBy: 'creneau', targetEntity: Commande::class)]
    private Collection $commandes;

    public function __construct()
    {
        $this->dateHeure = new \DateTimeImmutable();
        $this->heureDebut = new \DateTimeImmutable('08:00:00');
        $this->heureFin = new \DateTimeImmutable('08:30:00');
        $this->commandes = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDateHeure(): \DateTimeImmutable
    {
        return $this->dateHeure;
    }

    public function setDateHeure(\DateTimeImmutable $dateHeure): self
    {
        $this->dateHeure = $dateHeure;

        return $this;
    }

    public function getHeureDebut(): \DateTimeInterface
    {
        return $this->heureDebut;
    }

    public function setHeureDebut(\DateTimeInterface $heureDebut): self
    {
        $this->heureDebut = $heureDebut;

        return $this;
    }

    public function getHeureFin(): \DateTimeInterface
    {
        return $this->heureFin;
    }

    public function setHeureFin(\DateTimeInterface $heureFin): self
    {
        $this->heureFin = $heureFin;

        return $this;
    }

    public function getCapaciteMax(): int
    {
        return $this->capaciteMax;
    }

    public function setCapaciteMax(int $capaciteMax): self
    {
        $this->capaciteMax = $capaciteMax;

        return $this;
    }

    public function getCapaciteUtilisee(): int
    {
        return $this->capaciteUtilisee;
    }

    public function setCapaciteUtilisee(int $capaciteUtilisee): self
    {
        $this->capaciteUtilisee = $capaciteUtilisee;

        return $this;
    }


    /** @return Collection<int, Commande> */
    public function getCommandes(): Collection
    {
        return $this->commandes;
    }

    public function getType(): CreneauTypeEnum
    {
        return $this->type;
    }

    public function setType(CreneauTypeEnum $type): self
    {
        $this->type = $type;

        return $this;
    }
}
