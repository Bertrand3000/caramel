<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Commande;
use App\Enum\CommandeProfilEnum;
use App\Enum\ProfilUtilisateur;
use App\Interface\ToutDoitDisparaitreServiceInterface;
use App\Repository\CommandeRepository;
use App\Repository\JourLivraisonRepository;
use App\Repository\ParametreRepository;

final class ToutDoitDisparaitreService implements ToutDoitDisparaitreServiceInterface
{
    private const PARAM_KEY = 'mode_tout_doit_disparaitre';

    public function __construct(
        private readonly ParametreRepository $parametreRepository,
        private readonly JourLivraisonRepository $jourLivraisonRepository,
        private readonly CommandeRepository $commandeRepository,
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->parametreRepository->findOneByKey(self::PARAM_KEY)?->getValeur() === '1';
    }

    public function isEnabledForProfil(ProfilUtilisateur $profil): bool
    {
        return $this->isEnabled() && $profil === ProfilUtilisateur::PUBLIC;
    }

    public function findCommandeACompleter(string $numeroAgent, ProfilUtilisateur $profil): ?Commande
    {
        $numeroAgent = trim($numeroAgent);
        if ($numeroAgent === '' || !$this->isEnabledForProfil($profil)) {
            return null;
        }

        $jour = $this->jourLivraisonRepository->findNextOpenDeliveryDayFrom(new \DateTimeImmutable('tomorrow'));
        if ($jour === null) {
            return null;
        }

        return $this->commandeRepository->findDerniereCommandeActiveAvecCreneauPourNumeroAgentEtProfilLeJour(
            $numeroAgent,
            $this->mapProfilCommande($profil),
            $jour->getDate(),
        );
    }

    private function mapProfilCommande(ProfilUtilisateur $profil): CommandeProfilEnum
    {
        return match ($profil) {
            ProfilUtilisateur::DMAX => CommandeProfilEnum::DMAX,
            ProfilUtilisateur::PARTENAIRE => CommandeProfilEnum::PARTENAIRE,
            ProfilUtilisateur::TELETRAVAILLEUR => CommandeProfilEnum::TELETRAVAILLEUR,
            default => CommandeProfilEnum::AGENT,
        };
    }
}
