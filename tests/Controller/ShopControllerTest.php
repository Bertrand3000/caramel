<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Parametre;
use App\Entity\Produit;
use App\Entity\ReservationTemporaire;
use App\Entity\Utilisateur;
use App\Enum\ProduitEtatEnum;
use App\Enum\ProduitStatutEnum;
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

    public function testCatalogueNafficheQueLesProduitsDisponibles(): void
    {
        $client = static::createClient();
        $client->loginUser($this->createAgentUser());
        $this->setBoutiqueOpenForAgents(true);
        $this->createProduit('Produit disponible', ProduitStatutEnum::DISPONIBLE, 1);
        $this->createProduit('Produit remis', ProduitStatutEnum::REMIS, 0);

        $client->request('GET', '/boutique');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', 'Produit disponible');
        self::assertSelectorTextNotContains('main', 'Produit remis');
    }

    public function testCatalogueRechercheParMotClePartiel(): void
    {
        $client = static::createClient();
        $client->loginUser($this->createAgentUser());
        $this->setBoutiqueOpenForAgents(true);
        $this->createProduit('Armoire métal', ProduitStatutEnum::DISPONIBLE, 1);
        $this->createProduit('Bureau compact', ProduitStatutEnum::DISPONIBLE, 1);

        $client->request('GET', '/boutique?q=moi');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', 'Armoire métal');
        self::assertSelectorTextNotContains('main', 'Bureau compact');
    }

    public function testCatalogueMasqueLesProduitsReservesTemporairement(): void
    {
        $client = static::createClient();
        $client->loginUser($this->createAgentUser());
        $this->setBoutiqueOpenForAgents(true);
        $suffix = bin2hex(random_bytes(4));
        $libelleVisible = sprintf('Chaise libre %s', $suffix);
        $libelleReserve = sprintf('Chaise reservee %s', $suffix);
        $this->createProduit($libelleVisible, ProduitStatutEnum::DISPONIBLE, 1);
        $produitReserve = $this->createProduit($libelleReserve, ProduitStatutEnum::DISPONIBLE, 1);
        $this->createReservationTemporaire($produitReserve, 'session-other');

        $client->request('GET', '/boutique');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', $libelleVisible);
        self::assertSelectorTextNotContains('main', $libelleReserve);
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

    private function createProduit(string $libelle, ProduitStatutEnum $statut, int $quantite): Produit
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $produit = (new Produit())
            ->setLibelle($libelle)
            ->setPhotoProduit('test.jpg')
            ->setEtat(ProduitEtatEnum::BON)
            ->setEtage('1')
            ->setPorte('A')
            ->setLargeur(50)
            ->setHauteur(70)
            ->setProfondeur(50)
            ->setStatut($statut)
            ->setQuantite($quantite);

        $entityManager->persist($produit);
        $entityManager->flush();

        return $produit;
    }

    private function createReservationTemporaire(Produit $produit, string $sessionId): void
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $reservation = (new ReservationTemporaire())
            ->setProduit($produit)
            ->setSessionId($sessionId)
            ->setQuantite(1)
            ->setExpireAt(new \DateTimeImmutable('+20 minutes'));

        $entityManager->persist($reservation);
        $entityManager->flush();
    }
}
