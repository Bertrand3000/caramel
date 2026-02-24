<?php

declare(strict_types=1);

namespace App\Controller\Shop;

use App\Interface\BoutiqueAccessCheckerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DashboardController extends AbstractController
{
    public function __construct(private readonly BoutiqueAccessCheckerInterface $boutiqueAccessChecker)
    {
    }

    #[Route('/boutique/dashboard', name: 'shop_dashboard', methods: ['GET'])]
    public function index(): Response
    {
        $this->boutiqueAccessChecker->assertOpenForRoles($this->getUser()?->getRoles() ?? []);

        return $this->render('shop/dashboard.html.twig');
    }
}
