<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Produit;
use App\Entity\ReservationTemporaire;
use App\Entity\Utilisateur;
use App\Entity\Parametre;
use App\Enum\ProduitEtatEnum;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Cookie;

final class CartControllerTest extends WebTestCase
{
    public function testAnonymousAddRedirectsToLogin(): void
    {
        $client = static::createClient();
        $client->request('POST', '/panier/ajouter', [
            'produitId' => 1,
            'quantite' => 1,
        ]);

        self::assertResponseRedirects('/login');
    }

    public function testAjoutProduitValideCreeReservationTemporaire(): void
    {
        $client = static::createClient();
        $client->loginUser($this->createAgentUser());
        $sessionId = $this->forceClientSession($client);
        $produit = $this->createProduit('Chaise test', 5);

        $client->request('POST', '/panier/ajouter', [
            'produitId' => $produit->getId(),
            'quantite' => 2,
        ]);

        self::assertResponseRedirects('/panier');
        self::assertSame(1, $this->countReservationsForProduitAndSession($produit, $sessionId));
    }

    public function testAjoutPanierAvecProduitInexistantRedirigeVersPanier(): void
    {
        $client = static::createClient();
        $client->loginUser($this->createAgentUser());
        $client->request('POST', '/panier/ajouter', ['produitId' => 9999, 'quantite' => 1]);

        self::assertResponseRedirects('/panier');
    }

    public function testRetirerProduitSupprimeLaReservation(): void
    {
        $client = static::createClient();
        $client->loginUser($this->createAgentUser());
        $sessionId = $this->forceClientSession($client);
        $produit = $this->createProduit('Bureau test', 3);
        $client->request('POST', '/panier/ajouter', [
            'produitId' => $produit->getId(),
            'quantite' => 1,
        ]);
        self::assertSame(1, $this->countReservationsForProduitAndSession($produit, $sessionId));

        $client->request('POST', sprintf('/panier/retirer/%d', $produit->getId()));

        self::assertResponseRedirects('/panier');
        self::assertSame(0, $this->countReservationsForProduitAndSession($produit, $sessionId));
    }

    public function testViderPanierSupprimeToutesLesReservations(): void
    {
        $client = static::createClient();
        $client->loginUser($this->createAgentUser());
        $sessionId = $this->forceClientSession($client);
        $produitA = $this->createProduit('Armoire test', 5);
        $produitB = $this->createProduit('Table test', 5);
        $client->request('POST', '/panier/ajouter', ['produitId' => $produitA->getId(), 'quantite' => 1]);
        $client->request('POST', '/panier/ajouter', ['produitId' => $produitB->getId(), 'quantite' => 1]);
        self::assertSame(1, $this->countReservationsForProduitAndSession($produitA, $sessionId));
        self::assertSame(1, $this->countReservationsForProduitAndSession($produitB, $sessionId));

        $client->request('POST', '/panier/vider');

        self::assertResponseRedirects('/boutique');
        self::assertSame(0, $this->countReservationsForProduitAndSession($produitA, $sessionId));
        self::assertSame(0, $this->countReservationsForProduitAndSession($produitB, $sessionId));
    }

    public function testPanierVideMasqueBoutonValiderEtAfficheContinuerAchats(): void
    {
        $client = static::createClient();
        $client->loginUser($this->createAgentUser());

        $crawler = $client->request('GET', '/panier');

        self::assertResponseIsSuccessful();
        self::assertSame(0, $crawler->filter('main a[href="/commande/creneaux"]')->count());
        self::assertGreaterThan(0, $crawler->filter('main a[href="/boutique"]')->count());
    }

    public function testPanierPleinSelonQuotaMasqueContinuerAchats(): void
    {
        $client = static::createClient();
        $client->loginUser($this->createAgentUser());
        $this->setQuotaArticlesMax(1);
        $produit = $this->createProduit('Chaise quota', 1);

        $client->request('POST', '/panier/ajouter', [
            'produitId' => $produit->getId(),
        ]);
        self::assertResponseRedirects('/panier');

        $crawler = $client->request('GET', '/panier');

        self::assertResponseIsSuccessful();
        self::assertGreaterThan(0, $crawler->filter('main a[href="/commande/creneaux"]')->count());
        self::assertSame(0, $crawler->filter('main a[href="/boutique"]')->count());
    }

    private function createProduit(string $libelle, int $quantite): Produit
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $produit = (new Produit())
            ->setLibelle($libelle)
            ->setPhotoProduit('test.jpg')
            ->setEtat(ProduitEtatEnum::BON)
            ->setEtage('1')
            ->setPorte('A')
            ->setLargeur(60.0)
            ->setHauteur(70.0)
            ->setProfondeur(50.0)
            ->setQuantite($quantite);

        $entityManager->persist($produit);
        $entityManager->flush();

        return $produit;
    }

    private function createAgentUser(): Utilisateur
    {
        $this->setBoutiqueOpenForAgents();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $user = (new Utilisateur())
            ->setLogin(sprintf('agent-cart-%s@test.local', bin2hex(random_bytes(4))))
            ->setPassword('dummy')
            ->setRoles(['ROLE_AGENT']);
        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }

    private function setBoutiqueOpenForAgents(): void
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $param = $entityManager->getRepository(Parametre::class)->findOneBy(['cle' => 'boutique_ouverte_agents']) ?? (new Parametre())->setCle('boutique_ouverte_agents');
        $param->setValeur('1');
        $entityManager->persist($param);
        $entityManager->flush();
    }

    private function setQuotaArticlesMax(int $quota): void
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $param = $entityManager->getRepository(Parametre::class)->findOneBy(['cle' => 'quota_articles_max']) ?? (new Parametre())->setCle('quota_articles_max');
        $param->setValeur((string) $quota);
        $entityManager->persist($param);
        $entityManager->flush();
    }

    private function forceClientSession(KernelBrowser $client): string
    {
        $client->request('GET', '/panier');
        $session = $client->getRequest()->getSession();
        $session->set('cart_test', true);
        $session->save();

        $client->getCookieJar()->set(new Cookie($session->getName(), $session->getId()));

        return $session->getId();
    }

    private function countReservationsForProduitAndSession(Produit $produit, string $sessionId): int
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        return count($entityManager->getRepository(ReservationTemporaire::class)->findBy([
            'produit' => $produit,
            'sessionId' => $sessionId,
        ]));
    }
}
