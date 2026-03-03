<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\AgentEligible;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class AgentEligibleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AgentEligible::class);
    }

    public function existsByNumeroAgent(string $numeroAgent): bool
    {
        $result = $this->createQueryBuilder('a')
            ->select('1')
            ->andWhere('a.numeroAgent = :numeroAgent')
            ->setParameter('numeroAgent', $numeroAgent)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $result !== null;
    }

    public function deleteAll(): void
    {
        $this->createQueryBuilder('a')
            ->delete()
            ->getQuery()
            ->execute();
    }
}
