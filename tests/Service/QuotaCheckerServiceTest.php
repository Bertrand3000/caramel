<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Parametre;
use App\Enum\ProfilUtilisateur;
use App\Repository\CommandeRepository;
use App\Repository\ParametreRepository;
use App\Service\QuotaCheckerService;
use PHPUnit\Framework\TestCase;

final class QuotaCheckerServiceTest extends TestCase
{
    public function testPartenaireEstExempte(): void
    {
        $service = new QuotaCheckerService(
            $this->createMock(ParametreRepository::class),
            $this->createMock(CommandeRepository::class),
        );

        self::assertTrue($service->check('s', ProfilUtilisateur::PARTENAIRE, 999));
        self::assertSame(PHP_INT_MAX, $service->getQuotaRestant('s', ProfilUtilisateur::PARTENAIRE));
    }

    public function testTeletravailleurRespecteLimiteParNumeroAgent(): void
    {
        $param = (new Parametre())->setCle('quota_articles_max')->setValeur('5');
        $paramRepo = $this->createMock(ParametreRepository::class);
        $paramRepo->method('findOneByKey')->willReturn($param);
        $commandeRepo = $this->createMock(CommandeRepository::class);
        $commandeRepo->method('countArticlesActifsForNumeroAgent')->with('12345')->willReturn(2);

        $service = new QuotaCheckerService($paramRepo, $commandeRepo);

        self::assertTrue($service->check('session-ignored', ProfilUtilisateur::TELETRAVAILLEUR, 3, '12345'));
        self::assertSame(3, $service->getQuotaRestant('session-ignored', ProfilUtilisateur::TELETRAVAILLEUR, '12345'));
    }

    public function testQuotaParDefautEstTroisPourPublicParNumeroAgent(): void
    {
        $paramRepo = $this->createMock(ParametreRepository::class);
        $paramRepo->method('findOneByKey')->willReturn(null);
        $commandeRepo = $this->createMock(CommandeRepository::class);
        $commandeRepo->method('countArticlesActifsForNumeroAgent')->with('54321')->willReturn(1);

        $service = new QuotaCheckerService($paramRepo, $commandeRepo);

        self::assertTrue($service->check('session-ignored', ProfilUtilisateur::PUBLIC, 2, '54321'));
        self::assertFalse($service->check('session-ignored', ProfilUtilisateur::PUBLIC, 3, '54321'));
    }

    public function testQuotaLuDepuisParametrePourPublicParNumeroAgent(): void
    {
        $param = (new Parametre())->setCle('quota_articles_max')->setValeur('7');
        $paramRepo = $this->createMock(ParametreRepository::class);
        $paramRepo->method('findOneByKey')->willReturn($param);
        $commandeRepo = $this->createMock(CommandeRepository::class);
        $commandeRepo->method('countArticlesActifsForNumeroAgent')->with('11111')->willReturn(4);

        $service = new QuotaCheckerService($paramRepo, $commandeRepo);

        self::assertTrue($service->check('session-ignored', ProfilUtilisateur::PUBLIC, 3, '11111'));
    }

    public function testPublicSansNumeroAgentValideDeclencheErreur(): void
    {
        $service = new QuotaCheckerService(
            $this->createMock(ParametreRepository::class),
            $this->createMock(CommandeRepository::class),
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Numero agent invalide');

        $service->check('s', ProfilUtilisateur::PUBLIC, 1, null);
    }

    public function testCanAddMoreItemsRespecteQuotaPourAgent(): void
    {
        $param = (new Parametre())->setCle('quota_articles_max')->setValeur('2');
        $paramRepo = $this->createMock(ParametreRepository::class);
        $paramRepo->method('findOneByKey')->willReturn($param);
        $service = new QuotaCheckerService($paramRepo, $this->createMock(CommandeRepository::class));

        self::assertTrue($service->canAddMoreItems(['ROLE_AGENT'], 1));
        self::assertFalse($service->canAddMoreItems(['ROLE_AGENT'], 2));
    }

    public function testCanAddMoreItemsToujoursVraiPourPartenaire(): void
    {
        $service = new QuotaCheckerService(
            $this->createMock(ParametreRepository::class),
            $this->createMock(CommandeRepository::class),
        );

        self::assertTrue($service->canAddMoreItems(['ROLE_PARTENAIRE'], 999));
    }

    public function testGetCartQuotaForRolesRetourneQuotaPourAgent(): void
    {
        $param = (new Parametre())->setCle('quota_articles_max')->setValeur('4');
        $paramRepo = $this->createMock(ParametreRepository::class);
        $paramRepo->method('findOneByKey')->willReturn($param);
        $service = new QuotaCheckerService($paramRepo, $this->createMock(CommandeRepository::class));

        self::assertSame(4, $service->getCartQuotaForRoles(['ROLE_AGENT']));
    }

    public function testGetCartQuotaForRolesRetourneNullPourPartenaire(): void
    {
        $service = new QuotaCheckerService(
            $this->createMock(ParametreRepository::class),
            $this->createMock(CommandeRepository::class),
        );

        self::assertNull($service->getCartQuotaForRoles(['ROLE_PARTENAIRE']));
    }
}
