<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\JourLivraisonRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: JourLivraisonRepository::class)]
#[ORM\Table(name: 'jours_livraison')]
#[ORM\Index(name: 'IDX_C8A833FFF00B1B0D', columns: ['date'])]
#[ORM\Index(name: 'IDX_C8A833FFB8755515', columns: ['actif'])]
class JourLivraison
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $date;

    #[ORM\Column]
    private bool $actif = true;

    #[ORM\Column(type: Types::TIME_MUTABLE)]
    private \DateTimeInterface $heureOuverture;

    #[ORM\Column(type: Types::TIME_MUTABLE)]
    private \DateTimeInterface $heureFermeture;

    #[ORM\Column]
    private bool $coupureMeridienne = false;

    #[ORM\Column(type: Types::TIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $heureCoupureDebut = null;

    #[ORM\Column(type: Types::TIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $heureCoupureFin = null;

    #[ORM\Column]
    private bool $exigerJourneePleine = false;

    #[ORM\OneToMany(mappedBy: 'jourLivraison', targetEntity: Creneau::class)]
    private Collection $creneaux;

    public function __construct()
    {
        $this->date = new \DateTimeImmutable('today');
        $this->heureOuverture = new \DateTimeImmutable('08:00:00');
        $this->heureFermeture = new \DateTimeImmutable('17:00:00');
        $this->creneaux = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDate(): \DateTimeImmutable
    {
        return $this->date;
    }

    public function setDate(\DateTimeImmutable $date): self
    {
        $this->date = $date;

        return $this;
    }

    public function isActif(): bool
    {
        return $this->actif;
    }

    public function setActif(bool $actif): self
    {
        $this->actif = $actif;

        return $this;
    }

    public function getHeureOuverture(): \DateTimeInterface
    {
        return $this->heureOuverture;
    }

    public function setHeureOuverture(\DateTimeInterface $heureOuverture): self
    {
        $this->heureOuverture = $heureOuverture;

        return $this;
    }

    public function getHeureFermeture(): \DateTimeInterface
    {
        return $this->heureFermeture;
    }

    public function setHeureFermeture(\DateTimeInterface $heureFermeture): self
    {
        $this->heureFermeture = $heureFermeture;

        return $this;
    }

    public function isCoupureMeridienne(): bool
    {
        return $this->coupureMeridienne;
    }

    public function setCoupureMeridienne(bool $coupureMeridienne): self
    {
        $this->coupureMeridienne = $coupureMeridienne;

        return $this;
    }

    public function getHeureCoupureDebut(): ?\DateTimeInterface
    {
        return $this->heureCoupureDebut;
    }

    public function setHeureCoupureDebut(?\DateTimeInterface $heureCoupureDebut): self
    {
        $this->heureCoupureDebut = $heureCoupureDebut;

        return $this;
    }

    public function getHeureCoupureFin(): ?\DateTimeInterface
    {
        return $this->heureCoupureFin;
    }

    public function setHeureCoupureFin(?\DateTimeInterface $heureCoupureFin): self
    {
        $this->heureCoupureFin = $heureCoupureFin;

        return $this;
    }

    public function isExigerJourneePleine(): bool
    {
        return $this->exigerJourneePleine;
    }

    public function setExigerJourneePleine(bool $exigerJourneePleine): self
    {
        $this->exigerJourneePleine = $exigerJourneePleine;

        return $this;
    }

    /** @return Collection<int, Creneau> */
    public function getCreneaux(): Collection
    {
        return $this->creneaux;
    }

    public function addCreneau(Creneau $creneau): self
    {
        if (!$this->creneaux->contains($creneau)) {
            $this->creneaux->add($creneau);
            $creneau->setJourLivraison($this);
        }

        return $this;
    }

    public function removeCreneau(Creneau $creneau): self
    {
        if ($this->creneaux->removeElement($creneau) && $creneau->getJourLivraison() === $this) {
            $creneau->setJourLivraison(null);
        }

        return $this;
    }
}
