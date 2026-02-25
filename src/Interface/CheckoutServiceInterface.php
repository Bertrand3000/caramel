<?php

declare(strict_types=1);

namespace App\Interface;

use App\Entity\Commande;
use App\Entity\Creneau;
use App\Entity\Utilisateur;
use App\Enum\ProfilUtilisateur;

interface CheckoutServiceInterface
{
    public function hasItems(string $sessionId): bool;

    public function confirmCommande(
        string $sessionId,
        Creneau $creneau,
        ProfilUtilisateur $profil,
        Utilisateur $utilisateur,
        ?string $numeroAgent = null,
        ?string $nom = null,
        ?string $prenom = null,
    ): Commande;

    public function checkQuota(
        string $sessionId,
        ProfilUtilisateur $profil,
        ?string $numeroAgent = null,
    ): bool;

    public function assignCreneau(Commande $commande, Creneau $creneau): void;

    public function annulerCommande(Commande $commande): void;
}
