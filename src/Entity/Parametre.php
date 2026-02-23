<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ParametreRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ParametreRepository::class)]
#[ORM\Table(name: 'parametres')]
class Parametre
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100, unique: true)]
    private string $cle;

    #[ORM\Column(length: 255)]
    private string $valeur;

    public function getId(): ?int { return $this->id; }
    public function getCle(): string { return $this->cle; }
    public function setCle(string $cle): self { $this->cle = $cle; return $this; }
    public function getValeur(): string { return $this->valeur; }
    public function setValeur(string $valeur): self { $this->valeur = $valeur; return $this; }
}
