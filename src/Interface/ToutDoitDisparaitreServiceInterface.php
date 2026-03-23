<?php

declare(strict_types=1);

namespace App\Interface;

use App\Entity\Commande;
use App\Enum\ProfilUtilisateur;

interface ToutDoitDisparaitreServiceInterface
{
    public function isEnabled(): bool;

    public function isEnabledForProfil(ProfilUtilisateur $profil): bool;

    public function findCommandeACompleter(string $numeroAgent, ProfilUtilisateur $profil): ?Commande;
}
