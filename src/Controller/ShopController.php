<?php

declare(strict_types=1);

namespace App\Controller;

use App\Enum\ProduitStatutEnum;
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

        $keyword = trim($request->query->getString('q'));
        $keywordNormalized = mb_strtolower($keyword);

        $produits = $produitRepository->findBy([
            'statut' => ProduitStatutEnum::DISPONIBLE,
        ], ['id' => 'DESC']);
        $catalogue = [];

        foreach ($produits as $produit) {
            if (
                $keywordNormalized !== ''
                && !str_contains(mb_strtolower($produit->getLibelle()), $keywordNormalized)
            ) {
                continue;
            }

            $stockDisponible = $this->cartManager->getAvailableStockForDisplay($produit);
            if ($stockDisponible < 1) {
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
                'q' => $keyword,
            ],
        ]);
    }
}
