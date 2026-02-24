<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Commande;
use App\Entity\Creneau;
use App\Entity\LigneCommande;
use App\Entity\Produit;
use App\Entity\Utilisateur;
use App\Enum\CommandeStatutEnum;
use App\Enum\ProduitEtatEnum;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class LogistiqueControllerTest extends WebTestCase
{
    public function testDashboardAfficheLesCommandesPretesDuJour(): void
    {
        $client = static::createClient();
        $client->loginUser($this->buildDmaxUser());

        $commande = $this->createReadyOrderForToday();

        $client->request('GET', '/logistique/dashboard');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Commandes prêtes du jour');
        self::assertStringContainsString(
            sprintf('Commande #%d', $commande->getId()),
            (string) $client->getResponse()->getContent(),
        );
    }

    public function testRetraitAppliqueTransitionEtRedirige(): void
    {
        $client = static::createClient();
        $client->loginUser($this->buildDmaxUser());

        $commande = $this->createReadyOrderForToday();

        $client->request('POST', sprintf('/logistique/commande/%d/retrait', $commande->getId()));

        self::assertResponseRedirects('/logistique/dashboard');

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();
        $reloaded = $entityManager->find(Commande::class, $commande->getId());

        self::assertInstanceOf(Commande::class, $reloaded);
        self::assertSame(CommandeStatutEnum::RETIREE, $reloaded->getStatut());
    }

    private function createReadyOrderForToday(): Commande
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $produit = (new Produit())
            ->setLibelle('Chaise de test')
            ->setPhotoProduit('test.jpg')
            ->setEtat(ProduitEtatEnum::BON)
            ->setEtage('1')
            ->setPorte('A')
            ->setLargeur(45.0)
            ->setHauteur(80.0)
            ->setProfondeur(40.0)
            ->setQuantite(1);

        $creneau = (new Creneau())
            ->setDateHeure(new \DateTimeImmutable('today 09:00'))
            ->setHeureDebut(new \DateTime('09:00'))
            ->setHeureFin(new \DateTime('09:30'));

        $commande = (new Commande())
            ->setStatut(CommandeStatutEnum::PRETE)
            ->setDateValidation(new \DateTime());
        $beneficiaire = (new Utilisateur())
            ->setLogin(sprintf('agent-log-%s@test.local', bin2hex(random_bytes(4))))
            ->setPassword('dummy')
            ->setRoles(['ROLE_AGENT']);

        $ligneCommande = (new LigneCommande())
            ->setCommande($commande)
            ->setProduit($produit)
            ->setQuantite(1);

        $commande->setCreneau($creneau);
        $commande->setUtilisateur($beneficiaire);

        $entityManager->persist($beneficiaire);
        $entityManager->persist($produit);
        $entityManager->persist($creneau);
        $entityManager->persist($commande);
        $entityManager->persist($ligneCommande);
        $entityManager->flush();

        return $commande;
    }

    private function buildDmaxUser(): Utilisateur
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
}
