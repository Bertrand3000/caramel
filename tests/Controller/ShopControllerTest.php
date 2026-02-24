<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Parametre;
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
        $this->setBoutiqueOpenForAgents(true);
        $client->request('GET', '/boutique');

        self::assertResponseIsSuccessful();
    }

    public function testCatalogueFermeRetourne403PourAgent(): void
    {
        $client = static::createClient();
        $client->loginUser($this->createAgentUser());
        $this->setBoutiqueOpenForAgents(false);
        $crawler = $client->request('GET', '/boutique');

        self::assertResponseStatusCodeSame(403);
        self::assertGreaterThan(0, $crawler->filter('.alert.alert-danger')->count());
        self::assertSelectorTextContains('.alert.alert-danger', 'Boutique fermée');
        self::assertSelectorTextContains('.alert.alert-danger', 'La boutique n\'est pas encore ouverte pour votre profil.');
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

    private function setBoutiqueOpenForAgents(bool $open): void
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $param = $entityManager->getRepository(Parametre::class)->findOneBy(['cle' => 'boutique_ouverte_agents']) ?? (new Parametre())->setCle('boutique_ouverte_agents');
        $param->setValeur($open ? '1' : '0');
        $entityManager->persist($param);
        $entityManager->flush();
    }
}
