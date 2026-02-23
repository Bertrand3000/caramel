<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\CommandeContactTmp;
use App\Entity\TeletravailleurListe;
use App\Interface\GrhMatcherServiceInterface;
use App\Repository\TeletravailleurListeRepository;
use Doctrine\ORM\EntityManagerInterface;

class GrhMatcherService implements GrhMatcherServiceInterface
{
    public function __construct(
        private readonly TeletravailleurListeRepository $repository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function matchContact(CommandeContactTmp $contact): bool
    {
        $commande = $contact->getCommande();
        $numeroAgent = trim((string) $commande->getNumeroAgent());
        $nom = trim((string) $commande->getNom());
        $prenom = trim((string) $commande->getPrenom());

        if ($numeroAgent === '' || ($nom === '' && $prenom === '')) {
            return false;
        }

        return $this->repository->existsByNumeroAgentAndNomOrPrenom($numeroAgent, $nom, $prenom);
    }

    public function processImport(array $csvData): int
    {
        $connection = $this->entityManager->getConnection();
        $connection->beginTransaction();

        try {
            $this->repository->deleteAll();

            $count = 0;
            foreach ($csvData as $row) {
                $numeroAgent = trim((string) ($row['numero_agent'] ?? ''));
                if (!preg_match('/^\d{5}$/', $numeroAgent)) {
                    continue;
                }

                $entity = (new TeletravailleurListe())
                    ->setNumeroAgent($numeroAgent)
                    ->setNom($this->normalizeValue($row['nom'] ?? null))
                    ->setPrenom($this->normalizeValue($row['prenom'] ?? null));

                $this->entityManager->persist($entity);
                ++$count;

                if ($count % 500 === 0) {
                    $this->entityManager->flush();
                    $this->entityManager->clear();
                }
            }

            $this->entityManager->flush();
            $connection->commit();

            return $count;
        } catch (\Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }
    }

    private function normalizeValue(mixed $value): ?string
    {
        $normalized = trim((string) $value);

        return $normalized === '' ? null : $normalized;
    }
}
