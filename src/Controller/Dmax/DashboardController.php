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
    public function index(InventoryManagerInterface $inventoryManager): Response
    {
        return $this->render('dmax/dashboard/index.html.twig', ['produits' => $inventoryManager->findAllAvailable(null)]);
    }

    #[Route('/dmax/produit/nouveau', name: 'dmax_produit_new', methods: ['GET', 'POST'])]
    public function new(Request $request, InventoryManagerInterface $inventoryManager): Response
    {
        $form = $this->createForm(ProduitType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $dto = new CreateProduitDTO($data['libelle'], $data['etat'], $data['etage'], $data['porte'], (bool) $data['tagTeletravailleur'], $data['largeur'], $data['hauteur'], $data['profondeur']);
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

        $form = $this->createForm(ProduitType::class, ['libelle' => $produit->getLibelle(), 'etat' => $produit->getEtat(), 'etage' => $produit->getEtage(), 'porte' => $produit->getPorte(), 'tagTeletravailleur' => $produit->isTagTeletravailleur(), 'largeur' => $produit->getLargeur(), 'hauteur' => $produit->getHauteur(), 'profondeur' => $produit->getProfondeur()], ['photo_required' => false]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $dto = new CreateProduitDTO($data['libelle'], $data['etat'], $data['etage'], $data['porte'], (bool) $data['tagTeletravailleur'], $data['largeur'], $data['hauteur'], $data['profondeur']);
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
}
