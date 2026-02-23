<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ShopControllerTest extends WebTestCase
{
    public function testCatalogueRetourne200(): void
    {
        $client = static::createClient();
        $client->request('GET', '/boutique');

        self::assertResponseIsSuccessful();
    }
}
