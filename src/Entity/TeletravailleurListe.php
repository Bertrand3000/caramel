<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\TeletravailleurListeRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TeletravailleurListeRepository::class)]
#[ORM\Table(name: 'teletravailleurs_liste')]
class TeletravailleurListe
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 5, unique: true)]
    private string $numeroAgent;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $nom = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $prenom = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNumeroAgent(): string
    {
        return $this->numeroAgent;
    }

    public function setNumeroAgent(string $numeroAgent): self
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
}
