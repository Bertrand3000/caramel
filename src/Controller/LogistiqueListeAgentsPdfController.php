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
final class LogistiqueListeAgentsPdfController extends AbstractController
{
    #[Route('/logistique/liste-agents.pdf', name: 'logistique_liste_agents_pdf', methods: ['GET'])]
    public function listeAgentsPdf(
        LogistiqueServiceInterface $logistiqueService,
        DocumentPdfGeneratorInterface $generator,
    ): Response {
        $jour = $logistiqueService->findNextDeliveryDay();
        if ($jour === null) {
            return $this->redirectWithWarning();
        }

        $commandes = $logistiqueService->findAgentOrdersForLogistique($jour);
        if ($commandes === []) {
            return $this->redirectWithWarning();
        }

        $pdf = $generator->generateListeAgents($jour, $commandes);

        return new Response($pdf, Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => sprintf(
                'inline; filename="liste-agents-%s.pdf"',
                $jour->getDate()->format('Ymd'),
            ),
        ]);
    }

    private function redirectWithWarning(): RedirectResponse
    {
        $this->addFlash('warning', 'Aucun agent à lister pour la journée de livraison active.');

        return $this->redirectToRoute('logistique_index');
    }
}
