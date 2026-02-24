<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\LandingRouteResolver;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    public function __construct(private readonly LandingRouteResolver $landingRouteResolver)
    {
    }

    #[Route('/', name: 'home', methods: ['GET'])]
    public function index(): RedirectResponse
    {
        $user = $this->getUser();
        if ($user === null) {
            return $this->redirectToRoute('login');
        }

        return $this->redirectToRoute($this->landingRouteResolver->resolveRoute($user->getRoles()));
    }
}

