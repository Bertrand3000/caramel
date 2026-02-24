<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class NavigationMenuTest extends WebTestCase
{
    public function testAgentSeesShopAndLogoutLinks(): void
    {
        $client = static::createClient();
        $client->loginUser($this->createUserWithRoles(['ROLE_AGENT'], 'agent-nav'));

        $crawler = $client->request('GET', '/boutique');
        self::assertResponseIsSuccessful();
        self::assertGreaterThan(0, $crawler->filter('a[href="/boutique/dashboard"]')->count());
        self::assertGreaterThan(0, $crawler->filter('a[href="/boutique"]')->count());
        self::assertGreaterThan(0, $crawler->filter('a[href="/panier"]')->count());
        self::assertGreaterThan(0, $crawler->filter('a[href="/commande/creneaux"]')->count());
        self::assertGreaterThan(0, $crawler->filter('a[href="/logout"]')->count());
    }

    public function testDmaxSeesDmaxLogistiqueAndLogoutLinks(): void
    {
        $client = static::createClient();
        $client->loginUser($this->createUserWithRoles(['ROLE_DMAX'], 'dmax-nav'));

        $crawler = $client->request('GET', '/dmax/');
        self::assertResponseIsSuccessful();
        self::assertGreaterThan(0, $crawler->filter('a[href="/dmax/"]')->count());
        self::assertGreaterThan(0, $crawler->filter('a[href="/logistique/dashboard"]')->count());
        self::assertGreaterThan(0, $crawler->filter('a[href="/logout"]')->count());
        self::assertSame(0, $crawler->filter('a[href="/boutique"]')->count());
    }

    public function testAdminSeesAllMainLinks(): void
    {
        $client = static::createClient();
        $client->loginUser($this->createUserWithRoles(['ROLE_ADMIN'], 'admin-nav'));

        $crawler = $client->request('GET', '/admin/');
        self::assertResponseIsSuccessful();
        self::assertGreaterThan(0, $crawler->filter('a[href="/admin/"]')->count());
        self::assertGreaterThan(0, $crawler->filter('a[href="/dmax/"]')->count());
        self::assertGreaterThan(0, $crawler->filter('a[href="/logistique/dashboard"]')->count());
        self::assertGreaterThan(0, $crawler->filter('a[href="/boutique/dashboard"]')->count());
        self::assertGreaterThan(0, $crawler->filter('a[href="/boutique"]')->count());
        self::assertGreaterThan(0, $crawler->filter('a[href="/panier"]')->count());
        self::assertGreaterThan(0, $crawler->filter('a[href="/commande/creneaux"]')->count());
        self::assertGreaterThan(0, $crawler->filter('a[href="/logout"]')->count());
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
