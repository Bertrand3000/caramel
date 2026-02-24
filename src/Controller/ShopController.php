<?php

declare(strict_types=1);

namespace App\Controller;

use App\Enum\ProfilUtilisateur;
use App\Interface\BoutiqueAccessCheckerInterface;
use App\Repository\ProduitRepository;
use App\Service\CartManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\Cache;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/boutique')]
#[Cache(maxage: 0, public: false)]
final class ShopController extends AbstractController
{
    public function __construct(
        private readonly CartManager $cartManager,
        private readonly BoutiqueAccessCheckerInterface $boutiqueAccessChecker,
    )
    {
    }

    #[Route('', name: 'shop_catalogue', methods: ['GET'])]
    public function catalogue(Request $request, ProduitRepository $produitRepository): Response
    {
        $this->boutiqueAccessChecker->assertOpenForRoles($this->getUser()?->getRoles() ?? []);
        $this->cartManager->releaseExpired();

        $profil = $request->query->getString('profil');
        $onlyTeletravailleur = $profil === ProfilUtilisateur::TELETRAVAILLEUR->value;

        $produits = $produitRepository->findBy([], ['id' => 'DESC']);
        $catalogue = [];

        foreach ($produits as $produit) {
            if ($onlyTeletravailleur && !$produit->isTagTeletravailleur()) {
                continue;
            }

            $stockDisponible = $this->cartManager->getAvailableStockForDisplay($produit);
            $showOnlyAvailable = $request->query->getBoolean('dispo', false);
            if ($showOnlyAvailable && $stockDisponible < 1) {
                continue;
            }

            $catalogue[] = [
                'produit' => $produit,
                'stockDisponible' => $stockDisponible,
            ];
        }

        return $this->render('shop/catalogue.html.twig', [
            'catalogue' => $catalogue,
            'filtres' => [
                'profil' => $profil,
                'categorie' => $request->query->getString('categorie'),
                'dispo' => $request->query->getBoolean('dispo', false),
            ],
        ]);
    }
}
