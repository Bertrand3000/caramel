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
            $this->createMock(CommandeRepository::class)
        );

        self::assertTrue($service->check('s', ProfilUtilisateur::PARTENAIRE, 999));
        self::assertSame(PHP_INT_MAX, $service->getQuotaRestant('s', ProfilUtilisateur::PARTENAIRE));
    }

    public function testTeletravailleurRespecteLimite(): void
    {
        $param = (new Parametre())->setCle('quota_articles_max')->setValeur('5');
        $paramRepo = $this->createMock(ParametreRepository::class);
        $paramRepo->method('findOneByKey')->willReturn($param);
        $commandeRepo = $this->createMock(CommandeRepository::class);
        $commandeRepo->method('countArticlesActifsForSession')->willReturn(2);

        $service = new QuotaCheckerService($paramRepo, $commandeRepo);

        self::assertTrue($service->check('s', ProfilUtilisateur::TELETRAVAILLEUR, 3));
        self::assertSame(3, $service->getQuotaRestant('s', ProfilUtilisateur::TELETRAVAILLEUR));
    }

    public function testQuotaParDefautEstTrois(): void
    {
        $paramRepo = $this->createMock(ParametreRepository::class);
        $paramRepo->method('findOneByKey')->willReturn(null);
        $commandeRepo = $this->createMock(CommandeRepository::class);
        $commandeRepo->method('countArticlesActifsForSession')->willReturn(1);

        $service = new QuotaCheckerService($paramRepo, $commandeRepo);

        self::assertTrue($service->check('s', ProfilUtilisateur::PUBLIC, 2));
        self::assertFalse($service->check('s', ProfilUtilisateur::PUBLIC, 3));
    }

    public function testQuotaLuDepuisParametre(): void
    {
        $param = (new Parametre())->setCle('quota_articles_max')->setValeur('7');
        $paramRepo = $this->createMock(ParametreRepository::class);
        $paramRepo->method('findOneByKey')->willReturn($param);
        $commandeRepo = $this->createMock(CommandeRepository::class);
        $commandeRepo->method('countArticlesActifsForSession')->willReturn(4);

        self::assertTrue((new QuotaCheckerService($paramRepo, $commandeRepo))->check('s', ProfilUtilisateur::PUBLIC, 3));
    }
}
