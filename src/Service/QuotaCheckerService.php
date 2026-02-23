<?php

declare(strict_types=1);

namespace App\Service;

use App\Enum\ProfilUtilisateur;
use App\Repository\CommandeRepository;
use App\Repository\ParametreRepository;

class QuotaCheckerService
{
    private const DEFAULT_QUOTA = 3;

    public function __construct(
        private readonly ParametreRepository $parametreRepository,
        private readonly CommandeRepository $commandeRepository,
    ) {
    }

    public function check(string $sessionId, ProfilUtilisateur $profil, int $quantiteDemandee): bool
    {
        if ($profil === ProfilUtilisateur::PARTENAIRE) {
            return true;
        }

        $quota = $this->readQuota();
        $existing = $this->commandeRepository->countArticlesActifsForSession($sessionId);

        return ($existing + $quantiteDemandee) <= $quota;
    }

    public function getQuotaRestant(string $sessionId, ProfilUtilisateur $profil): int
    {
        if ($profil === ProfilUtilisateur::PARTENAIRE) {
            return PHP_INT_MAX;
        }

        return max(0, $this->readQuota() - $this->commandeRepository->countArticlesActifsForSession($sessionId));
    }

    private function readQuota(): int
    {
        $param = $this->parametreRepository->findOneByKey('quota_articles_max');

        return $param ? (int) $param->getValeur() : self::DEFAULT_QUOTA;
    }
}
