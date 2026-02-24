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

    /**
     * @return array<int, Produit>
     */
    public function findAvailableDashboardPage(?string $etage, ?string $bureau, int $page, int $perPage): array
    {
        return $this->createAvailableDashboardQb($etage, $bureau)
            ->setFirstResult(max(0, ($page - 1) * $perPage))
            ->setMaxResults(max(1, $perPage))
            ->getQuery()
            ->getResult();
    }

    public function countAvailableDashboard(?string $etage, ?string $bureau): int
    {
        return (int) $this->createAvailableDashboardQb($etage, $bureau)
            ->select('COUNT(p.id)')
            ->resetDQLPart('orderBy')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return list<string>
     */
    public function findDistinctAvailableEtages(): array
    {
        $rows = $this->createQueryBuilder('p')
            ->select('DISTINCT p.etage AS etage')
            ->andWhere('p.statut = :statut')
            ->andWhere('p.etage <> :empty')
            ->setParameter('statut', ProduitStatutEnum::DISPONIBLE)
            ->setParameter('empty', '')
            ->orderBy('p.etage', 'ASC')
            ->getQuery()
            ->getArrayResult();

        return array_values(array_map(
            static fn (array $row): string => (string) $row['etage'],
            $rows,
        ));
    }

    /**
     * @return list<string>
     */
    public function findDistinctAvailablePortes(): array
    {
        $rows = $this->createQueryBuilder('p')
            ->select('DISTINCT p.porte AS porte')
            ->andWhere('p.statut = :statut')
            ->andWhere('p.porte <> :empty')
            ->setParameter('statut', ProduitStatutEnum::DISPONIBLE)
            ->setParameter('empty', '')
            ->orderBy('p.porte', 'ASC')
            ->getQuery()
            ->getArrayResult();

        return array_values(array_map(
            static fn (array $row): string => (string) $row['porte'],
            $rows,
        ));
    }

    private function createAvailableDashboardQb(?string $etage, ?string $bureau): \Doctrine\ORM\QueryBuilder
    {
        $qb = $this->createQueryBuilder('p')
            ->andWhere('p.statut = :statut')
            ->setParameter('statut', ProduitStatutEnum::DISPONIBLE)
            ->orderBy('p.id', 'DESC');

        if ($etage !== null && $etage !== '') {
            $qb->andWhere('p.etage = :etage')->setParameter('etage', $etage);
        }
        if ($bureau !== null && $bureau !== '') {
            $qb->andWhere('p.porte = :bureau')->setParameter('bureau', $bureau);
        }

        return $qb;
    }
}
