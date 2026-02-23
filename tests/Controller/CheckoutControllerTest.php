<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class CheckoutControllerTest extends WebTestCase
{
    public function testCheckoutSansCreneauRetourneErreur(): void
    {
        $client = static::createClient();
        $client->request('POST', '/commande/confirmer', []);

        self::assertResponseRedirects('/commande/creneaux');
    }
}
