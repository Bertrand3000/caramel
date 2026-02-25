<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\JourLivraison;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class JourLivraisonRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, JourLivraison::class);
    }

    /** @return list<JourLivraison> */
    public function findActifsOrderedByDate(): array
    {
        return $this->createQueryBuilder('j')
            ->andWhere('j.actif = :actif')
            ->setParameter('actif', true)
            ->orderBy('j.date', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findPremierJourNonPleinAvant(\DateTimeImmutable $date): ?JourLivraison
    {
        return $this->createQueryBuilder('j')
            ->leftJoin('j.creneaux', 'c')
            ->andWhere('j.actif = :actif')
            ->andWhere('j.exigerJourneePleine = :exiger')
            ->andWhere('j.date < :date')
            ->groupBy('j.id')
            ->having('COUNT(c.id) = 0 OR SUM(CASE WHEN c.capaciteUtilisee < c.capaciteMax THEN 1 ELSE 0 END) > 0')
            ->setParameter('actif', true)
            ->setParameter('exiger', true)
            ->setParameter('date', $date->format('Y-m-d'))
            ->orderBy('j.date', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @return list<JourLivraison> */
    public function findAllWithCreneauxOrderedByDate(): array
    {
        return $this->createQueryBuilder('j')
            ->leftJoin('j.creneaux', 'c')
            ->addSelect('c')
            ->orderBy('j.date', 'ASC')
            ->addOrderBy('j.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
