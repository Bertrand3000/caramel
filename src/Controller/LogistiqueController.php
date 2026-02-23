<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Commande;
use App\Interface\BonLivraisonGeneratorInterface;
use App\Interface\LogistiqueServiceInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class LogistiqueController extends AbstractController
{
    #[Route('/logistique/dashboard', name: 'logistique_dashboard', methods: ['GET'])]
    public function dashboard(LogistiqueServiceInterface $logistiqueService): Response
    {
        return $this->render('logistique/dashboard.html.twig', [
            'commandes' => $logistiqueService->findTodayReadyOrders(),
        ]);
    }

    #[Route('/logistique/commande/{id}/retrait', name: 'logistique_commande_retrait', methods: ['POST'])]
    public function retrait(Commande $commande, LogistiqueServiceInterface $logistiqueService): RedirectResponse
    {
        $logistiqueService->validateRetrait($commande);
        $this->addFlash('success', sprintf('Retrait de la commande #%d validé.', $commande->getId()));

        return $this->redirectToRoute('logistique_dashboard');
    }

    #[Route('/logistique/commande/{id}/bon-livraison', name: 'logistique_bon_livraison', methods: ['GET'])]
    public function bonLivraison(Commande $commande, BonLivraisonGeneratorInterface $generator): Response
    {
        return new Response($generator->generatePrintHtml($commande));
    }
}
