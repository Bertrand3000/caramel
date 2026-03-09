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
    public function findAvailableDashboardPage(?string $etage, ?string $bureau, ?string $vnc, ?string $q, ?bool $teletravailleur, int $page, int $perPage): array
    {
        return $this->createAvailableDashboardQb($etage, $bureau, $vnc, $q, $teletravailleur)
            ->setFirstResult(max(0, ($page - 1) * $perPage))
            ->setMaxResults(max(1, $perPage))
            ->getQuery()
            ->getResult();
    }

    public function countAvailableDashboard(?string $etage, ?string $bureau, ?string $vnc, ?string $q, ?bool $teletravailleur): int
    {
        return (int) $this->createAvailableDashboardQb($etage, $bureau, $vnc, $q, $teletravailleur)
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

    /**
     * @return list<string>
     */
    public function findDistinctAvailableVncs(): array
    {
        $rows = $this->createQueryBuilder('p')
            ->select('DISTINCT p.vnc AS vnc')
            ->andWhere('p.statut = :statut')
            ->setParameter('statut', ProduitStatutEnum::DISPONIBLE)
            ->orderBy('p.vnc', 'ASC')
            ->getQuery()
            ->getArrayResult();

        return array_values(array_map(
            static fn (array $row): string => (string) $row['vnc'],
            $rows,
        ));
    }

    private function createAvailableDashboardQb(?string $etage, ?string $bureau, ?string $vnc, ?string $q, ?bool $teletravailleur): \Doctrine\ORM\QueryBuilder
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
        if ($vnc !== null && $vnc !== '') {
            $qb->andWhere('p.vnc = :vnc')->setParameter('vnc', $vnc);
        }
        if ($teletravailleur !== null) {
            $qb->andWhere('p.tagTeletravailleur = :teletravailleur')->setParameter('teletravailleur', $teletravailleur);
        }
        if ($q !== null && $q !== '') {
            $keyword = mb_strtolower(trim($q));
            $escapedKeyword = addcslashes($keyword, '%_');
            $qb->andWhere('LOWER(p.libelle) LIKE :keyword OR LOWER(COALESCE(p.description, \'\')) LIKE :keyword')
                ->setParameter('keyword', '%'.$escapedKeyword.'%');
        }

        return $qb;
    }
}
