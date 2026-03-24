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
    public function findAvailableDashboardPage(?string $etage, ?string $bureau, ?string $vnc, ?string $q, ?bool $teletravailleur, ?string $inventaireEtat, int $page, int $perPage): array
    {
        return $this->createAvailableDashboardQb($etage, $bureau, $vnc, $q, $teletravailleur, $inventaireEtat)
            ->setFirstResult(max(0, ($page - 1) * $perPage))
            ->setMaxResults(max(1, $perPage))
            ->getQuery()
            ->getResult();
    }

    public function countAvailableDashboard(?string $etage, ?string $bureau, ?string $vnc, ?string $q, ?bool $teletravailleur, ?string $inventaireEtat): int
    {
        return (int) $this->createAvailableDashboardQb($etage, $bureau, $vnc, $q, $teletravailleur, $inventaireEtat)
            ->select('COUNT(p.id)')
            ->resetDQLPart('orderBy')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countProduitsDisponibles(): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COALESCE(SUM(p.quantite), 0)')
            ->andWhere('p.statut = :statut')
            ->andWhere('p.quantite > 0')
            ->setParameter('statut', ProduitStatutEnum::DISPONIBLE)
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

    private function createAvailableDashboardQb(?string $etage, ?string $bureau, ?string $vnc, ?string $q, ?bool $teletravailleur, ?string $inventaireEtat): \Doctrine\ORM\QueryBuilder
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
        if ($inventaireEtat !== null && $inventaireEtat !== '') {
            if ($inventaireEtat === 'sans_numero') {
                $qb->andWhere('p.numeroInventaire IS NULL OR p.numeroInventaire = :emptyNumero')
                    ->setParameter('emptyNumero', '');
            } elseif ($inventaireEtat === 'non_decompose') {
                $qb
                    ->andWhere('p.numeroInventaire IS NOT NULL')
                    ->andWhere('p.numeroInventaire <> :emptyNumero')
                    ->andWhere('p.gestion IS NULL')
                    ->andWhere('p.annee IS NULL')
                    ->andWhere('p.type IS NULL')
                    ->andWhere('p.chrono IS NULL')
                    ->setParameter('emptyNumero', '');
            } elseif ($inventaireEtat === 'ok') {
                $qb
                    ->andWhere('p.numeroInventaire IS NOT NULL')
                    ->andWhere('p.numeroInventaire <> :emptyNumero')
                    ->andWhere('p.gestion IS NOT NULL')
                    ->andWhere('p.annee IS NOT NULL')
                    ->andWhere('p.type IS NOT NULL')
                    ->andWhere('p.chrono IS NOT NULL')
                    ->setParameter('emptyNumero', '');
            }
        }
        if ($q !== null && $q !== '') {
            $keyword = trim($q);
            if (preg_match('/^#(\d+)$/', $keyword, $matches) === 1) {
                $qb->andWhere('p.id = :productId')
                    ->setParameter('productId', (int) $matches[1]);

                return $qb;
            }

            $keyword = mb_strtolower($keyword);
            $escapedKeyword = addcslashes($keyword, '%_');
            $qb->andWhere('LOWER(p.libelle) LIKE :keyword OR LOWER(COALESCE(p.description, \'\')) LIKE :keyword')
                ->setParameter('keyword', '%'.$escapedKeyword.'%');
        }

        return $qb;
    }
}
