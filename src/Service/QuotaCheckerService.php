<?php

declare(strict_types=1);

namespace App\Service;

use App\Enum\CommandeProfilEnum;
use App\Enum\ProfilUtilisateur;
use App\Repository\CommandeRepository;
use App\Repository\ParametreRepository;

class QuotaCheckerService
{
    private const DEFAULT_QUOTA = 3;
    private const AGENT_NUMBER_PATTERN = '/^\d{5}$/';

    public function __construct(
        private readonly ParametreRepository $parametreRepository,
        private readonly CommandeRepository $commandeRepository,
    ) {
    }

    public function check(
        string $sessionId,
        ProfilUtilisateur $profil,
        int $quantiteDemandee,
        ?string $numeroAgent = null,
    ): bool {
        if ($profil === ProfilUtilisateur::PARTENAIRE || $profil === ProfilUtilisateur::DMAX || $profil === ProfilUtilisateur::PUBLIC) {
            return true;
        }

        $quota = $this->readQuota();
        $existing = $this->readExistingArticlesCount($sessionId, $profil, $numeroAgent);

        return ($existing + $quantiteDemandee) <= $quota;
    }

    public function getQuotaRestant(
        string $sessionId,
        ProfilUtilisateur $profil,
        ?string $numeroAgent = null,
    ): int {
        if ($profil === ProfilUtilisateur::PARTENAIRE || $profil === ProfilUtilisateur::DMAX || $profil === ProfilUtilisateur::PUBLIC) {
            return PHP_INT_MAX;
        }

        $existing = $this->readExistingArticlesCount($sessionId, $profil, $numeroAgent);

        return max(0, $this->readQuota() - $existing);
    }

    /**
     * @param list<string> $roles
     */
    public function canAddMoreItems(array $roles, int $cartItemsCount): bool
    {
        if (in_array('ROLE_ADMIN', $roles, true) || in_array('ROLE_PARTENAIRE', $roles, true)) {
            return true;
        }

        return $cartItemsCount < $this->readQuota();
    }

    /**
     * @param list<string> $roles
     */
    public function getCartQuotaForRoles(array $roles): ?int
    {
        if (in_array('ROLE_ADMIN', $roles, true) || in_array('ROLE_PARTENAIRE', $roles, true)) {
            return null;
        }

        return $this->readQuota();
    }

    private function readQuota(): int
    {
        foreach (['max_produits_par_commande', 'quota_articles_max'] as $key) {
            $param = $this->parametreRepository->findOneByKey($key);
            if ($param === null) {
                continue;
            }

            $value = (int) $param->getValeur();
            if ($value > 0) {
                return $value;
            }
        }

        return self::DEFAULT_QUOTA;
    }

    private function readExistingArticlesCount(
        string $sessionId,
        ProfilUtilisateur $profil,
        ?string $numeroAgent,
    ): int {
        if ($profil === ProfilUtilisateur::TELETRAVAILLEUR) {
            $normalizedNumeroAgent = trim((string) $numeroAgent);
            if (!preg_match(self::AGENT_NUMBER_PATTERN, $normalizedNumeroAgent)) {
                throw new \RuntimeException('Numero agent invalide (5 chiffres requis).');
            }

            return $this->commandeRepository->countArticlesActifsForNumeroAgentEtProfil(
                $normalizedNumeroAgent,
                CommandeProfilEnum::TELETRAVAILLEUR,
            );
        }

        if ($profil === ProfilUtilisateur::PUBLIC) {
            // Pour le profil PUBLIC (admin/public sans numéro d'agent), on compte par session
            return $this->commandeRepository->countArticlesActifsForSession($sessionId);
        }

        // Pour PARTENAIRE et DMAX, pas de quota
        return 0;
    }
}
