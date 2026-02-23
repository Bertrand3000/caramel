<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Commande;
use App\Enum\CommandeStatutEnum;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class CommandeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Commande::class);
    }

    public function countArticlesActifsForSession(string $sessionId): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COALESCE(SUM(lc.quantite), 0)')
            ->leftJoin('c.lignesCommande', 'lc')
            ->andWhere('c.sessionId = :sessionId')
            ->andWhere('c.statut != :annulee')
            ->setParameter('sessionId', $sessionId)
            ->setParameter('annulee', CommandeStatutEnum::ANNULEE)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** @return list<Commande> */
    public function findRetireeOuAnnuleeWithContactTmp(): array
    {
        return $this->createQueryBuilder('c')
            ->innerJoin('c.commandeContactTmp', 'ct')
            ->andWhere('c.statut IN (:statuts)')
            ->setParameter('statuts', [
                CommandeStatutEnum::RETIREE,
                CommandeStatutEnum::ANNULEE,
            ])
            ->getQuery()
            ->getResult();
    }

    /** @return list<Commande> */
    public function findPretesForTodayOrderedBySlot(): array
    {
        $start = new \DateTimeImmutable('today');
        $end = $start->modify('+1 day');

        return $this->createQueryBuilder('c')
            ->leftJoin('c.creneau', 'creneau')
            ->addSelect('creneau')
            ->leftJoin('c.lignesCommande', 'ligneCommande')
            ->addSelect('ligneCommande')
            ->leftJoin('ligneCommande.produit', 'produit')
            ->addSelect('produit')
            ->andWhere('c.statut = :statut')
            ->andWhere('creneau.dateHeure >= :start')
            ->andWhere('creneau.dateHeure < :end')
            ->setParameter('statut', CommandeStatutEnum::PRETE)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('creneau.dateHeure', 'ASC')
            ->addOrderBy('creneau.heureDebut', 'ASC')
            ->getQuery()
            ->getResult();
    }

}
