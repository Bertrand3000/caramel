<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Commande;
use App\Interface\BonLivraisonGeneratorInterface;
use Twig\Environment;

final class BonLivraisonGenerator implements BonLivraisonGeneratorInterface
{
    public function __construct(
        private readonly Environment $twig,
    ) {
    }

    public function generatePrintHtml(Commande $commande): string
    {
        return $this->twig->render('logistique/bon_livraison_print.html.twig', [
            'commande' => $commande,
        ]);
    }
}
