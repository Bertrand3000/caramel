<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Commande;

interface PurgeServiceInterface
{
    public function anonymizeCommande(Commande $commande): void;
}
