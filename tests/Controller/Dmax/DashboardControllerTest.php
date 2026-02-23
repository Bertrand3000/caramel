<?php

declare(strict_types=1);

namespace App\Tests\Controller\Dmax;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class DashboardControllerTest extends WebTestCase
{
    public function testIndexRequiresDmaxRole(): void
    {
        $client = static::createClient();
        $client->request('GET', '/dmax/');

        self::assertResponseRedirects('/login');
    }

    public function testNewProduitFormSubmit(): void
    {
        self::markTestSkipped('Authentification ROLE_DMAX non configurée en fixture pour ce test.');
    }
}
