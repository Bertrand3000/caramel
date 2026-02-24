<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\RegleTagger;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class RegleTaggerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RegleTagger::class);
    }

    public function findLatestMatchingRule(string $libelle): ?RegleTagger
    {
        $normalizedLibelle = mb_strtolower(trim($libelle));
        if ($normalizedLibelle === '') {
            return null;
        }

        return $this->createQueryBuilder('r')
            ->andWhere("LOWER(:libelle) LIKE CONCAT('%', LOWER(r.libelleContains), '%')")
            ->andWhere('r.libelleContains != :empty')
            ->setParameter('libelle', $normalizedLibelle)
            ->setParameter('empty', '')
            ->orderBy('r.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
