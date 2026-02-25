<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Creneau;
use App\Entity\JourLivraison;
use App\Entity\Parametre;
use App\Repository\CreneauRepository;
use App\Repository\ParametreRepository;
use App\Service\CreneauGeneratorService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class CreneauGeneratorServiceTest extends TestCase
{
    public function testGenerateCasNominalSansCoupure(): void
    {
        $jour = (new JourLivraison())
            ->setDate(new \DateTimeImmutable('2030-03-21'))
            ->setHeureOuverture(new \DateTime('08:00:00'))
            ->setHeureFermeture(new \DateTime('10:00:00'))
            ->setCoupureMeridienne(false);

        [$service, $created] = $this->buildService($jour, []);
        $result = $service->generate($jour);

        self::assertSame(4, $result->crees);
        self::assertSame(0, $result->supprimes);
        self::assertSame(0, $result->verrouilles);
        self::assertSame([], $result->avertissements);
        self::assertCount(4, $created);
    }

    public function testGenerateAvecCoupureMeridienneSansChevauchement(): void
    {
        $jour = (new JourLivraison())
            ->setDate(new \DateTimeImmutable('2030-03-22'))
            ->setHeureOuverture(new \DateTime('08:00:00'))
            ->setHeureFermeture(new \DateTime('12:00:00'))
            ->setCoupureMeridienne(true)
            ->setHeureCoupureDebut(new \DateTime('09:00:00'))
            ->setHeureCoupureFin(new \DateTime('10:00:00'));

        [$service, $created] = $this->buildService($jour, []);
        $result = $service->generate($jour);

        self::assertSame(6, $result->crees);
        foreach ($created as $slot) {
            $start = $slot->getHeureDebut()->format('H:i');
            $end = $slot->getHeureFin()->format('H:i');
            self::assertTrue($end <= '09:00' || $start >= '10:00');
        }
    }

    public function testGenerateOptionCPreserveVerrouillesEtRemplaceLibres(): void
    {
        $jour = (new JourLivraison())
            ->setDate(new \DateTimeImmutable('2030-03-23'))
            ->setHeureOuverture(new \DateTime('08:00:00'))
            ->setHeureFermeture(new \DateTime('09:00:00'))
            ->setCoupureMeridienne(false);

        $locked = $this->slot($jour, '08:00', '08:30', 1);
        $free = $this->slot($jour, '08:30', '09:00', 0);
        [$service] = $this->buildService($jour, [$locked, $free], 1);

        $result = $service->generate($jour);

        self::assertSame(1, $result->crees);
        self::assertSame(1, $result->supprimes);
        self::assertSame(1, $result->verrouilles);
    }

    public function testGenerateSignaleConflitHoraireVerrouille(): void
    {
        $jour = (new JourLivraison())
            ->setDate(new \DateTimeImmutable('2030-03-24'))
            ->setHeureOuverture(new \DateTime('08:00:00'))
            ->setHeureFermeture(new \DateTime('08:30:00'))
            ->setCoupureMeridienne(false);

        $locked = $this->slot($jour, '08:00', '08:30', 1);
        [$service] = $this->buildService($jour, [$locked], 0);

        $result = $service->generate($jour);

        self::assertSame(0, $result->crees);
        self::assertSame(1, $result->verrouilles);
        self::assertCount(1, $result->avertissements);
        self::assertStringContainsString('Conflit verrouillé ignoré', $result->avertissements[0]);
    }

    /**
     * @param list<Creneau> $existing
     * @return array{0:CreneauGeneratorService,1:\ArrayObject<int, Creneau>}
     */
    private function buildService(JourLivraison $jour, array $existing, int $expectedRemoved = 0): array
    {
        $created = new \ArrayObject();
        $paramRepo = $this->createMock(ParametreRepository::class);
        $paramRepo->method('findOneByKey')->willReturnCallback(function (string $key): ?Parametre {
            return match ($key) {
                'duree_creneau_minutes' => (new Parametre())->setCle($key)->setValeur('30'),
                'capacite_creneau_max' => (new Parametre())->setCle($key)->setValeur('10'),
                default => null,
            };
        });

        $creneauRepo = $this->createMock(CreneauRepository::class);
        $creneauRepo->method('findByJourOrderedByHeure')->with($jour)->willReturn($existing);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::any())->method('persist')->willReturnCallback(function (Creneau $creneau) use ($created): void {
            $created[] = $creneau;
        });
        $entityManager->expects(self::exactly($expectedRemoved))->method('remove');
        $entityManager->expects(self::once())->method('flush');

        return [new CreneauGeneratorService($paramRepo, $creneauRepo, $entityManager), $created];
    }

    private function slot(JourLivraison $jour, string $start, string $end, int $used): Creneau
    {
        return (new Creneau())
            ->setJourLivraison($jour)
            ->setDateHeure(new \DateTimeImmutable(sprintf('%s %s:00', $jour->getDate()->format('Y-m-d'), $start)))
            ->setHeureDebut(new \DateTime(sprintf('%s:00', $start)))
            ->setHeureFin(new \DateTime(sprintf('%s:00', $end)))
            ->setCapaciteMax(10)
            ->setCapaciteUtilisee($used);
    }
}
