<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\Creneau;
use App\Entity\JourLivraison;
use App\Repository\JourLivraisonRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class JourLivraisonRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private JourLivraisonRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $this->repository = static::getContainer()->get(JourLivraisonRepository::class);
        $this->cleanupJourLivraisonData();
    }

    public function testFindPremierJourNonPleinAvantRetourneNullSiTousPleins(): void
    {
        $jour = $this->createJour('2091-03-01', true, true);
        $this->createCreneau($jour, '08:00', '08:30', 2, 2);
        $this->entityManager->flush();

        $result = $this->repository->findPremierJourNonPleinAvant(
            new \DateTimeImmutable('2091-03-10'),
        );

        self::assertNull($result);
    }

    public function testFindPremierJourNonPleinAvantRetourneLeBonJour(): void
    {
        $jourPlein = $this->createJour('2092-03-01', true, true);
        $this->createCreneau($jourPlein, '08:00', '08:30', 1, 1);
        $jourNonPlein = $this->createJour('2092-03-02', true, true);
        $this->createCreneau($jourNonPlein, '08:00', '08:30', 0, 1);
        $this->entityManager->flush();

        $result = $this->repository->findPremierJourNonPleinAvant(
            new \DateTimeImmutable('2092-03-10'),
        );

        self::assertInstanceOf(JourLivraison::class, $result);
        self::assertSame('2092-03-02', $result->getDate()->format('Y-m-d'));
    }

    public function testFindPremierJourNonPleinAvantIgnoreExigerJourneePleineFalse(): void
    {
        $jour = $this->createJour('2093-03-01', true, false);
        $this->createCreneau($jour, '08:00', '08:30', 0, 1);
        $this->entityManager->flush();

        $result = $this->repository->findPremierJourNonPleinAvant(
            new \DateTimeImmutable('2093-03-10'),
        );

        self::assertNull($result);
    }

    public function testFindPremierJourNonPleinAvantIgnoreJoursInactifs(): void
    {
        $jour = $this->createJour('2094-03-01', false, true);
        $this->createCreneau($jour, '08:00', '08:30', 0, 1);
        $this->entityManager->flush();

        $result = $this->repository->findPremierJourNonPleinAvant(
            new \DateTimeImmutable('2094-03-10'),
        );

        self::assertNull($result);
    }

    private function createJour(string $date, bool $actif, bool $exiger): JourLivraison
    {
        $jour = (new JourLivraison())
            ->setDate(new \DateTimeImmutable($date))
            ->setActif($actif)
            ->setExigerJourneePleine($exiger)
            ->setHeureOuverture(new \DateTime('08:00:00'))
            ->setHeureFermeture(new \DateTime('10:00:00'));
        $this->entityManager->persist($jour);

        return $jour;
    }

    private function createCreneau(JourLivraison $jour, string $start, string $end, int $used, int $max): void
    {
        $date = $jour->getDate()->format('Y-m-d');
        $creneau = (new Creneau())
            ->setJourLivraison($jour)
            ->setDateHeure(new \DateTimeImmutable(sprintf('%s %s:00', $date, $start)))
            ->setHeureDebut(new \DateTime(sprintf('%s:00', $start)))
            ->setHeureFin(new \DateTime(sprintf('%s:00', $end)))
            ->setCapaciteUtilisee($used)
            ->setCapaciteMax($max);

        $this->entityManager->persist($creneau);
    }

    private function cleanupJourLivraisonData(): void
    {
        $connection = $this->entityManager->getConnection();
        $connection->executeStatement('DELETE FROM commandes WHERE creneau_id IN (SELECT id FROM creneaux WHERE jour_livraison_id IS NOT NULL)');
        $connection->executeStatement('DELETE FROM creneaux WHERE jour_livraison_id IS NOT NULL');
        $connection->executeStatement('DELETE FROM jours_livraison');
    }
}
