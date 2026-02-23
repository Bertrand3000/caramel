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

    public function getId(): ?int { return $this->id; }
    public function getNumeroAgent(): string { return $this->numeroAgent; }
    public function setNumeroAgent(string $numeroAgent): self { $this->numeroAgent = $numeroAgent; return $this; }
}
