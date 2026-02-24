<?php

declare(strict_types=1);

namespace App\Controller\Dmax;

use App\DTO\CreateProduitDTO;
use App\Form\ProduitType;
use App\Interface\InventoryManagerInterface;
use App\Repository\ProduitRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DashboardController extends AbstractController
{
    #[Route('/dmax/', name: 'dmax_dashboard', methods: ['GET'])]
    public function index(Request $request, InventoryManagerInterface $inventoryManager): Response
    {
        $etage = trim($request->query->getString('etage'));
        $bureau = trim($request->query->getString('bureau'));
        $page = max(1, $request->query->getInt('page', 1));

        return $this->render('dmax/dashboard/index.html.twig', $inventoryManager->findDashboardPage(
            $etage !== '' ? $etage : null,
            $bureau !== '' ? $bureau : null,
            $page,
        ));
    }

    #[Route('/dmax/produit/nouveau', name: 'dmax_produit_new', methods: ['GET', 'POST'])]
    public function new(Request $request, InventoryManagerInterface $inventoryManager): Response
    {
        $form = $this->createForm(ProduitType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $dto = $this->buildCreateProduitDto($form->getData());
            $photo = $form->get('photoProduit')->getData();
            $photoInv = $form->get('photoNumeroInventaire')->getData();
            $inventoryManager->createProduit($dto, $photo, $photoInv);
            $this->addFlash('success', 'Produit créé avec succès.');
            if ($photoInv === null) {
                $this->addFlash('warning', 'Photo numéro inventaire absente.');
            }

            return $this->redirectToRoute('dmax_dashboard');
        }

        return $this->render('dmax/produit/new.html.twig', ['form' => $form->createView()]);
    }

    #[Route('/dmax/produit/{id}/editer', name: 'dmax_produit_edit', methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request, ProduitRepository $produitRepository, InventoryManagerInterface $inventoryManager): Response
    {
        $produit = $produitRepository->find($id);
        if ($produit === null) {
            throw $this->createNotFoundException();
        }

        $form = $this->createForm(ProduitType::class, [
            'numeroInventaire' => $produit->getNumeroInventaire(),
            'libelle' => $produit->getLibelle(),
            'etat' => $produit->getEtat(),
            'etage' => $produit->getEtage(),
            'porte' => $produit->getPorte(),
            'tagTeletravailleur' => $produit->isTagTeletravailleur(),
            'largeur' => $produit->getLargeur(),
            'hauteur' => $produit->getHauteur(),
            'profondeur' => $produit->getProfondeur(),
        ], ['photo_required' => false]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $dto = $this->buildCreateProduitDto($form->getData());
            $inventoryManager->updateProduit($id, $dto, $form->get('photoProduit')->getData(), $form->get('photoNumeroInventaire')->getData());
            $this->addFlash('success', 'Produit mis à jour.');

            return $this->redirectToRoute('dmax_dashboard');
        }

        return $this->render('dmax/produit/edit.html.twig', ['form' => $form->createView(), 'produit' => $produit]);
    }

    #[Route('/dmax/produit/{id}/supprimer', name: 'dmax_produit_delete', methods: ['POST'])]
    public function delete(int $id, Request $request, InventoryManagerInterface $inventoryManager): Response
    {
        if ($this->isCsrfTokenValid('delete_produit_'.$id, (string) $request->request->get('_token'))) {
            $inventoryManager->deleteProduit($id);
            $this->addFlash('success', 'Produit supprimé.');
        }

        return $this->redirectToRoute('dmax_dashboard');
    }

    /**
     * @param array<string, mixed> $data
     */
    private function buildCreateProduitDto(array $data): CreateProduitDTO
    {
        $numeroInventaire = trim((string) ($data['numeroInventaire'] ?? ''));

        return new CreateProduitDTO(
            $numeroInventaire !== '' ? $numeroInventaire : null,
            (string) $data['libelle'],
            $data['etat'],
            (string) $data['etage'],
            (string) $data['porte'],
            (bool) $data['tagTeletravailleur'],
            (float) $data['largeur'],
            (float) $data['hauteur'],
            (float) $data['profondeur'],
        );
    }
}
