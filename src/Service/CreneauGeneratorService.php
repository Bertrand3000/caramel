<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\GenerationResult;
use App\Entity\Creneau;
use App\Entity\JourLivraison;
use App\Enum\CreneauTypeEnum;
use App\Interface\CreneauGeneratorInterface;
use App\Repository\CreneauRepository;
use App\Repository\ParametreRepository;
use Doctrine\ORM\EntityManagerInterface;

class CreneauGeneratorService implements CreneauGeneratorInterface
{
    public function __construct(
        private readonly ParametreRepository $parametreRepository,
        private readonly CreneauRepository $creneauRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function generate(JourLivraison $jour): GenerationResult
    {
        $dureeMinutes = $this->readIntParam(['duree_creneau_minutes', 'dureeCreneauMinutes'], 30);
        $capaciteMax = $this->readIntParam(['capacite_creneau_max', 'capaciteMax'], 10);

        $creneauxExistants = $this->creneauRepository->findByJourOrderedByHeure($jour);
        $theorique = $this->buildTheoreticalGrid($jour, $dureeMinutes);
        $slotType = $this->resolveSlotType($creneauxExistants);

        $crees = 0;
        $supprimes = 0;
        $verrouilles = 0;
        $avertissements = [];
        $verrouillesParCle = [];

        foreach ($creneauxExistants as $creneau) {
            $key = $this->slotKey($creneau->getHeureDebut(), $creneau->getHeureFin());
            if ($creneau->getCapaciteUtilisee() > 0) {
                ++$verrouilles;
                $verrouillesParCle[$key] = true;
                if (!isset($theorique[$key])) {
                    $avertissements[] = sprintf('Créneau verrouillé conservé hors grille : %s.', $key);
                }
                continue;
            }

            ++$supprimes;
            $this->entityManager->remove($creneau);
        }

        foreach ($theorique as $key => $plage) {
            if (isset($verrouillesParCle[$key])) {
                $avertissements[] = sprintf('Conflit verrouillé ignoré pour %s.', $key);
                continue;
            }

            $this->entityManager->persist(
                $this->buildCreneau($jour, $plage['debut'], $plage['fin'], $capaciteMax, $slotType),
            );
            ++$crees;
        }

        $this->entityManager->flush();

        return new GenerationResult($crees, $supprimes, $verrouilles, array_values(array_unique($avertissements)));
    }

    /**
     * @return array<string, array{debut:\DateTimeImmutable, fin:\DateTimeImmutable}>
     */
    private function buildTheoreticalGrid(JourLivraison $jour, int $dureeMinutes): array
    {
        $segments = [
            [
                'debut' => $this->combineDateAndTime($jour->getDate(), $jour->getHeureOuverture()),
                'fin' => $this->combineDateAndTime($jour->getDate(), $jour->getHeureFermeture()),
            ],
        ];

        if (
            $jour->isCoupureMeridienne()
            && $jour->getHeureCoupureDebut() !== null
            && $jour->getHeureCoupureFin() !== null
        ) {
            $segments = [
                [
                    'debut' => $this->combineDateAndTime($jour->getDate(), $jour->getHeureOuverture()),
                    'fin' => $this->combineDateAndTime($jour->getDate(), $jour->getHeureCoupureDebut()),
                ],
                [
                    'debut' => $this->combineDateAndTime($jour->getDate(), $jour->getHeureCoupureFin()),
                    'fin' => $this->combineDateAndTime($jour->getDate(), $jour->getHeureFermeture()),
                ],
            ];
        }

        $grid = [];
        foreach ($segments as $segment) {
            $cursor = $segment['debut'];
            while (true) {
                $next = $cursor->modify(sprintf('+%d minutes', $dureeMinutes));
                if ($next > $segment['fin']) {
                    break;
                }

                $grid[$this->slotKey($cursor, $next)] = ['debut' => $cursor, 'fin' => $next];
                $cursor = $next;
            }
        }

        return $grid;
    }

    private function buildCreneau(
        JourLivraison $jour,
        \DateTimeImmutable $debut,
        \DateTimeImmutable $fin,
        int $capaciteMax,
        CreneauTypeEnum $slotType,
    ): Creneau {
        return (new Creneau())
            ->setJourLivraison($jour)
            ->setType($slotType)
            ->setDateHeure($debut)
            ->setHeureDebut(\DateTime::createFromImmutable($debut))
            ->setHeureFin(\DateTime::createFromImmutable($fin))
            ->setCapaciteMax($capaciteMax)
            ->setCapaciteUtilisee(0);
    }

    private function combineDateAndTime(\DateTimeImmutable $date, \DateTimeInterface $time): \DateTimeImmutable
    {
        return new \DateTimeImmutable(sprintf('%s %s', $date->format('Y-m-d'), $time->format('H:i:s')));
    }

    private function slotKey(\DateTimeInterface $debut, \DateTimeInterface $fin): string
    {
        return sprintf('%s-%s', $debut->format('H:i'), $fin->format('H:i'));
    }

    /**
     * @param list<Creneau> $creneauxExistants
     */
    private function resolveSlotType(array $creneauxExistants): CreneauTypeEnum
    {
        foreach ($creneauxExistants as $existing) {
            return $existing->getType();
        }

        return CreneauTypeEnum::GENERAL;
    }

    /**
     * @param list<string> $keys
     */
    private function readIntParam(array $keys, int $default): int
    {
        foreach ($keys as $key) {
            $param = $this->parametreRepository->findOneByKey($key);
            if ($param === null) {
                continue;
            }

            $value = (int) $param->getValeur();
            if ($value > 0) {
                return $value;
            }
        }

        return $default;
    }
}
