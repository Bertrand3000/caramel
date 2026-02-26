<?php

declare(strict_types=1);

namespace App\Interface;

use App\Entity\Commande;
use App\Enum\CommandeStatutEnum;

interface CommandeDecisionServiceInterface
{
    public function apply(Commande $commande, CommandeStatutEnum $status): bool;
}
