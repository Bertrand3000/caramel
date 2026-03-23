<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Commande;
use App\Entity\JourLivraison;
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

    /** @return list<Commande> */
    public function findValideesSansCreneau(): array
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.utilisateur', 'u')
            ->addSelect('u')
            ->leftJoin('c.commandeContactTmp', 'contact')
            ->addSelect('contact')
            ->leftJoin('c.lignesCommande', 'lignes')
            ->addSelect('lignes')
            ->leftJoin('lignes.produit', 'produit')
            ->addSelect('produit')
            ->andWhere('c.statut = :statut')
            ->andWhere('c.creneau IS NULL')
            ->setParameter('statut', CommandeStatutEnum::VALIDEE)
            ->orderBy('c.dateValidation', 'DESC')
            ->addOrderBy('c.id', 'DESC')
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

    /** @return list<Commande> */
    public function findByStatutWithEmail(CommandeStatutEnum $statut): array
    {
        return $this->createQueryBuilder('c')
            ->innerJoin('c.commandeContactTmp', 'contact')
            ->addSelect('contact')
            ->andWhere('c.statut = :statut')
            ->andWhere('contact.email IS NOT NULL')
            ->andWhere("TRIM(contact.email) != ''")
            ->setParameter('statut', $statut)
            ->orderBy('c.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return list<Commande> */
    public function findByJourLivraisonWithEmail(JourLivraison $jour, ?CommandeStatutEnum $statut = null): array
    {
        $qb = $this->createQueryBuilder('c')
            ->innerJoin('c.commandeContactTmp', 'contact')
            ->addSelect('contact')
            ->innerJoin('c.creneau', 'cr')
            ->innerJoin('cr.jourLivraison', 'j')
            ->andWhere('j.id = :jourId')
            ->andWhere('contact.email IS NOT NULL')
            ->andWhere("TRIM(contact.email) != ''")
            ->setParameter('jourId', $jour->getId())
            ->orderBy('c.id', 'ASC');

        if ($statut !== null) {
            $qb->andWhere('c.statut = :statut')
                ->setParameter('statut', $statut);
        } else {
            $qb->andWhere('c.statut != :annulee')
                ->setParameter('annulee', CommandeStatutEnum::ANNULEE);
        }

        return $qb->getQuery()->getResult();
    }

    /** @return list<Commande> */
    public function findForGrhImportByNumeroAgent(string $numeroAgent): array
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.commandeContactTmp', 'contact')
            ->addSelect('contact')
            ->andWhere('c.numeroAgent = :numeroAgent')
            ->andWhere('c.statut IN (:statuts)')
            ->setParameter('numeroAgent', $numeroAgent)
            ->setParameter('statuts', [
                CommandeStatutEnum::EN_ATTENTE_VALIDATION,
                CommandeStatutEnum::VALIDEE,
            ])
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array{with: int, without: int}
     */
    public function countWithAndWithoutEmailByStatut(CommandeStatutEnum $statut): array
    {
        $total = (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.statut = :statut')
            ->setParameter('statut', $statut)
            ->getQuery()
            ->getSingleScalarResult();

        $with = (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->innerJoin('c.commandeContactTmp', 'contact')
            ->andWhere('c.statut = :statut')
            ->andWhere('contact.email IS NOT NULL')
            ->andWhere("TRIM(contact.email) != ''")
            ->setParameter('statut', $statut)
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'with' => $with,
            'without' => max(0, $total - $with),
        ];
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

    public function countCommandesActivesForNumeroAgentEtProfil(string $numeroAgent, CommandeProfilEnum $profil): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.numeroAgent = :numeroAgent')
            ->andWhere('c.profilCommande = :profil')
            ->andWhere('c.statut != :annulee')
            ->setParameter('numeroAgent', $numeroAgent)
            ->setParameter('profil', $profil)
            ->setParameter('annulee', CommandeStatutEnum::ANNULEE)
            ->getQuery()
            ->getSingleScalarResult();
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

    public function findDerniereCommandeActiveAvecCreneauPourNumeroAgentEtProfilLeJour(
        string $numeroAgent,
        CommandeProfilEnum $profil,
        \DateTimeImmutable $date,
    ): ?Commande {
        $start = $date->setTime(0, 0);
        $end = $start->modify('+1 day');

        return $this->createQueryBuilder('c')
            ->innerJoin('c.creneau', 'cr')
            ->addSelect('cr')
            ->andWhere('c.numeroAgent = :numeroAgent')
            ->andWhere('c.profilCommande = :profil')
            ->andWhere('c.statut != :annulee')
            ->andWhere('cr.dateHeure >= :start')
            ->andWhere('cr.dateHeure < :end')
            ->setParameter('numeroAgent', $numeroAgent)
            ->setParameter('profil', $profil)
            ->setParameter('annulee', CommandeStatutEnum::ANNULEE)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
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
    public function findForPreparation(JourLivraison $jour): array
    {
        return $this->createQueryBuilder('c')
            ->innerJoin('c.creneau', 'cr')
            ->addSelect('cr')
            ->innerJoin('cr.jourLivraison', 'j')
            ->innerJoin('c.lignesCommande', 'lc')
            ->addSelect('lc')
            ->innerJoin('lc.produit', 'p')
            ->addSelect('p')
            ->andWhere('j.id = :jourId')
            ->andWhere('c.statut IN (:statuts)')
            ->setParameter('jourId', $jour->getId())
            ->setParameter('statuts', [
                CommandeStatutEnum::VALIDEE,
                CommandeStatutEnum::EN_PREPARATION,
            ])
            ->orderBy('p.etage', 'ASC')
            ->addOrderBy('p.porte', 'ASC')
            ->addOrderBy('cr.heureDebut', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère les lignes de commandes pour le récapitulatif matériel.
     * Retourne un tableau plat de lignes avec leurs relations préchargées.
     *
     * @return list<array{
     *     ligne: \App\Entity\LigneCommande,
     *     produit: \App\Entity\Produit,
     *     commande: \App\Entity\Commande
     * }>
     */
    public function findLignesForRecapMateriel(JourLivraison $jour): array
    {
        $rows = $this->createQueryBuilder('c')
            ->innerJoin('c.creneau', 'cr')
            ->addSelect('cr')
            ->innerJoin('cr.jourLivraison', 'j')
            ->innerJoin('c.lignesCommande', 'lc')
            ->addSelect('lc')
            ->innerJoin('lc.produit', 'p')
            ->addSelect('p')
            ->andWhere('j.id = :jourId')
            ->andWhere('c.statut IN (:statuts)')
            ->setParameter('jourId', $jour->getId())
            ->setParameter('statuts', [
                CommandeStatutEnum::VALIDEE,
                CommandeStatutEnum::EN_PREPARATION,
            ])
            ->orderBy('p.etage', 'ASC')
            ->addOrderBy('p.porte', 'ASC')
            ->addOrderBy('p.libelle', 'ASC')
            ->getQuery()
            ->getResult();

        // Transforme en tableau plat de lignes pour faciliter le groupement
        $result = [];
        foreach ($rows as $commande) {
            foreach ($commande->getLignesCommande() as $ligne) {
                $result[] = [
                    'ligne' => $ligne,
                    'produit' => $ligne->getProduit(),
                    'commande' => $commande,
                ];
            }
        }

        return $result;
    }

    /** @return list<Commande> */
    public function findAllForLogistique(JourLivraison $jour): array
    {
        return $this->createQueryBuilder('c')
            ->innerJoin('c.creneau', 'cr')
            ->addSelect('cr')
            ->innerJoin('cr.jourLivraison', 'j')
            ->innerJoin('c.lignesCommande', 'lc')
            ->addSelect('lc')
            ->innerJoin('lc.produit', 'p')
            ->addSelect('p')
            ->andWhere('j.id = :jourId')
            ->andWhere('c.statut IN (:statuts)')
            ->setParameter('jourId', $jour->getId())
            ->setParameter('statuts', [
                CommandeStatutEnum::VALIDEE,
                CommandeStatutEnum::EN_PREPARATION,
                CommandeStatutEnum::PRETE,
                CommandeStatutEnum::RETIREE,
            ])
            ->orderBy('cr.heureDebut', 'ASC')
            ->addOrderBy('c.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return list<Commande> */
    public function findAgentsForLogistique(JourLivraison $jour): array
    {
        return $this->createQueryBuilder('c')
            ->innerJoin('c.creneau', 'cr')
            ->addSelect('cr')
            ->innerJoin('cr.jourLivraison', 'j')
            ->andWhere('j.id = :jourId')
            ->andWhere('c.statut IN (:statuts)')
            ->andWhere('c.numeroAgent IS NOT NULL')
            ->andWhere("TRIM(c.numeroAgent) != ''")
            ->setParameter('jourId', $jour->getId())
            ->setParameter('statuts', [
                CommandeStatutEnum::VALIDEE,
                CommandeStatutEnum::EN_PREPARATION,
                CommandeStatutEnum::PRETE,
                CommandeStatutEnum::RETIREE,
            ])
            ->orderBy('cr.heureDebut', 'ASC')
            ->addOrderBy('c.nom', 'ASC')
            ->addOrderBy('c.prenom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @param int|null   $filtreId       Filtre par numéro de commande
     * @param string|null $filtreNumeroAgent Filtre par numéro d'agent
     * @param int|null   $filtreCreneauId Filtre par créneau
     *
     * @return list<Commande>
     */
    public function findFilteredForLogistique(JourLivraison $jour, ?int $filtreId = null, ?string $filtreNumeroAgent = null, ?int $filtreCreneauId = null): array
    {
        $qb = $this->createQueryBuilder('c')
            ->innerJoin('c.creneau', 'cr')
            ->addSelect('cr')
            ->innerJoin('cr.jourLivraison', 'j')
            ->innerJoin('c.lignesCommande', 'lc')
            ->addSelect('lc')
            ->innerJoin('lc.produit', 'p')
            ->addSelect('p')
            ->andWhere('j.id = :jourId')
            ->andWhere('c.statut IN (:statuts)')
            ->setParameter('jourId', $jour->getId())
            ->setParameter('statuts', [
                CommandeStatutEnum::VALIDEE,
                CommandeStatutEnum::EN_PREPARATION,
                CommandeStatutEnum::PRETE,
                CommandeStatutEnum::RETIREE,
            ]);

        if ($filtreId !== null && $filtreId > 0) {
            $qb->andWhere('c.id = :filtreId')
                ->setParameter('filtreId', $filtreId);
        }

        if ($filtreNumeroAgent !== null && trim($filtreNumeroAgent) !== '') {
            $qb->andWhere('c.numeroAgent = :filtreNumeroAgent')
                ->setParameter('filtreNumeroAgent', trim($filtreNumeroAgent));
        }

        if ($filtreCreneauId !== null && $filtreCreneauId > 0) {
            $qb->andWhere('cr.id = :filtreCreneauId')
                ->setParameter('filtreCreneauId', $filtreCreneauId);
        }

        return $qb->orderBy('cr.heureDebut', 'ASC')
            ->addOrderBy('c.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return list<Creneau> */
    public function findCreneauxForJourLivraison(JourLivraison $jour): array
    {
        return $this->getEntityManager()
            ->getRepository(\App\Entity\Creneau::class)
            ->createQueryBuilder('cr')
            ->innerJoin('cr.jourLivraison', 'j')
            ->andWhere('j.id = :jourId')
            ->setParameter('jourId', $jour->getId())
            ->orderBy('cr.heureDebut', 'ASC')
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
                CommandeStatutEnum::EN_PREPARATION,
                CommandeStatutEnum::PRETE,
                CommandeStatutEnum::RETIREE,
            ])
            ->orderBy('c.dateValidation', 'ASC')
            ->addOrderBy('c.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array<string, int>
     */
    public function countByStatut(): array
    {
        $results = $this->createQueryBuilder('c')
            ->select('c.statut as statut, COUNT(c.id) as count')
            ->groupBy('c.statut')
            ->getQuery()
            ->getResult();

        $counts = [];
        foreach ($results as $result) {
            /** @var CommandeStatutEnum $statut */
            $statut = $result['statut'];
            $counts[$statut->value] = (int) $result['count'];
        }

        return $counts;
    }

    public function countProduitsCommandesAtLeastEnAttenteValidation(): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COALESCE(SUM(lc.quantite), 0)')
            ->innerJoin('c.lignesCommande', 'lc')
            ->andWhere('c.statut IN (:statuts)')
            ->setParameter('statuts', $this->getStatutsAtLeastEnAttenteValidation())
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countCommandesAtLeastEnAttenteValidation(): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.statut IN (:statuts)')
            ->setParameter('statuts', $this->getStatutsAtLeastEnAttenteValidation())
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return list<array{jourDate: \DateTimeImmutable, total: int}>
     */
    public function countCommandesAtLeastEnAttenteValidationByJourLivraison(): array
    {
        $rows = $this->createQueryBuilder('c')
            ->select('j.date AS jourDate, COUNT(c.id) AS total')
            ->innerJoin('c.creneau', 'cr')
            ->innerJoin('cr.jourLivraison', 'j')
            ->andWhere('c.statut IN (:statuts)')
            ->setParameter('statuts', $this->getStatutsAtLeastEnAttenteValidation())
            ->groupBy('j.date')
            ->orderBy('j.date', 'ASC')
            ->getQuery()
            ->getResult();

        $result = [];
        foreach ($rows as $row) {
            $date = $row['jourDate'];
            if (!$date instanceof \DateTimeImmutable) {
                $date = new \DateTimeImmutable((string) $date);
            }
            $result[] = [
                'jourDate' => $date,
                'total' => (int) $row['total'],
            ];
        }

        return $result;
    }

    /**
     * @return list<CommandeStatutEnum>
     */
    private function getStatutsAtLeastEnAttenteValidation(): array
    {
        return [
            CommandeStatutEnum::EN_ATTENTE_VALIDATION,
            CommandeStatutEnum::VALIDEE,
            CommandeStatutEnum::EN_PREPARATION,
            CommandeStatutEnum::PRETE,
            CommandeStatutEnum::RETIREE,
        ];
    }
}
