<?php

declare(strict_types=1);

namespace App\Service;

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
        if ($profil === ProfilUtilisateur::PARTENAIRE) {
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
        if ($profil === ProfilUtilisateur::PARTENAIRE) {
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
        $param = $this->parametreRepository->findOneByKey('quota_articles_max');

        return $param ? (int) $param->getValeur() : self::DEFAULT_QUOTA;
    }

    private function readExistingArticlesCount(
        string $sessionId,
        ProfilUtilisateur $profil,
        ?string $numeroAgent,
    ): int {
        if ($profil === ProfilUtilisateur::TELETRAVAILLEUR || $profil === ProfilUtilisateur::PUBLIC) {
            $normalizedNumeroAgent = trim((string) $numeroAgent);
            if (!preg_match(self::AGENT_NUMBER_PATTERN, $normalizedNumeroAgent)) {
                throw new \RuntimeException('Numero agent invalide (5 chiffres requis).');
            }

            return $this->commandeRepository->countArticlesActifsForNumeroAgent($normalizedNumeroAgent);
        }

        return $this->commandeRepository->countArticlesActifsForSession($sessionId);
    }
}
