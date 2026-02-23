<?php

declare(strict_types=1);

namespace App\Controller\Dmax;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DashboardController extends AbstractController
{
    #[Route('/dmax/', name: 'dmax_dashboard', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('dmax/dashboard.html.twig');
    }
}
