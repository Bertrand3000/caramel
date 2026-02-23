<?php

declare(strict_types=1);

namespace App\Controller\Shop;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DashboardController extends AbstractController
{
    #[Route('/shop/', name: 'shop_dashboard', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('shop/dashboard.html.twig');
    }
}
