<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\TeletravailleurListe;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class TeletravailleurListeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TeletravailleurListe::class);
    }

    public function existsByNumeroAgentAndNomOrPrenom(
        string $numeroAgent,
        string $nom,
        string $prenom,
    ): bool {
        $result = $this->createQueryBuilder('t')
            ->select('1')
            ->andWhere('t.numeroAgent = :numeroAgent')
            ->andWhere('(UPPER(t.nom) = :nom OR UPPER(t.prenom) = :prenom)')
            ->setParameter('numeroAgent', $numeroAgent)
            ->setParameter('nom', mb_strtoupper($nom))
            ->setParameter('prenom', mb_strtoupper($prenom))
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $result !== null;
    }

    public function deleteAll(): void
    {
        $this->createQueryBuilder('t')
            ->delete()
            ->getQuery()
            ->execute();
    }
}
