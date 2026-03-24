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
        self::assertSelectorTextContains('.alert.alert-danger', 'La boutique est actuellement fermée. Nous vous remercions pour votre compréhension.');
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

    public function testCatalogueDesactiveAjoutAuPanierQuandQuotaAtteint(): void
    {
        $client = static::createClient();
        $client->loginUser($this->createAgentUser());
        $this->setBoutiqueOpenForAgents(true);
        $this->setQuotaArticlesMax(1);

        $produitAjoute = $this->createProduit('Produit deja dans panier', ProduitStatutEnum::DISPONIBLE, 1);
        $this->createProduit('Produit encore visible', ProduitStatutEnum::DISPONIBLE, 1);

        $client->request('POST', '/panier/ajouter', [
            'produitId' => $produitAjoute->getId(),
        ]);
        self::assertResponseRedirects('/panier');

        $crawler = $client->request('GET', '/boutique');

        self::assertResponseIsSuccessful();
        self::assertGreaterThan(
            0,
            $crawler->filter('button[disabled][title="Vous ne pouvez pas commander plus de 1 articles."]')->count(),
        );
    }

    public function testCataloguePaginatesFilteredResults(): void
    {
        $client = static::createClient();
        $client->loginUser($this->createAgentUser());
        $this->setBoutiqueOpenForAgents(true);

        $suffix = bin2hex(random_bytes(4));
        for ($i = 1; $i <= 13; ++$i) {
            $this->createProduit(sprintf('Produit pagination %s %02d', $suffix, $i), ProduitStatutEnum::DISPONIBLE, 1);
        }

        $client->request('GET', sprintf('/boutique?q=%s', $suffix));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', sprintf('Produit pagination %s 13', $suffix));
        self::assertSelectorTextNotContains('main', sprintf('Produit pagination %s 01', $suffix));

        $client->request('GET', sprintf('/boutique?q=%s&page=2', $suffix));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', sprintf('Produit pagination %s 01', $suffix));
        self::assertSelectorTextNotContains('main', sprintf('Produit pagination %s 13', $suffix));
    }

    public function testCatalogueTeletravailleurNafficheQueLesProduitsTagges(): void
    {
        $client = static::createClient();
        $client->loginUser($this->createTeletravailleurUser());
        $this->setBoutiqueOpenForTeletravailleurs(true);

        $suffix = bin2hex(random_bytes(4));
        $this->createProduit(
            sprintf('Produit teletravail %s', $suffix),
            ProduitStatutEnum::DISPONIBLE,
            1,
            true,
        );
        $this->createProduit(
            sprintf('Produit general %s', $suffix),
            ProduitStatutEnum::DISPONIBLE,
            1,
            false,
        );

        $client->request('GET', '/boutique');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', sprintf('Produit teletravail %s', $suffix));
        self::assertSelectorTextNotContains('main', sprintf('Produit general %s', $suffix));
    }

    public function testCatalogueAdminVoitAussiLesProduitsNonTagges(): void
    {
        $client = static::createClient();
        $client->loginUser($this->createAdminUser());

        $suffix = bin2hex(random_bytes(4));
        $this->createProduit(
            sprintf('Produit admin teletravail %s', $suffix),
            ProduitStatutEnum::DISPONIBLE,
            1,
            true,
        );
        $this->createProduit(
            sprintf('Produit admin general %s', $suffix),
            ProduitStatutEnum::DISPONIBLE,
            1,
            false,
        );

        $client->request('GET', '/boutique');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', sprintf('Produit admin teletravail %s', $suffix));
        self::assertSelectorTextContains('main', sprintf('Produit admin general %s', $suffix));
    }

    private function createAgentUser(): Utilisateur
    {
        $this->setQuotaArticlesMax(3);
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $user = (new Utilisateur())
            ->setLogin(sprintf('agent-shop-%s@test.local', bin2hex(random_bytes(4))))
            ->setPassword('dummy')
            ->setRoles(['ROLE_AGENT']);

        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }

    private function createTeletravailleurUser(): Utilisateur
    {
        $this->setQuotaArticlesMax(3);
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $user = (new Utilisateur())
            ->setLogin(sprintf('teletravailleur-shop-%s@test.local', bin2hex(random_bytes(4))))
            ->setPassword('dummy')
            ->setRoles(['ROLE_TELETRAVAILLEUR']);

        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }

    private function createAdminUser(): Utilisateur
    {
        $this->setQuotaArticlesMax(3);
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $user = (new Utilisateur())
            ->setLogin(sprintf('admin-shop-%s@test.local', bin2hex(random_bytes(4))))
            ->setPassword('dummy')
            ->setRoles(['ROLE_ADMIN']);

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

    private function setBoutiqueOpenForTeletravailleurs(bool $open): void
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $param = $entityManager->getRepository(Parametre::class)->findOneBy(['cle' => 'boutique_ouverte_teletravailleurs']) ?? (new Parametre())->setCle('boutique_ouverte_teletravailleurs');
        $param->setValeur($open ? '1' : '0');
        $entityManager->persist($param);
        $entityManager->flush();
    }

    private function setQuotaArticlesMax(int $quota): void
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $param = $entityManager->getRepository(Parametre::class)->findOneBy(['cle' => 'max_produits_par_commande']) ?? (new Parametre())->setCle('max_produits_par_commande');
        $param->setValeur((string) $quota);
        $entityManager->persist($param);
        $entityManager->flush();
    }

    private function createProduit(
        string $libelle,
        ProduitStatutEnum $statut,
        int $quantite,
        bool $tagTeletravailleur = false,
        string $vnc = '=0',
    ): Produit
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
            ->setQuantite($quantite)
            ->setTagTeletravailleur($tagTeletravailleur)
            ->setVnc($vnc);

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
