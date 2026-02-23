<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\RgpdPurgeCommand;
use App\Entity\Commande;
use App\Repository\CommandeRepository;
use App\Service\PurgeServiceInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class RgpdPurgeCommandTest extends TestCase
{
    public function testExecutePurgesAllOrphanContacts(): void
    {
        $commande1 = new Commande();
        $commande2 = new Commande();

        $repository = $this->createMock(CommandeRepository::class);
        $repository->expects(self::once())
            ->method('findRetireeOuAnnuleeWithContactTmp')
            ->willReturn([$commande1, $commande2]);

        $purgeService = $this->createMock(PurgeServiceInterface::class);
        $purgeService->expects(self::exactly(2))
            ->method('anonymizeCommande')
            ->with(self::logicalOr($commande1, $commande2));

        $commandTester = new CommandTester(new RgpdPurgeCommand($repository, $purgeService));
        $exitCode = $commandTester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('2 commande(s) purgée(s).', $commandTester->getDisplay());
    }
}
