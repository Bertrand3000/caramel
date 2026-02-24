<?php

declare(strict_types=1);

namespace App\Interface;

use App\Entity\Commande;
use App\Entity\Creneau;
use App\Enum\ProfilUtilisateur;

interface CheckoutServiceInterface
{
    public function confirmCommande(
        string $sessionId,
        Creneau $creneau,
        ProfilUtilisateur $profil,
        ?string $numeroAgent = null,
    ): Commande;

    public function checkQuota(
        string $sessionId,
        ProfilUtilisateur $profil,
        ?string $numeroAgent = null,
    ): bool;

    public function assignCreneau(Commande $commande, Creneau $creneau): void;

    public function annulerCommande(Commande $commande): void;
}
