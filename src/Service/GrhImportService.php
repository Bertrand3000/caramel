<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\TeletravailleurListe;
use App\Interface\GrhImportServiceInterface;
use App\Repository\TeletravailleurListeRepository;
use Doctrine\ORM\EntityManagerInterface;

class GrhImportService implements GrhImportServiceInterface
{
    public function __construct(
        private readonly TeletravailleurListeRepository $repository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function importFromCsv(string $csvPath): int
    {
        $handle = fopen($csvPath, 'rb');
        if ($handle === false) {
            throw new \InvalidArgumentException('CSV introuvable.');
        }

        $count = 0;
        while (($row = fgetcsv($handle, separator: ';', escape: '\\')) !== false) {
            $numeroAgent = trim((string) ($row[0] ?? ''));
            if (!preg_match('/^\d{5}$/', $numeroAgent)) {
                continue;
            }

            $entity = (new TeletravailleurListe())->setNumeroAgent($numeroAgent);
            $this->entityManager->persist($entity);
            ++$count;
        }

        fclose($handle);
        $this->entityManager->flush();

        return $count;
    }

    public function replaceAll(string $csvPath): int
    {
        $this->entityManager->createQueryBuilder()->delete(TeletravailleurListe::class, 't')->getQuery()->execute();

        return $this->importFromCsv($csvPath);
    }
}
