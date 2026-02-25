<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Commande;
use App\Entity\Creneau;
use App\Enum\CommandeStatutEnum;
use App\Interface\SlotManagerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\CacheItemPoolInterface;

class CreneauManager implements SlotManagerInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CacheItemPoolInterface $cache,
    ) {
    }

    public function getDisponibles(\DateTimeInterface $date): array
    {
        $slots = $this->em->createQueryBuilder()
            ->from(Creneau::class, 'c')
            ->select('c')
            ->andWhere('c.dateHeure >= :start AND c.dateHeure < :end')
            ->setParameter('start', (new \DateTimeImmutable($date->format('Y-m-d')))->setTime(0, 0))
            ->setParameter('end', (new \DateTimeImmutable($date->format('Y-m-d')))->modify('+1 day')->setTime(0, 0))
            ->orderBy('c.dateHeure', 'ASC')
            ->getQuery()
            ->getResult();

        return array_values(array_filter($slots, fn (Creneau $creneau): bool => $this->getJaugeDisponible($creneau) > 0));
    }

    public function getDisponiblesPourCheckout(\DateTimeInterface $fromDate): array
    {
        $slots = $this->findDisponiblesFromDate($fromDate);
        if ($slots === []) {
            return [];
        }

        $firstDate = $slots[0]->getDateHeure()->format('Y-m-d');
        $firstDaySlots = array_values(array_filter(
            $slots,
            static fn (Creneau $creneau): bool => $creneau->getDateHeure()->format('Y-m-d') === $firstDate,
        ));

        $isStrictDay = $slots[0]->getJourLivraison()?->isExigerJourneePleine() ?? false;
        if ($isStrictDay) {
            return $firstDaySlots;
        }

        return $slots;
    }

    public function reserverCreneau(Creneau $creneau, Commande $commande): void
    {
        if ($this->countActiveForSlot($creneau) >= $creneau->getCapaciteMax()) {
            throw new \RuntimeException('Créneau complet');
        }

        $commande->setCreneau($creneau);
        $creneau->setCapaciteUtilisee(min($creneau->getCapaciteMax(), $creneau->getCapaciteUtilisee() + 1));
        $this->cache->deleteItem($this->cacheKey($creneau));
    }

    public function getJaugeDisponible(Creneau $creneau): int
    {
        $key = $this->cacheKey($creneau);
        $item = $this->cache->getItem($key);
        if (!$item->isHit()) {
            $item->set($this->countActiveForSlot($creneau));
            $item->expiresAfter(30);
            $this->cache->save($item);
        }

        return $creneau->getCapaciteMax() - (int) $item->get();
    }

    public function libererCreneau(Creneau $creneau, Commande $commande): void
    {
        $commande->setCreneau(null);
        $creneau->setCapaciteUtilisee(max(0, $creneau->getCapaciteUtilisee() - 1));
        $this->cache->deleteItem($this->cacheKey($creneau));
    }

    private function countActiveForSlot(Creneau $creneau): int
    {
        return (int) $this->em->createQueryBuilder()
            ->from(Commande::class, 'c')
            ->select('COUNT(c.id)')
            ->andWhere('c.creneau = :creneau')
            ->andWhere('c.statut != :annulee')
            ->setParameter('creneau', $creneau)
            ->setParameter('annulee', CommandeStatutEnum::ANNULEE)
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function cacheKey(Creneau $creneau): string
    {
        return 'creneau_jauge_' . (string) $creneau->getId();
    }

    /** @return list<Creneau> */
    private function findDisponiblesFromDate(\DateTimeInterface $fromDate): array
    {
        $start = (new \DateTimeImmutable($fromDate->format('Y-m-d')))->setTime(0, 0);
        $slots = $this->em->createQueryBuilder()
            ->from(Creneau::class, 'c')
            ->select('c', 'j')
            ->leftJoin('c.jourLivraison', 'j')
            ->andWhere('c.dateHeure >= :start')
            ->andWhere('j.id IS NULL OR j.actif = :actif')
            ->setParameter('start', $start)
            ->setParameter('actif', true)
            ->orderBy('c.dateHeure', 'ASC')
            ->addOrderBy('c.heureDebut', 'ASC')
            ->getQuery()
            ->getResult();

        return array_values(array_filter($slots, fn (Creneau $creneau): bool => $this->getJaugeDisponible($creneau) > 0));
    }
}
