<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Commande;
use App\Interface\DocumentPdfGeneratorInterface;
use Dompdf\Dompdf;
use Dompdf\Options;
use Twig\Environment;

/**
 * Génère les documents PDF (bon de commande, bon de préparation, bon de livraison)
 * en utilisant Dompdf pour la conversion HTML→PDF et Twig pour le rendu HTML.
 *
 * Stratégie pagination bon de préparation (>3 produits) :
 * On regroupe les produits par tranches de 3 et on génère une section par page.
 * Dompdf gère le saut de page via CSS `page-break-after: always`.
 */
final class DocumentPdfGenerator implements DocumentPdfGeneratorInterface
{
    public function __construct(
        private readonly Environment $twig,
    ) {
    }

    public function generateBonCommande(Commande $commande): string
    {
        $html = $this->twig->render('logistique/pdf/bon_commande.html.twig', [
            'commande' => $commande,
        ]);

        return $this->renderPdf($html);
    }

    public function generateBonPreparation(Commande $commande): string
    {
        $lignes = $commande->getLignesCommande()->toArray();

        // Pagination : 3 produits par page
        $pages = array_chunk($lignes, 3);

        // S'il n'y a aucune ligne, on affiche une page vide avec 3 sections vides
        if ($pages === []) {
            $pages = [[]];
        }

        $html = $this->twig->render('logistique/pdf/bon_preparation.html.twig', [
            'commande' => $commande,
            'pages'    => $pages,
        ]);

        return $this->renderPdf($html);
    }

    public function generateBonLivraison(Commande $commande): string
    {
        $html = $this->twig->render('logistique/pdf/bon_livraison.html.twig', [
            'commande' => $commande,
        ]);

        return $this->renderPdf($html);
    }

    private function renderPdf(string $html): string
    {
        $options = new Options();
        $options->set('defaultFont', 'Helvetica');
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return (string) $dompdf->output();
    }
}
