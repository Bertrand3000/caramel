<?php

declare(strict_types=1);

namespace App\Interface;

interface BoutiqueAccessCheckerInterface
{
    /**
     * @param list<string> $roles
     */
    public function isOpenForRoles(array $roles): bool;

    /**
     * @param list<string> $roles
     */
    public function assertOpenForRoles(array $roles): void;
}
