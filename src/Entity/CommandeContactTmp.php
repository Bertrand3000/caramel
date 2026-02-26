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

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $nomGrh = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $prenomGrh = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $telephone = null;

    #[ORM\Column(length: 100)]
    private string $importBatchId = '';

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

    public function getCommande(): Commande
    {
        return $this->commande;
    }

    public function setCommande(Commande $commande): self
    {
        $this->commande = $commande;

        return $this;
    }

    public function getNomGrh(): ?string
    {
        return $this->nomGrh;
    }

    public function setNomGrh(?string $nomGrh): self
    {
        $this->nomGrh = $nomGrh;

        return $this;
    }

    public function getPrenomGrh(): ?string
    {
        return $this->prenomGrh;
    }

    public function setPrenomGrh(?string $prenomGrh): self
    {
        $this->prenomGrh = $prenomGrh;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): self
    {
        $this->email = $email;

        return $this;
    }

    public function getTelephone(): ?string
    {
        return $this->telephone;
    }

    public function setTelephone(?string $telephone): self
    {
        $this->telephone = $telephone;

        return $this;
    }

    public function getImportBatchId(): string
    {
        return $this->importBatchId;
    }

    public function setImportBatchId(string $importBatchId): self
    {
        $this->importBatchId = $importBatchId;

        return $this;
    }

    public function getImportedAt(): \DateTimeImmutable
    {
        return $this->importedAt;
    }

    public function setImportedAt(\DateTimeImmutable $importedAt): self
    {
        $this->importedAt = $importedAt;

        return $this;
    }
}
