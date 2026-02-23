<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'commande_contacts_tmp')]
class CommandeContactTmp
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'commandeContactTmp')]
    #[ORM\JoinColumn(nullable: false)]
    private Commande $commande;

    #[ORM\Column(length: 255)]
    private string $email;

    #[ORM\Column(length: 30)]
    private string $telephone;

    #[ORM\Column(length: 100)]
    private string $importBatchId;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $importedAt;

    public function __construct()
    {
        $this->importedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }
}
