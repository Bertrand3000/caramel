<?php

declare(strict_types=1);

namespace App\Service;

use App\Exception\BoutiqueClosedException;
use App\Interface\BoutiqueAccessCheckerInterface;
use App\Repository\ParametreRepository;

final class BoutiqueAccessChecker implements BoutiqueAccessCheckerInterface
{
    public function __construct(private readonly ParametreRepository $parametreRepository)
    {
    }

    public function isOpenForRoles(array $roles): bool
    {
        if (in_array('ROLE_ADMIN', $roles, true)) {
            return true;
        }

        $keys = [];
        if (in_array('ROLE_AGENT', $roles, true)) {
            $keys[] = 'boutique_ouverte_agents';
        }
        if (in_array('ROLE_TELETRAVAILLEUR', $roles, true)) {
            $keys[] = 'boutique_ouverte_teletravailleurs';
        }
        if (in_array('ROLE_PARTENAIRE', $roles, true)) {
            $keys[] = 'boutique_ouverte_partenaires';
        }

        foreach ($keys as $key) {
            $param = $this->parametreRepository->findOneByKey($key);
            if ($param !== null && $param->getValeur() === '1') {
                return true;
            }
        }

        return false;
    }

    public function assertOpenForRoles(array $roles): void
    {
        if ($this->isOpenForRoles($roles)) {
            return;
        }

        throw new BoutiqueClosedException();
    }
}
