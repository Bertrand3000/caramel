<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ShopControllerTest extends WebTestCase
{
    public function testCatalogueAnonymousRedirectsToLogin(): void
    {
        $client = static::createClient();
        $client->request('GET', '/boutique');

        self::assertResponseRedirects('/login');
    }

    public function testCatalogueRetourne200PourAgent(): void
    {
        $client = static::createClient();
        $client->loginUser($this->createAgentUser());
        $client->request('GET', '/boutique');

        self::assertResponseIsSuccessful();
    }

    private function createAgentUser(): Utilisateur
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $user = (new Utilisateur())
            ->setLogin(sprintf('agent-shop-%s@test.local', bin2hex(random_bytes(4))))
            ->setPassword('dummy')
            ->setRoles(['ROLE_AGENT']);

        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }
}
