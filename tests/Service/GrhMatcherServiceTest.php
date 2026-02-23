<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Commande;
use App\Entity\CommandeContactTmp;
use App\Repository\TeletravailleurListeRepository;
use App\Service\GrhMatcherService;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class GrhMatcherServiceTest extends TestCase
{
    public function testMatchContactReturnsTrueForExactName(): void
    {
        $repo = $this->createMock(TeletravailleurListeRepository::class);
        $repo->expects(self::once())
            ->method('existsByNumeroAgentAndNomOrPrenom')
            ->with('12345', 'Dupont', 'Jean')
            ->willReturn(true);

        $service = new GrhMatcherService($repo, $this->createMock(EntityManagerInterface::class));

        self::assertTrue($service->matchContact($this->buildContact('12345', 'Dupont', 'Jean')));
    }

    public function testMatchContactReturnsTrueWithCaseDifference(): void
    {
        $repo = $this->createMock(TeletravailleurListeRepository::class);
        $repo->expects(self::once())
            ->method('existsByNumeroAgentAndNomOrPrenom')
            ->with('12345', 'dupont', 'jean')
            ->willReturn(true);

        $service = new GrhMatcherService($repo, $this->createMock(EntityManagerInterface::class));

        self::assertTrue($service->matchContact($this->buildContact('12345', 'dupont', 'jean')));
    }

    public function testMatchContactReturnsFalseForWrongNumeroAgent(): void
    {
        $repo = $this->createMock(TeletravailleurListeRepository::class);
        $repo->expects(self::once())
            ->method('existsByNumeroAgentAndNomOrPrenom')
            ->with('99999', 'Dupont', 'Jean')
            ->willReturn(false);

        $service = new GrhMatcherService($repo, $this->createMock(EntityManagerInterface::class));

        self::assertFalse($service->matchContact($this->buildContact('99999', 'Dupont', 'Jean')));
    }

    public function testProcessImportImportsTenRows(): void
    {
        $repo = $this->createMock(TeletravailleurListeRepository::class);
        $repo->expects(self::once())->method('deleteAll');

        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('beginTransaction');
        $connection->expects(self::once())->method('commit');
        $connection->expects(self::never())->method('rollBack');

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('getConnection')->willReturn($connection);
        $entityManager->expects(self::exactly(10))->method('persist');
        $entityManager->expects(self::once())->method('flush');
        $entityManager->expects(self::never())->method('clear');

        $service = new GrhMatcherService($repo, $entityManager);

        $rows = [];
        for ($i = 0; $i < 10; ++$i) {
            $rows[] = [
                'numero_agent' => str_pad((string) (10000 + $i), 5, '0', STR_PAD_LEFT),
                'nom' => 'Nom'.$i,
                'prenom' => 'Prenom'.$i,
            ];
        }

        self::assertSame(10, $service->processImport($rows));
    }

    private function buildContact(string $numeroAgent, string $nom, string $prenom): CommandeContactTmp
    {
        $commande = (new Commande())
            ->setNumeroAgent($numeroAgent)
            ->setNom($nom)
            ->setPrenom($prenom);

        return (new CommandeContactTmp())->setCommande($commande);
    }
}
