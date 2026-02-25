<?php

declare(strict_types=1);

namespace App\Service;

use App\Enum\CommandeProfilEnum;
use App\Enum\ProfilUtilisateur;
use App\Exception\CommandeDejaExistanteException;
use App\Repository\CommandeRepository;

class CommandeLimitCheckerService
{
    public function __construct(
        private readonly CommandeRepository $commandeRepository,
    ) {
    }

    public function assertPeutCommander(string $numeroAgent, ProfilUtilisateur $profil): void
    {
        if ($profil === ProfilUtilisateur::PARTENAIRE || $profil === ProfilUtilisateur::DMAX) {
            return;
        }

        $profilCommande = match ($profil) {
            ProfilUtilisateur::TELETRAVAILLEUR => CommandeProfilEnum::TELETRAVAILLEUR,
            default => CommandeProfilEnum::AGENT,
        };

        if ($this->commandeRepository->hasCommandeActiveForNumeroAgentEtProfil($numeroAgent, $profilCommande)) {
            throw new CommandeDejaExistanteException('Vous ne pouvez commander qu\'une seule fois.');
        }
    }
}
