<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Enum\CommandeProfilEnum;
use App\Enum\ProfilUtilisateur;
use App\Exception\CommandeDejaExistanteException;
use App\Repository\CommandeRepository;
use App\Service\CommandeLimitCheckerService;
use PHPUnit\Framework\TestCase;

final class CommandeLimitCheckerServiceTest extends TestCase
{
    public function testAssertPeutCommanderAgentSansCommandeExistante(): void
    {
        $repository = $this->createMock(CommandeRepository::class);
        $repository
            ->expects(self::once())
            ->method('hasCommandeActiveForNumeroAgentEtProfil')
            ->with('12345', CommandeProfilEnum::AGENT)
            ->willReturn(false);

        $service = new CommandeLimitCheckerService($repository);
        $service->assertPeutCommander('12345', ProfilUtilisateur::PUBLIC);

        self::assertTrue(true);
    }

    public function testAssertPeutCommanderCommandeActiveBloquee(): void
    {
        $this->expectException(CommandeDejaExistanteException::class);

        $repository = $this->createMock(CommandeRepository::class);
        $repository
            ->method('hasCommandeActiveForNumeroAgentEtProfil')
            ->with('12345', CommandeProfilEnum::AGENT)
            ->willReturn(true);

        $service = new CommandeLimitCheckerService($repository);
        $service->assertPeutCommander('12345', ProfilUtilisateur::PUBLIC);
    }

    public function testAssertPeutCommanderCommandeAnnuleeNeBloquePas(): void
    {
        $repository = $this->createMock(CommandeRepository::class);
        $repository
            ->expects(self::once())
            ->method('hasCommandeActiveForNumeroAgentEtProfil')
            ->with('12345', CommandeProfilEnum::AGENT)
            ->willReturn(false);

        $service = new CommandeLimitCheckerService($repository);
        $service->assertPeutCommander('12345', ProfilUtilisateur::PUBLIC);

        self::assertTrue(true);
    }

    public function testAssertPeutCommanderCommandeRetireeBloquee(): void
    {
        $this->expectException(CommandeDejaExistanteException::class);

        $repository = $this->createMock(CommandeRepository::class);
        $repository
            ->method('hasCommandeActiveForNumeroAgentEtProfil')
            ->with('12345', CommandeProfilEnum::AGENT)
            ->willReturn(true);

        $service = new CommandeLimitCheckerService($repository);
        $service->assertPeutCommander('12345', ProfilUtilisateur::PUBLIC);
    }

    public function testAssertPeutCommanderPartenaireToujoursAutorise(): void
    {
        $repository = $this->createMock(CommandeRepository::class);
        $repository->expects(self::never())->method('hasCommandeActiveForNumeroAgentEtProfil');

        $service = new CommandeLimitCheckerService($repository);
        $service->assertPeutCommander('12345', ProfilUtilisateur::PARTENAIRE);

        self::assertTrue(true);
    }

    public function testAssertPeutCommanderDmaxToujoursAutorise(): void
    {
        $repository = $this->createMock(CommandeRepository::class);
        $repository->expects(self::never())->method('hasCommandeActiveForNumeroAgentEtProfil');

        $service = new CommandeLimitCheckerService($repository);
        $service->assertPeutCommander('12345', ProfilUtilisateur::DMAX);

        self::assertTrue(true);
    }

    public function testAssertPeutCommanderTeletravailleurAvecCommandeAgentActiveAutorise(): void
    {
        $repository = $this->createMock(CommandeRepository::class);
        $repository
            ->expects(self::once())
            ->method('hasCommandeActiveForNumeroAgentEtProfil')
            ->with('12345', CommandeProfilEnum::TELETRAVAILLEUR)
            ->willReturn(false);

        $service = new CommandeLimitCheckerService($repository);
        $service->assertPeutCommander('12345', ProfilUtilisateur::TELETRAVAILLEUR);

        self::assertTrue(true);
    }

    public function testAssertPeutCommanderTeletravailleurAvecCommandeTeletravailleurActiveBloque(): void
    {
        $this->expectException(CommandeDejaExistanteException::class);

        $repository = $this->createMock(CommandeRepository::class);
        $repository
            ->method('hasCommandeActiveForNumeroAgentEtProfil')
            ->with('12345', CommandeProfilEnum::TELETRAVAILLEUR)
            ->willReturn(true);

        $service = new CommandeLimitCheckerService($repository);
        $service->assertPeutCommander('12345', ProfilUtilisateur::TELETRAVAILLEUR);
    }
}
