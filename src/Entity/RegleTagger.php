<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\RegleTaggerRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RegleTaggerRepository::class)]
#[ORM\Table(name: 'regles_tagger')]
class RegleTagger
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $libelleContains = '';

    #[ORM\Column]
    private bool $tagTeletravailleur = false;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLibelleContains(): string
    {
        return $this->libelleContains;
    }

    public function setLibelleContains(string $libelleContains): self
    {
        $this->libelleContains = trim($libelleContains);

        return $this;
    }

    public function isTagTeletravailleur(): bool
    {
        return $this->tagTeletravailleur;
    }

    public function setTagTeletravailleur(bool $tagTeletravailleur): self
    {
        $this->tagTeletravailleur = $tagTeletravailleur;

        return $this;
    }
}
