<?php

declare(strict_types=1);

namespace App\Controller;

use App\Interface\BoutiqueAccessCheckerInterface;
use App\Repository\ProduitRepository;
use App\Service\CartManager;
use App\Service\QuotaCheckerService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/panier')]
final class CartController extends AbstractController
{
    public function __construct(
        private readonly CartManager $cartManager,
        private readonly BoutiqueAccessCheckerInterface $boutiqueAccessChecker,
        private readonly QuotaCheckerService $quotaChecker,
    )
    {
    }

    #[Route('', name: 'cart_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $this->boutiqueAccessChecker->assertOpenForRoles($this->getUser()?->getRoles() ?? []);
        $sessionId = $request->getSession()->getId();
        $items = $this->cartManager->getContents($sessionId);
        $hasItems = $items !== [];
        $canContinueShopping = $this->quotaChecker->canAddMoreItems(
            $this->getUser()?->getRoles() ?? [],
            count($items),
        );

        $expireAt = null;
        if ($hasItems) {
            $expireAt = min(array_map(
                static fn (array $item): int => $item['expireAt']->getTimestamp(),
                $items,
            ));
        }

        return $this->render('cart/index.html.twig', [
            'items' => $items,
            'expireAt' => $expireAt,
            'hasItems' => $hasItems,
            'canContinueShopping' => $canContinueShopping,
        ]);
    }

    #[Route('/ajouter', name: 'cart_add', methods: ['POST'])]
    public function addItem(Request $request, ProduitRepository $produitRepository): RedirectResponse
    {
        $this->boutiqueAccessChecker->assertOpenForRoles($this->getUser()?->getRoles() ?? []);
        $produitId = $request->request->getInt('produitId');
        $produit = $produitRepository->find($produitId);

        if ($produit === null) {
            $this->addFlash('error', 'Produit introuvable.');

            return $this->redirectToRoute('cart_index');
        }

        try {
            $this->cartManager->addItem(
                $request->getSession()->getId(),
                $produit,
                $this->getUser()?->getRoles() ?? [],
            );
            $this->addFlash('success', 'Produit ajouté au panier.');
        } catch (\RuntimeException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('cart_index');
    }

    #[Route('/retirer/{id}', name: 'cart_remove', methods: ['POST'])]
    public function removeItem(int $id, Request $request): RedirectResponse
    {
        $this->boutiqueAccessChecker->assertOpenForRoles($this->getUser()?->getRoles() ?? []);
        $this->cartManager->removeItem($request->getSession()->getId(), $id);
        $this->addFlash('success', 'Article retiré du panier.');

        return $this->redirectToRoute('cart_index');
    }

    #[Route('/vider', name: 'cart_clear', methods: ['POST'])]
    public function clear(Request $request): RedirectResponse
    {
        $this->boutiqueAccessChecker->assertOpenForRoles($this->getUser()?->getRoles() ?? []);
        $this->cartManager->clear($request->getSession()->getId());
        $this->addFlash('success', 'Panier vidé.');

        return $this->redirectToRoute('shop_catalogue');
    }
}
