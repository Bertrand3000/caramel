<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Enum\CommandeProfilEnum;
use App\Enum\ProfilUtilisateur;
use App\Exception\CommandeDejaExistanteException;
use App\Repository\CommandeRepository;
use App\Repository\ParametreRepository;
use App\Service\CommandeLimitCheckerService;
use PHPUnit\Framework\TestCase;

final class CommandeLimitCheckerServiceTest extends TestCase
{
    public function testAssertPeutCommanderAgentSansCommandeExistante(): void
    {
        $repository = $this->createMock(CommandeRepository::class);
        $repository
            ->expects(self::once())
            ->method('countCommandesActivesForNumeroAgentEtProfil')
            ->with('12345', CommandeProfilEnum::AGENT)
            ->willReturn(0);

        $service = new CommandeLimitCheckerService($repository, $this->mockParamRepo());
        $service->assertPeutCommander('12345', ProfilUtilisateur::PUBLIC);

        self::assertTrue(true);
    }

    public function testAssertPeutCommanderCommandeActiveBloquee(): void
    {
        $this->expectException(CommandeDejaExistanteException::class);

        $repository = $this->createMock(CommandeRepository::class);
        $repository
            ->method('countCommandesActivesForNumeroAgentEtProfil')
            ->with('12345', CommandeProfilEnum::AGENT)
            ->willReturn(2);

        $service = new CommandeLimitCheckerService($repository, $this->mockParamRepo());
        $service->assertPeutCommander('12345', ProfilUtilisateur::PUBLIC);
    }

    public function testAssertPeutCommanderCommandeAnnuleeNeBloquePas(): void
    {
        $repository = $this->createMock(CommandeRepository::class);
        $repository
            ->expects(self::once())
            ->method('countCommandesActivesForNumeroAgentEtProfil')
            ->with('12345', CommandeProfilEnum::AGENT)
            ->willReturn(0);

        $service = new CommandeLimitCheckerService($repository, $this->mockParamRepo());
        $service->assertPeutCommander('12345', ProfilUtilisateur::PUBLIC);

        self::assertTrue(true);
    }

    public function testAssertPeutCommanderCommandeRetireeBloquee(): void
    {
        $this->expectException(CommandeDejaExistanteException::class);

        $repository = $this->createMock(CommandeRepository::class);
        $repository
            ->method('countCommandesActivesForNumeroAgentEtProfil')
            ->with('12345', CommandeProfilEnum::AGENT)
            ->willReturn(2);

        $service = new CommandeLimitCheckerService($repository, $this->mockParamRepo());
        $service->assertPeutCommander('12345', ProfilUtilisateur::PUBLIC);
    }

    public function testAssertPeutCommanderPartenaireToujoursAutorise(): void
    {
        $repository = $this->createMock(CommandeRepository::class);
        $repository->expects(self::never())->method('countCommandesActivesForNumeroAgentEtProfil');

        $service = new CommandeLimitCheckerService($repository, $this->mockParamRepo());
        $service->assertPeutCommander('12345', ProfilUtilisateur::PARTENAIRE);

        self::assertTrue(true);
    }

    public function testAssertPeutCommanderDmaxToujoursAutorise(): void
    {
        $repository = $this->createMock(CommandeRepository::class);
        $repository->expects(self::never())->method('countCommandesActivesForNumeroAgentEtProfil');

        $service = new CommandeLimitCheckerService($repository, $this->mockParamRepo());
        $service->assertPeutCommander('12345', ProfilUtilisateur::DMAX);

        self::assertTrue(true);
    }

    public function testAssertPeutCommanderTeletravailleurAvecCommandeAgentActiveAutorise(): void
    {
        $repository = $this->createMock(CommandeRepository::class);
        $repository
            ->expects(self::once())
            ->method('countCommandesActivesForNumeroAgentEtProfil')
            ->with('12345', CommandeProfilEnum::TELETRAVAILLEUR)
            ->willReturn(0);

        $service = new CommandeLimitCheckerService($repository, $this->mockParamRepo());
        $service->assertPeutCommander('12345', ProfilUtilisateur::TELETRAVAILLEUR);

        self::assertTrue(true);
    }

    public function testAssertPeutCommanderTeletravailleurAvecCommandeTeletravailleurActiveBloque(): void
    {
        $this->expectException(CommandeDejaExistanteException::class);

        $repository = $this->createMock(CommandeRepository::class);
        $repository
            ->method('countCommandesActivesForNumeroAgentEtProfil')
            ->with('12345', CommandeProfilEnum::TELETRAVAILLEUR)
            ->willReturn(1);

        $service = new CommandeLimitCheckerService($repository, $this->mockParamRepo());
        $service->assertPeutCommander('12345', ProfilUtilisateur::TELETRAVAILLEUR);
    }

    public function testAssertPeutCommanderAgentAvecUneCommandeActiveEstAutoriseParDefaut(): void
    {
        $repository = $this->createMock(CommandeRepository::class);
        $repository
            ->expects(self::once())
            ->method('countCommandesActivesForNumeroAgentEtProfil')
            ->with('12345', CommandeProfilEnum::AGENT)
            ->willReturn(1);

        $service = new CommandeLimitCheckerService($repository, $this->mockParamRepo());
        $service->assertPeutCommander('12345', ProfilUtilisateur::PUBLIC);

        self::assertTrue(true);
    }

    public function testAssertPeutCommanderAgentRespecteParametrePersonnalise(): void
    {
        $this->expectException(CommandeDejaExistanteException::class);

        $repository = $this->createMock(CommandeRepository::class);
        $repository
            ->method('countCommandesActivesForNumeroAgentEtProfil')
            ->with('12345', CommandeProfilEnum::AGENT)
            ->willReturn(1);

        $service = new CommandeLimitCheckerService($repository, $this->mockParamRepo('1', '1'));
        $service->assertPeutCommander('12345', ProfilUtilisateur::PUBLIC);
    }

    private function mockParamRepo(?string $maxAgents = null, ?string $maxTeletravailleurs = null): ParametreRepository
    {
        $parametreRepository = $this->createMock(ParametreRepository::class);
        $parametreRepository
            ->method('findOneByKey')
            ->willReturnCallback(static function (string $key) use ($maxAgents, $maxTeletravailleurs) {
                $parametre = new \App\Entity\Parametre();
                if ($key === 'max_commandes_agents' && $maxAgents !== null) {
                    return $parametre->setCle($key)->setValeur($maxAgents);
                }
                if ($key === 'max_commandes_teletravailleurs' && $maxTeletravailleurs !== null) {
                    return $parametre->setCle($key)->setValeur($maxTeletravailleurs);
                }

                return null;
            });

        return $parametreRepository;
    }
}
