<?php

declare(strict_types=1);

namespace App\Service;

use App\Enum\CommandeProfilEnum;
use App\Enum\ProfilUtilisateur;
use App\Exception\CommandeDejaExistanteException;
use App\Repository\CommandeRepository;
use App\Repository\ParametreRepository;

class CommandeLimitCheckerService
{
    public function __construct(
        private readonly CommandeRepository $commandeRepository,
        private readonly ParametreRepository $parametreRepository,
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
        $maxCommandes = $this->resolveMaxCommandes($profilCommande);
        $countActive = $this->commandeRepository->countCommandesActivesForNumeroAgentEtProfil($numeroAgent, $profilCommande);

        if ($countActive >= $maxCommandes) {
            throw new CommandeDejaExistanteException(sprintf(
                'Vous avez atteint la limite de %d commande(s) pour ce profil.',
                $maxCommandes,
            ));
        }
    }

    private function resolveMaxCommandes(CommandeProfilEnum $profil): int
    {
        $key = $profil === CommandeProfilEnum::TELETRAVAILLEUR
            ? 'max_commandes_teletravailleurs'
            : 'max_commandes_agents';
        $default = $profil === CommandeProfilEnum::TELETRAVAILLEUR ? 1 : 2;

        $rawValue = $this->parametreRepository->findOneByKey($key)?->getValeur();
        $value = is_string($rawValue) ? (int) $rawValue : $default;

        return max(1, $value);
    }
}
