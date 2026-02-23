<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity]
#[ORM\Table(name: 'utilisateurs')]
class Utilisateur implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    private string $login;

    #[ORM\Column]
    private string $password;

    /** @var list<string> */
    #[ORM\Column(type: 'json')]
    private array $roles = [];

    #[ORM\Column]
    private bool $actif = true;

    #[ORM\OneToOne(mappedBy: 'utilisateur', targetEntity: Partenaire::class)]
    private ?Partenaire $partenaire = null;

    #[ORM\OneToMany(mappedBy: 'utilisateur', targetEntity: Commande::class)]
    private Collection $commandes;

    #[ORM\OneToOne(mappedBy: 'utilisateur', targetEntity: Panier::class)]
    private ?Panier $panier = null;

    public function __construct()
    {
        $this->commandes = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUserIdentifier(): string
    {
        return $this->login;
    }

    public function getRoles(): array
    {
        return array_values(array_unique($this->roles));
    }

    public function eraseCredentials(): void
    {
    }

    public function getPassword(): string
    {
        return $this->password;
    }
}
