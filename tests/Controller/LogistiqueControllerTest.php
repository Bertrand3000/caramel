<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Commande;
use App\Entity\Creneau;
use App\Entity\LigneCommande;
use App\Entity\Produit;
use App\Enum\CommandeStatutEnum;
use App\Enum\ProduitEtatEnum;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Security\Core\User\InMemoryUser;

final class LogistiqueControllerTest extends WebTestCase
{
    public function testDashboardAfficheLesCommandesPretesDuJour(): void
    {
        $client = static::createClient();
        $client->loginUser(new InMemoryUser('dmax', null, ['ROLE_DMAX']));

        $commande = $this->createReadyOrderForToday();

        $client->request('GET', '/logistique/dashboard');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Commandes prêtes du jour');
        self::assertSelectorTextContains('article', sprintf('Commande #%d', $commande->getId()));
    }

    public function testRetraitAppliqueTransitionEtRedirige(): void
    {
        $client = static::createClient();
        $client->loginUser(new InMemoryUser('dmax', null, ['ROLE_DMAX']));

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
            ->setQuantite(1);

        $creneau = (new Creneau())
            ->setDateHeure(new \DateTimeImmutable('today 09:00'))
            ->setHeureDebut(new \DateTimeImmutable('09:00'))
            ->setHeureFin(new \DateTimeImmutable('09:30'));

        $commande = (new Commande())
            ->setStatut(CommandeStatutEnum::PRETE)
            ->setDateValidation(new \DateTimeImmutable());

        $ligneCommande = (new LigneCommande())
            ->setCommande($commande)
            ->setProduit($produit)
            ->setQuantite(1);

        $commande->setCreneau($creneau);

        $entityManager->persist($produit);
        $entityManager->persist($creneau);
        $entityManager->persist($commande);
        $entityManager->persist($ligneCommande);
        $entityManager->flush();

        return $commande;
    }
}
