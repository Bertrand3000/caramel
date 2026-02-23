<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class CartControllerTest extends WebTestCase
{
    public function testAjoutPanierRedirigeVersPanier(): void
    {
        $client = static::createClient();
        $client->request('POST', '/panier/ajouter', ['produitId' => 9999, 'quantite' => 1]);

        self::assertResponseRedirects('/panier');
    }
}
