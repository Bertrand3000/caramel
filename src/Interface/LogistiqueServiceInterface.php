<?php

declare(strict_types=1);

namespace App\Interface;

use App\Entity\Commande;

interface LogistiqueServiceInterface
{
    /** @return list<Commande> */
    public function findTodayReadyOrders(): array;

    public function validateRetrait(Commande $commande): void;
}
