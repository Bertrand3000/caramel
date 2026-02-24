<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class HomeControllerTest extends WebTestCase
{
    public function testAnonymousHomeRedirectsToLogin(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');

        self::assertResponseRedirects('/login');
    }

    public function testAdminHomeRedirectsToAdminDashboard(): void
    {
        $client = static::createClient();
        $client->loginUser($this->createUserWithRoles(['ROLE_ADMIN'], 'admin-home'));
        $client->request('GET', '/');

        self::assertResponseRedirects('/admin/');
    }

    public function testDmaxHomeRedirectsToDmaxDashboard(): void
    {
        $client = static::createClient();
        $client->loginUser($this->createUserWithRoles(['ROLE_DMAX'], 'dmax-home'));
        $client->request('GET', '/');

        self::assertResponseRedirects('/dmax/');
    }

    public function testAgentHomeRedirectsToShopDashboard(): void
    {
        $client = static::createClient();
        $client->loginUser($this->createUserWithRoles(['ROLE_AGENT'], 'agent-home'));
        $client->request('GET', '/');

        self::assertResponseRedirects('/boutique/dashboard');
    }

    /**
     * @param list<string> $roles
     */
    private function createUserWithRoles(array $roles, string $prefix): Utilisateur
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $user = (new Utilisateur())
            ->setLogin(sprintf('%s-%s@test.local', $prefix, bin2hex(random_bytes(4))))
            ->setPassword('dummy')
            ->setRoles($roles);
        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }
}

