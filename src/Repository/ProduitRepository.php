<?php

declare(strict_types=1);

namespace App\Repository;

use App\DTO\ProduitFilterDTO;
use App\Entity\Produit;
use App\Enum\ProduitStatutEnum;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ProduitRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Produit::class);
    }

    /** @return array<int, Produit> */
    public function findAvailableWithFilter(?ProduitFilterDTO $filter): array
    {
        $qb = $this->createQueryBuilder('p')->andWhere('p.statut = :statut')->setParameter('statut', ProduitStatutEnum::DISPONIBLE);

        if ($filter?->tagTeletravailleur !== null) {
            $qb->andWhere('p.tagTeletravailleur = :tag')->setParameter('tag', $filter->tagTeletravailleur);
        }
        if ($filter?->etat !== null) {
            $qb->andWhere('p.etat = :etat')->setParameter('etat', $filter->etat);
        }

        return $qb->orderBy('p.id', 'DESC')->getQuery()->getResult();
    }

    /** @return list<Produit> */
    public function findForStockRestantExport(): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.statut = :statut')
            ->andWhere('p.quantite > 0')
            ->setParameter('statut', ProduitStatutEnum::DISPONIBLE)
            ->orderBy('p.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
