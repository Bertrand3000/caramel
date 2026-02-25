<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Commande;
use App\Entity\Parametre;
use App\Entity\Produit;
use App\Entity\Utilisateur;
use App\Enum\CommandeStatutEnum;
use App\Enum\ProduitEtatEnum;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class CheckoutControllerTest extends WebTestCase
{
    public function testCheckoutSansAuthentificationRedirigeVersLogin(): void
    {
        $client = static::createClient();
        $client->request('POST', '/commande/confirmer', []);

        self::assertResponseRedirects('/login');
    }

    public function testAnnulationRefuseeSiCommandeAppartientAUnAutreUtilisateur(): void
    {
        $client = static::createClient();
        $proprietaire = $this->createUser('owner');
        $commande = $this->createCommandeForUser($proprietaire);
        $attaquant = $this->createUser('attacker');
        $client->loginUser($attaquant);

        $token = $this->fetchCancelCsrfToken($client, $commande->getId() ?? 0);
        $client->request('POST', sprintf('/commande/annuler/%d', $commande->getId()), [
            '_token' => $token,
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testAnnulationRefuseeSiTokenCsrfInvalide(): void
    {
        $client = static::createClient();
        $user = $this->createUser('owner-csrf');
        $commande = $this->createCommandeForUser($user);
        $client->loginUser($user);

        $client->request('POST', sprintf('/commande/annuler/%d', $commande->getId()), [
            '_token' => 'token-invalide',
        ]);

        self::assertResponseRedirects('/commande/confirmation');

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();
        $reloaded = $entityManager->find(Commande::class, $commande->getId());
        self::assertInstanceOf(Commande::class, $reloaded);
        self::assertSame(CommandeStatutEnum::EN_ATTENTE_VALIDATION, $reloaded->getStatut());
    }

    public function testAnnulationAccepteeAvecTokenCsrfValide(): void
    {
        $client = static::createClient();
        $user = $this->createUser('owner-ok');
        $commande = $this->createCommandeForUser($user);
        $client->loginUser($user);

        $token = $this->fetchCancelCsrfToken($client, $commande->getId() ?? 0);
        $client->request('POST', sprintf('/commande/annuler/%d', $commande->getId()), [
            '_token' => $token,
        ]);

        self::assertResponseRedirects('/commande/confirmation');

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();
        $reloaded = $entityManager->find(Commande::class, $commande->getId());
        self::assertInstanceOf(Commande::class, $reloaded);
        self::assertSame(CommandeStatutEnum::ANNULEE, $reloaded->getStatut());
    }

    public function testAgentPanierVideRedirigeVersPanierPourLesCreneaux(): void
    {
        $client = static::createClient();
        $client->loginUser($this->createUser('agent-creneaux'));

        $client->request('GET', '/commande/creneaux');

        self::assertResponseRedirects('/panier');
    }

    public function testAgentPeutAccederAuxCreneauxAvecPanierNonVide(): void
    {
        $client = static::createClient();
        $client->loginUser($this->createUser('agent-creneaux-ok'));
        $produit = $this->createProduit('Produit checkout');

        $client->request('POST', '/panier/ajouter', [
            'produitId' => $produit->getId(),
        ]);
        self::assertResponseRedirects('/panier');

        $client->request('GET', '/commande/creneaux');

        self::assertResponseIsSuccessful();
    }

    public function testConfirmationAvecPanierVideRedirigeVersPanier(): void
    {
        $client = static::createClient();
        $client->loginUser($this->createUser('agent-confirm-empty'));

        $client->request('POST', '/commande/confirmer', [
            'creneauId' => 1,
            'numeroAgent' => '12345',
        ]);

        self::assertResponseRedirects('/panier');
    }

    public function testDmaxNePeutPasAccederAuxCreneauxCommande(): void
    {
        $client = static::createClient();
        $client->loginUser($this->createDmaxUser());

        $client->request('GET', '/commande/creneaux');

        self::assertResponseStatusCodeSame(403);
    }

    private function createUser(string $prefix): Utilisateur
    {
        $this->setBoutiqueOpenForAgents();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $user = (new Utilisateur())
            ->setLogin(sprintf('%s-%s@test.local', $prefix, bin2hex(random_bytes(4))))
            ->setPassword('dummy')
            ->setRoles(['ROLE_AGENT']);
        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }

    private function createCommandeForUser(Utilisateur $user): Commande
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $commande = (new Commande())
            ->setUtilisateur($user)
            ->setSessionId(sprintf('sess-%s', bin2hex(random_bytes(4))))
            ->setDateValidation(new \DateTime())
            ->setStatut(CommandeStatutEnum::EN_ATTENTE_VALIDATION);
        $entityManager->persist($commande);
        $entityManager->flush();

        return $commande;
    }

    private function createDmaxUser(): Utilisateur
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $user = (new Utilisateur())
            ->setLogin(sprintf('dmax-%s@test.local', bin2hex(random_bytes(4))))
            ->setPassword('dummy')
            ->setRoles(['ROLE_DMAX']);
        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }

    private function fetchCancelCsrfToken(KernelBrowser $client, int $commandeId): string
    {
        $client->request('GET', '/commande/confirmation');
        $session = $client->getRequest()->getSession();
        $session->set('checkout_last_commande_id', $commandeId);
        $session->save();
        $crawler = $client->request('GET', '/commande/confirmation');

        return (string) $crawler->filter('input[name="_token"]')->attr('value');
    }

    private function setBoutiqueOpenForAgents(): void
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $param = $entityManager->getRepository(Parametre::class)->findOneBy(['cle' => 'boutique_ouverte_agents']) ?? (new Parametre())->setCle('boutique_ouverte_agents');
        $param->setValeur('1');
        $entityManager->persist($param);
        $entityManager->flush();
    }

    private function createProduit(string $libelle): Produit
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
            ->setQuantite(1);

        $entityManager->persist($produit);
        $entityManager->flush();

        return $produit;
    }
}
