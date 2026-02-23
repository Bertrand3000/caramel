<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Commande;
use App\Entity\CommandeContactTmp;
use App\Service\RgpdPurgeService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class RgpdPurgeServiceTest extends TestCase
{
    public function testAnonymizeCommandeRemovesTemporaryContact(): void
    {
        $commande = new Commande();
        $contact = (new CommandeContactTmp())->setCommande($commande);
        $commande->setCommandeContactTmp($contact);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('remove')->with($contact);
        $entityManager->expects(self::once())->method('flush');

        $service = new RgpdPurgeService($entityManager);
        $service->anonymizeCommande($commande);

        self::assertNull($commande->getCommandeContactTmp());
    }

    public function testAnonymizeCommandeSkipsWhenNoTemporaryContact(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('remove');
        $entityManager->expects(self::never())->method('flush');

        $service = new RgpdPurgeService($entityManager);
        $service->anonymizeCommande(new Commande());
    }
}
