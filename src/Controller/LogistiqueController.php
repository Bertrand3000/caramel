<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Commande;
use App\Interface\BonLivraisonGeneratorInterface;
use App\Interface\LogistiqueServiceInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

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

        return $this->redirectToRoute('logistique_index');
    }

    #[Route('/logistique/commande/{id}/bon-livraison', name: 'logistique_bon_livraison', methods: ['GET'])]
    public function bonLivraison(Commande $commande, BonLivraisonGeneratorInterface $generator): Response
    {
        return new Response($generator->generatePrintHtml($commande));
    }

    #[Route('/logistique', name: 'logistique_index', methods: ['GET'])]
    #[IsGranted('ROLE_DMAX')]
    public function index(LogistiqueServiceInterface $logistiqueService): Response
    {
        $jour = $logistiqueService->findNextDeliveryDay();

        return $this->render('logistique/index.html.twig', [
            'jour'      => $jour,
            'commandes' => $jour !== null ? $logistiqueService->findAllOrdersForLogistique($jour) : [],
        ]);
    }

    #[Route('/logistique/preparation', name: 'logistique_index', methods: ['GET'])]
    #[IsGranted('ROLE_DMAX')]
    public function preparation(LogistiqueServiceInterface $logistiqueService): Response
    {
        $jour = $logistiqueService->findNextDeliveryDay();

        return $this->render('logistique/preparation.html.twig', [
            'jour'      => $jour,
            'commandes' => $jour !== null ? $logistiqueService->findOrdersForPreparation($jour) : [],
        ]);
    }

    #[Route('/logistique/commande/{id}/en-preparation', name: 'logistique_commande_en_preparation', methods: ['POST'])]
    #[IsGranted('ROLE_DMAX')]
    public function enPreparation(Commande $commande, Request $request, LogistiqueServiceInterface $logistiqueService): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('en_preparation_' . $commande->getId(), $request->request->getString('_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');

            return $this->redirectToRoute('logistique_index');
        }

        try {
            $logistiqueService->markEnPreparation($commande);
            $this->addFlash('success', sprintf('Commande #%d en cours de préparation.', $commande->getId()));
        } catch (\LogicException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('logistique_index');
    }

    #[Route('/logistique/commande/{id}/retour-validee', name: 'logistique_commande_retour_validee', methods: ['POST'])]
    #[IsGranted('ROLE_DMAX')]
    public function retourValidee(Commande $commande, Request $request, LogistiqueServiceInterface $logistiqueService): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('retour_validee_' . $commande->getId(), $request->request->getString('_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');

            return $this->redirectToRoute('logistique_index');
        }

        try {
            $logistiqueService->markAsValidee($commande);
            $this->addFlash('success', sprintf('Commande #%d remise en attente de préparation.', $commande->getId()));
        } catch (\LogicException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('logistique_index');
    }

    #[Route('/logistique/commande/{id}/prete', name: 'logistique_commande_prete', methods: ['POST'])]
    #[IsGranted('ROLE_DMAX')]
    public function prete(Commande $commande, Request $request, LogistiqueServiceInterface $logistiqueService): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('prete_' . $commande->getId(), $request->request->getString('_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');

            return $this->redirectToRoute('logistique_index');
        }

        try {
            $logistiqueService->markAsPrete($commande);
            $this->addFlash('success', sprintf('Commande #%d marquée prête.', $commande->getId()));
        } catch (\LogicException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('logistique_index');
    }

    #[Route('/logistique/commande/{id}/retour-en-preparation', name: 'logistique_commande_retour_en_preparation', methods: ['POST'])]
    #[IsGranted('ROLE_DMAX')]
    public function retourEnPreparation(Commande $commande, Request $request, LogistiqueServiceInterface $logistiqueService): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('retour_en_preparation_' . $commande->getId(), $request->request->getString('_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');

            return $this->redirectToRoute('logistique_index');
        }

        try {
            $logistiqueService->markRevenirEnPreparation($commande);
            $this->addFlash('success', sprintf('Commande #%d remise en préparation.', $commande->getId()));
        } catch (\LogicException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('logistique_index');
    }
}
