<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Commande;
use App\Entity\Creneau;
use App\Entity\LigneCommande;
use App\Entity\Produit;
use App\Enum\CommandeStatutEnum;
use App\Enum\ProduitEtatEnum;
use App\Enum\ProduitStatutEnum;
use App\Repository\CommandeRepository;
use App\Repository\ProduitRepository;
use App\Service\ExportService;
use PHPUnit\Framework\TestCase;

final class ExportServiceTest extends TestCase
{
    public function testExportVentesCsvContientLesLignesCommande(): void
    {
        $produit = (new Produit())
            ->setLibelle('Bureau pliant')
            ->setPhotoProduit('test.jpg')
            ->setEtat(ProduitEtatEnum::BON)
            ->setEtage('2')
            ->setPorte('B')
            ->setLargeur(120.0)
            ->setHauteur(75.0)
            ->setProfondeur(60.0)
            ->setNumeroInventaire('INV-001')
            ->setStatut(ProduitStatutEnum::RESERVE)
            ->setQuantite(1);

        $commande = (new Commande())
            ->setNumeroAgent('12345')
            ->setNom('Durand')
            ->setPrenom('Alice')
            ->setStatut(CommandeStatutEnum::VALIDEE);

        $ligne = (new LigneCommande())
            ->setCommande($commande)
            ->setProduit($produit)
            ->setQuantite(2);

        $commande->getLignesCommande()->add($ligne);

        $commandeRepository = $this->createMock(CommandeRepository::class);
        $commandeRepository->method('findForVentesExport')->willReturn([$commande]);

        $produitRepository = $this->createMock(ProduitRepository::class);
        $service = new ExportService($commandeRepository, $produitRepository);

        $csv = $service->exportVentesCsv();

        self::assertStringContainsString('commande_id;date_validation;numero_agent', $csv);
        self::assertStringContainsString('12345', $csv);
        self::assertStringContainsString('Bureau pliant', $csv);
        self::assertStringContainsString(';2', $csv);
    }

    public function testExportStockRestantCsvContientLesProduitsDisponibles(): void
    {
        $produit = (new Produit())
            ->setLibelle('Armoire metal')
            ->setPhotoProduit('stock.jpg')
            ->setEtat(ProduitEtatEnum::TRES_BON_ETAT)
            ->setTagTeletravailleur(true)
            ->setEtage('1')
            ->setPorte('A')
            ->setLargeur(80.0)
            ->setHauteur(190.0)
            ->setProfondeur(45.0)
            ->setNumeroInventaire('INV-002')
            ->setStatut(ProduitStatutEnum::DISPONIBLE)
            ->setQuantite(3);

        $commandeRepository = $this->createMock(CommandeRepository::class);
        $produitRepository = $this->createMock(ProduitRepository::class);
        $produitRepository->method('findForStockRestantExport')->willReturn([$produit]);

        $service = new ExportService($commandeRepository, $produitRepository);
        $csv = $service->exportStockRestantCsv();

        self::assertStringContainsString('produit_id;numero_inventaire;libelle', $csv);
        self::assertStringContainsString('Armoire metal', $csv);
        self::assertStringContainsString(';1;', $csv);
        self::assertStringContainsString(';3;disponible', $csv);
    }

    public function testExportComptabiliteCsvCalculeLesTotaux(): void
    {
        $produit = (new Produit())
            ->setLibelle('Chaise bureau')
            ->setPhotoProduit('chaise.jpg')
            ->setEtat(ProduitEtatEnum::BON)
            ->setEtage('3')
            ->setPorte('C')
            ->setLargeur(45.0)
            ->setHauteur(85.0)
            ->setProfondeur(45.0)
            ->setStatut(ProduitStatutEnum::RESERVE)
            ->setQuantite(1);

        $commande = (new Commande())
            ->setNumeroAgent('54321')
            ->setNom('Martin')
            ->setPrenom('Paul')
            ->setStatut(CommandeStatutEnum::A_PREPARER);

        $creneau = (new Creneau())
            ->setDateHeure(new \DateTimeImmutable('2026-03-21 09:00:00'))
            ->setHeureDebut(new \DateTimeImmutable('09:00:00'))
            ->setHeureFin(new \DateTimeImmutable('09:30:00'));

        $commande->setCreneau($creneau);

        $ligne1 = (new LigneCommande())->setCommande($commande)->setProduit($produit)->setQuantite(2);
        $ligne2 = (new LigneCommande())->setCommande($commande)->setProduit($produit)->setQuantite(1);
        $commande->getLignesCommande()->add($ligne1);
        $commande->getLignesCommande()->add($ligne2);

        $commandeRepository = $this->createMock(CommandeRepository::class);
        $commandeRepository->method('findForVentesExport')->willReturn([$commande]);

        $produitRepository = $this->createMock(ProduitRepository::class);
        $service = new ExportService($commandeRepository, $produitRepository);

        $csv = $service->exportComptabiliteCsv();

        self::assertStringContainsString('commande_id;date_validation;numero_agent', $csv);
        self::assertStringContainsString('54321', $csv);
        self::assertStringContainsString('2026-03-21;09:00;09:30;2;3', $csv);
    }
}
