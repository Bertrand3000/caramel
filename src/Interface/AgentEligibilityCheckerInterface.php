<?php

declare(strict_types=1);

namespace App\Interface;

use App\Entity\Utilisateur;

interface AgentEligibilityCheckerInterface
{
    public function assertAllowed(Utilisateur $utilisateur, string $numeroAgent): void;
}
