<?php

declare(strict_types=1);

namespace App\Interface;

use App\Entity\Commande;

interface BonLivraisonGeneratorInterface
{
    public function generatePrintHtml(Commande $commande): string;
}
