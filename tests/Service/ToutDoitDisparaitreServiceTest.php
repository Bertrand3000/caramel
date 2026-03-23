<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Commande;
use App\Entity\JourLivraison;
use App\Entity\Parametre;
use App\Enum\CommandeProfilEnum;
use App\Enum\ProfilUtilisateur;
use App\Repository\CommandeRepository;
use App\Repository\JourLivraisonRepository;
use App\Repository\ParametreRepository;
use App\Service\ToutDoitDisparaitreService;
use PHPUnit\Framework\TestCase;

final class ToutDoitDisparaitreServiceTest extends TestCase
{
    public function testFindCommandeACompleterRetourneNullQuandModeInactif(): void
    {
        $paramRepo = $this->createMock(ParametreRepository::class);
        $paramRepo->method('findOneByKey')->willReturn(null);

        $jourRepo = $this->createMock(JourLivraisonRepository::class);
        $jourRepo->expects(self::never())->method('findNextOpenDeliveryDayFrom');

        $commandeRepo = $this->createMock(CommandeRepository::class);
        $commandeRepo->expects(self::never())->method('findDerniereCommandeActiveAvecCreneauPourNumeroAgentEtProfilLeJour');

        $service = new ToutDoitDisparaitreService($paramRepo, $jourRepo, $commandeRepo);

        self::assertNull($service->findCommandeACompleter('12345', ProfilUtilisateur::PUBLIC));
        self::assertFalse($service->isEnabledForProfil(ProfilUtilisateur::PUBLIC));
    }

    public function testFindCommandeACompleterRetourneLaCommandeDuProchainJourOuvert(): void
    {
        $param = (new Parametre())->setCle('mode_tout_doit_disparaitre')->setValeur('1');
        $paramRepo = $this->createMock(ParametreRepository::class);
        $paramRepo->method('findOneByKey')->with('mode_tout_doit_disparaitre')->willReturn($param);

        $jour = (new JourLivraison())->setDate(new \DateTimeImmutable('2030-03-30'));
        $jourRepo = $this->createMock(JourLivraisonRepository::class);
        $jourRepo->expects(self::once())->method('findNextOpenDeliveryDayFrom')->willReturn($jour);

        $commande = new Commande();
        $commandeRepo = $this->createMock(CommandeRepository::class);
        $commandeRepo->expects(self::once())
            ->method('findDerniereCommandeActiveAvecCreneauPourNumeroAgentEtProfilLeJour')
            ->with('12345', CommandeProfilEnum::AGENT, $jour->getDate())
            ->willReturn($commande);

        $service = new ToutDoitDisparaitreService($paramRepo, $jourRepo, $commandeRepo);

        self::assertSame($commande, $service->findCommandeACompleter('12345', ProfilUtilisateur::PUBLIC));
        self::assertTrue($service->isEnabledForProfil(ProfilUtilisateur::PUBLIC));
        self::assertFalse($service->isEnabledForProfil(ProfilUtilisateur::TELETRAVAILLEUR));
    }
}
