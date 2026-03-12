<?php

declare(strict_types=1);

namespace App\Controller;

use App\Interface\DocumentPdfGeneratorInterface;
use App\Interface\LogistiqueServiceInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_DMAX')]
final class LogistiqueBatchPdfController extends AbstractController
{
    #[Route('/logistique/bons/commandes.pdf', name: 'logistique_all_bons_commande_pdf', methods: ['GET'])]
    public function allBonCommandePdf(
        LogistiqueServiceInterface $logistiqueService,
        DocumentPdfGeneratorInterface $generator,
    ): Response {
        return $this->renderBatchPdf(
            $logistiqueService,
            fn (array $commandes): string => $generator->generateAllBonCommande($commandes),
            'tous-bons-commande',
        );
    }

    #[Route('/logistique/bons/preparations.pdf', name: 'logistique_all_bons_preparation_pdf', methods: ['GET'])]
    public function allBonPreparationPdf(
        LogistiqueServiceInterface $logistiqueService,
        DocumentPdfGeneratorInterface $generator,
    ): Response {
        return $this->renderBatchPdf(
            $logistiqueService,
            fn (array $commandes): string => $generator->generateAllBonPreparation($commandes),
            'tous-bons-preparation',
        );
    }

    #[Route('/logistique/bons/livraisons.pdf', name: 'logistique_all_bons_livraison_pdf', methods: ['GET'])]
    public function allBonLivraisonPdf(
        LogistiqueServiceInterface $logistiqueService,
        DocumentPdfGeneratorInterface $generator,
    ): Response {
        return $this->renderBatchPdf(
            $logistiqueService,
            fn (array $commandes): string => $generator->generateAllBonLivraison($commandes),
            'tous-bons-livraison',
        );
    }

    /**
     * @param \Closure(array): string $pdfGenerator
     */
    private function renderBatchPdf(
        LogistiqueServiceInterface $logistiqueService,
        \Closure $pdfGenerator,
        string $filePrefix,
    ): Response {
        $jour = $logistiqueService->findNextDeliveryDay();
        if ($jour === null) {
            return $this->redirectWithWarning();
        }

        $commandes = $logistiqueService->findAllOrdersForLogistique($jour);
        if ($commandes === []) {
            return $this->redirectWithWarning();
        }

        $pdf = $pdfGenerator($commandes);

        return new Response($pdf, Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => sprintf(
                'inline; filename="%s-%s.pdf"',
                $filePrefix,
                $jour->getDate()->format('Ymd'),
            ),
        ]);
    }

    private function redirectWithWarning(): RedirectResponse
    {
        $this->addFlash('warning', 'Aucune commande disponible pour la journée de livraison active.');

        return $this->redirectToRoute('logistique_index');
    }
}
