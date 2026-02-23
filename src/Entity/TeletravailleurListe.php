<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'teletravailleurs_liste')]
class TeletravailleurListe
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 5, unique: true)]
    private string $numeroAgent;

    public function getId(): ?int
    {
        return $this->id;
    }
}
