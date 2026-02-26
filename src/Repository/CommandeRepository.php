<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Commande;
use App\Enum\CommandeProfilEnum;
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
            ->select('COUNT(lc.id)')
            ->leftJoin('c.lignesCommande', 'lc')
            ->andWhere('c.sessionId = :sessionId')
            ->andWhere('c.statut != :annulee')
            ->setParameter('sessionId', $sessionId)
            ->setParameter('annulee', CommandeStatutEnum::ANNULEE)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** @return list<Commande> */
    public function findEnAttenteValidationWithRelations(): array
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.creneau', 'creneau')
            ->addSelect('creneau')
            ->leftJoin('c.commandeContactTmp', 'contact')
            ->addSelect('contact')
            ->andWhere('c.statut = :statut')
            ->setParameter('statut', CommandeStatutEnum::EN_ATTENTE_VALIDATION)
            ->orderBy('c.dateValidation', 'ASC')
            ->addOrderBy('c.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findOneForSuiviCommandes(int $id): ?Commande
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.creneau', 'creneau')
            ->addSelect('creneau')
            ->leftJoin('c.commandeContactTmp', 'contact')
            ->addSelect('contact')
            ->leftJoin('c.lignesCommande', 'ligne')
            ->addSelect('ligne')
            ->leftJoin('ligne.produit', 'produit')
            ->addSelect('produit')
            ->andWhere('c.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @return list<Commande> */
    public function findEnAttenteValidationByNumeroAgent(string $numeroAgent): array
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.commandeContactTmp', 'contact')
            ->addSelect('contact')
            ->andWhere('c.numeroAgent = :numeroAgent')
            ->andWhere('c.statut = :statut')
            ->setParameter('numeroAgent', $numeroAgent)
            ->setParameter('statut', CommandeStatutEnum::EN_ATTENTE_VALIDATION)
            ->getQuery()
            ->getResult();
    }

    public function countArticlesActifsForNumeroAgent(string $numeroAgent): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(lc.id)')
            ->leftJoin('c.lignesCommande', 'lc')
            ->andWhere('c.numeroAgent = :numeroAgent')
            ->andWhere('c.statut != :annulee')
            ->setParameter('numeroAgent', $numeroAgent)
            ->setParameter('annulee', CommandeStatutEnum::ANNULEE)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function hasCommandeActiveForNumeroAgentEtProfil(string $numeroAgent, CommandeProfilEnum $profil): bool
    {
        $count = (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.numeroAgent = :numeroAgent')
            ->andWhere('c.profilCommande = :profil')
            ->andWhere('c.statut != :annulee')
            ->setParameter('numeroAgent', $numeroAgent)
            ->setParameter('profil', $profil)
            ->setParameter('annulee', CommandeStatutEnum::ANNULEE)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    public function countArticlesActifsForNumeroAgentEtProfil(string $numeroAgent, CommandeProfilEnum $profil): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(lc.id)')
            ->leftJoin('c.lignesCommande', 'lc')
            ->andWhere('c.numeroAgent = :numeroAgent')
            ->andWhere('c.profilCommande = :profil')
            ->andWhere('c.statut != :annulee')
            ->setParameter('numeroAgent', $numeroAgent)
            ->setParameter('profil', $profil)
            ->setParameter('annulee', CommandeStatutEnum::ANNULEE)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findDerniereCommandeActiveForNumeroAgentEtProfil(string $numeroAgent, CommandeProfilEnum $profil): ?Commande
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.numeroAgent = :numeroAgent')
            ->andWhere('c.profilCommande = :profil')
            ->andWhere('c.statut != :annulee')
            ->setParameter('numeroAgent', $numeroAgent)
            ->setParameter('profil', $profil)
            ->setParameter('annulee', CommandeStatutEnum::ANNULEE)
            ->orderBy('c.dateValidation', 'DESC')
            ->addOrderBy('c.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
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

    /** @return list<Commande> */
    public function findForVentesExport(): array
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.utilisateur', 'u')
            ->addSelect('u')
            ->leftJoin('c.creneau', 'creneau')
            ->addSelect('creneau')
            ->leftJoin('c.lignesCommande', 'ligneCommande')
            ->addSelect('ligneCommande')
            ->leftJoin('ligneCommande.produit', 'produit')
            ->addSelect('produit')
            ->andWhere('c.statut IN (:statuts)')
            ->setParameter('statuts', [
                CommandeStatutEnum::VALIDEE,
                CommandeStatutEnum::A_PREPARER,
                CommandeStatutEnum::EN_PREPARATION,
                CommandeStatutEnum::PRETE,
                CommandeStatutEnum::RETIREE,
            ])
            ->orderBy('c.dateValidation', 'ASC')
            ->addOrderBy('c.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
