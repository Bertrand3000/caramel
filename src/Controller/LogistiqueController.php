<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Commande;
use App\Interface\DocumentPdfGeneratorInterface;
use App\Interface\LogistiqueServiceInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class LogistiqueController extends AbstractController
{
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

    // ── Actions de transition de statut ──────────────────────────────────────

    #[Route('/logistique/commande/{id}/retrait', name: 'logistique_commande_retrait', methods: ['POST'])]
    #[IsGranted('ROLE_DMAX')]
    public function retrait(Commande $commande, Request $request, LogistiqueServiceInterface $logistiqueService): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('retrait_' . $commande->getId(), $request->request->getString('_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');

            return $this->redirectToRoute('logistique_index');
        }

        $logistiqueService->validateRetrait($commande);
        $this->addFlash('success', sprintf('Retrait de la commande #%d validé.', $commande->getId()));

        return $this->redirectToRoute('logistique_index');
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

    #[Route('/logistique/commande/{id}/retour-prete', name: 'logistique_commande_retour_prete', methods: ['POST'])]
    #[IsGranted('ROLE_DMAX')]
    public function retourPrete(Commande $commande, Request $request, LogistiqueServiceInterface $logistiqueService): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('retour_prete_' . $commande->getId(), $request->request->getString('_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');

            return $this->redirectToRoute('logistique_index');
        }

        try {
            $logistiqueService->markRevenirPrete($commande);
            $this->addFlash('success', sprintf('Commande #%d remise à l\'état « Prête ».', $commande->getId()));
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

    // ── Génération PDF ────────────────────────────────────────────────────────

    /**
     * Bon de commande PDF — accessible quel que soit le statut de la commande.
     */
    #[Route('/logistique/commande/{id}/bon-commande.pdf', name: 'logistique_bon_commande_pdf', methods: ['GET'])]
    #[IsGranted('ROLE_DMAX')]
    public function bonCommandePdf(Commande $commande, DocumentPdfGeneratorInterface $generator): Response
    {
        $pdf = $generator->generateBonCommande($commande);

        return new Response($pdf, Response::HTTP_OK, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => sprintf('inline; filename="bon-commande-%d.pdf"', $commande->getId()),
        ]);
    }

    /**
     * Bon de préparation PDF — étiquettes produits (3 sections par page).
     */
    #[Route('/logistique/commande/{id}/bon-preparation.pdf', name: 'logistique_bon_preparation_pdf', methods: ['GET'])]
    #[IsGranted('ROLE_DMAX')]
    public function bonPreparationPdf(Commande $commande, DocumentPdfGeneratorInterface $generator): Response
    {
        $pdf = $generator->generateBonPreparation($commande);

        return new Response($pdf, Response::HTTP_OK, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => sprintf('inline; filename="bon-preparation-%d.pdf"', $commande->getId()),
        ]);
    }

    /**
     * Bon de livraison PDF — attestation de cession à titre gratuit.
     */
    #[Route('/logistique/commande/{id}/bon-livraison.pdf', name: 'logistique_bon_livraison_pdf', methods: ['GET'])]
    #[IsGranted('ROLE_DMAX')]
    public function bonLivraisonPdf(Commande $commande, DocumentPdfGeneratorInterface $generator): Response
    {
        $pdf = $generator->generateBonLivraison($commande);

        return new Response($pdf, Response::HTTP_OK, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => sprintf('inline; filename="bon-livraison-%d.pdf"', $commande->getId()),
        ]);
    }
}
