<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\PartenaireTypeEnum;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'partenaires')]
class Partenaire
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'partenaire')]
    #[ORM\JoinColumn(nullable: false)]
    private Utilisateur $utilisateur;

    #[ORM\Column(length: 255)]
    private string $nom;

    #[ORM\Column(enumType: PartenaireTypeEnum::class)]
    private PartenaireTypeEnum $type;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $contact = null;

    public function getId(): ?int
    {
        return $this->id;
    }
}
