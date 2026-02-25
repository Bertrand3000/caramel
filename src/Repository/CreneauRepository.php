<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Creneau;
use App\Entity\JourLivraison;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class CreneauRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Creneau::class);
    }

    /** @return list<Creneau> */
    public function findByJourOrderedByHeure(JourLivraison $jour): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.jourLivraison = :jour')
            ->setParameter('jour', $jour)
            ->orderBy('c.heureDebut', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return list<Creneau> */
    public function findByJourWithCommandes(JourLivraison $jour): array
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.commandes', 'commande')
            ->addSelect('commande')
            ->andWhere('c.jourLivraison = :jour')
            ->setParameter('jour', $jour)
            ->orderBy('c.heureDebut', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
