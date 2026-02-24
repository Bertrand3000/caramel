<?php

declare(strict_types=1);

namespace App\Tests\Controller\Admin;

use App\Entity\Commande;
use App\Entity\Creneau;
use App\Entity\LigneCommande;
use App\Entity\Produit;
use App\Entity\Utilisateur;
use App\Enum\CommandeStatutEnum;
use App\Enum\ProduitEtatEnum;
use App\Enum\ProduitStatutEnum;
use App\Enum\ProfilUtilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ExportControllerTest extends WebTestCase
{
    public function testAdminPeutTelechargerLesTroisExports(): void
    {
        $client = static::createClient();
        $client->loginUser($this->createAdminUser());
        $this->seedExportData();

        $client->request('GET', '/admin/exports/ventes.csv');
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type', 'text/csv; charset=UTF-8');
        self::assertStringContainsString('numero_agent', (string) $client->getResponse()->getContent());
        self::assertStringContainsString('12345', (string) $client->getResponse()->getContent());

        $client->request('GET', '/admin/exports/stock-restant.csv');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Stock restant export', (string) $client->getResponse()->getContent());

        $client->request('GET', '/admin/exports/comptabilite.xls');
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type', 'application/vnd.ms-excel; charset=UTF-8');
        self::assertStringContainsString('total_articles', (string) $client->getResponse()->getContent());
    }

    private function createAdminUser(): Utilisateur
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $user = (new Utilisateur())
            ->setLogin(sprintf('admin-export-%s@test.local', bin2hex(random_bytes(4))))
            ->setPassword('dummy')
            ->setRoles(['ROLE_ADMIN'])
            ->setProfil(ProfilUtilisateur::DMAX);

        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }

    private function seedExportData(): void
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $produitVendu = (new Produit())
            ->setLibelle('Vente export')
            ->setPhotoProduit('vente.jpg')
            ->setEtat(ProduitEtatEnum::BON)
            ->setEtage('1')
            ->setPorte('A')
            ->setLargeur(120.0)
            ->setHauteur(75.0)
            ->setProfondeur(60.0)
            ->setQuantite(1)
            ->setStatut(ProduitStatutEnum::RESERVE);

        $produitStock = (new Produit())
            ->setLibelle('Stock restant export')
            ->setPhotoProduit('stock.jpg')
            ->setEtat(ProduitEtatEnum::BON)
            ->setEtage('2')
            ->setPorte('B')
            ->setLargeur(80.0)
            ->setHauteur(180.0)
            ->setProfondeur(50.0)
            ->setQuantite(4)
            ->setStatut(ProduitStatutEnum::DISPONIBLE);

        $creneau = (new Creneau())
            ->setDateHeure(new \DateTimeImmutable('2026-03-21 09:00:00'))
            ->setHeureDebut(new \DateTime('09:00:00'))
            ->setHeureFin(new \DateTime('09:30:00'));

        $commande = (new Commande())
            ->setNumeroAgent('12345')
            ->setNom('Durand')
            ->setPrenom('Alice')
            ->setStatut(CommandeStatutEnum::PRETE)
            ->setDateValidation(new \DateTime())
            ->setCreneau($creneau);
        $beneficiaire = (new Utilisateur())
            ->setLogin(sprintf('agent-export-%s@test.local', bin2hex(random_bytes(4))))
            ->setPassword('dummy')
            ->setRoles(['ROLE_AGENT'])
            ->setProfil(ProfilUtilisateur::PUBLIC);

        $ligne = (new LigneCommande())
            ->setCommande($commande)
            ->setProduit($produitVendu)
            ->setQuantite(1);

        $commande->setUtilisateur($beneficiaire);

        $entityManager->persist($beneficiaire);
        $entityManager->persist($produitVendu);
        $entityManager->persist($produitStock);
        $entityManager->persist($creneau);
        $entityManager->persist($commande);
        $entityManager->persist($ligne);
        $entityManager->flush();
    }
}
