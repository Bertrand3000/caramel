<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Commande;
use App\Entity\LigneCommande;
use App\Entity\LignePanier;
use App\Entity\Panier;
use App\Entity\Produit;
use App\Entity\ReservationTemporaire;
use App\Entity\Utilisateur;
use App\Interface\CartManagerInterface;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

class CartManager implements CartManagerInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly QuotaCheckerService $quotaChecker,
    ) {
    }

    public function addItem(string $sessionId, Produit $produit, array $roles): void
    {
        $cartItemsCount = $this->countActiveCartItems($sessionId);
        if (!$this->quotaChecker->canAddMoreItems($roles, $cartItemsCount)) {
            $quota = $this->quotaChecker->getCartQuotaForRoles($roles);
            if ($quota !== null) {
                throw new \RuntimeException(sprintf('Vous ne pouvez pas commander plus de %d articles.', $quota));
            }

            throw new \RuntimeException('Quota atteint pour votre panier.');
        }

        /** @var ReservationTemporaire|null $reservation */
        $reservation = $this->em->getRepository(ReservationTemporaire::class)->findOneBy([
            'sessionId' => $sessionId,
            'produit' => $produit,
        ]);
        if ($reservation !== null) {
            throw new \RuntimeException('Produit déjà dans votre panier');
        }

        if ($this->getAvailableStock($produit, $sessionId) < 1) {
            throw new \RuntimeException('Nous sommes désolés, mais quelqu\'un d\'autre vient de réserver cet article.');
        }

        $reservation = (new ReservationTemporaire())
            ->setSessionId($sessionId)
            ->setProduit($produit)
            ->setQuantite(1)
            ->setExpireAt(new \DateTimeImmutable('+30 minutes'));

        $panier = $this->em->getRepository(Panier::class)->findOneBy(['sessionId' => $sessionId]) ?? (new Panier())->setSessionId($sessionId);
        $ligne = $this->em->getRepository(LignePanier::class)->findOneBy(['panier' => $panier, 'produit' => $produit]) ?? new LignePanier();
        $ligne->setPanier($panier)->setProduit($produit)->setQuantite(1);

        $this->em->persist($panier);
        $this->em->persist($reservation);
        $this->em->persist($ligne);
        $this->em->flush();
    }

    public function removeItem(string $sessionId, int $produitId): void
    {
        $this->em->createQuery('DELETE FROM App\\Entity\\ReservationTemporaire r WHERE r.sessionId = :sessionId AND r.produit = :produitId')
            ->setParameter('sessionId', $sessionId)
            ->setParameter('produitId', $produitId)
            ->execute();

        $this->em->createQuery('DELETE FROM App\\Entity\\LignePanier lp WHERE lp.produit = :produitId AND lp.panier IN (SELECT p.id FROM App\\Entity\\Panier p WHERE p.sessionId = :sessionId)')
            ->setParameter('sessionId', $sessionId)
            ->setParameter('produitId', $produitId)
            ->execute();
    }

    public function getContents(string $sessionId): array
    {
        $rows = $this->em->createQueryBuilder()
            ->from(ReservationTemporaire::class, 'r')
            ->select('r, p')
            ->join('r.produit', 'p')
            ->andWhere('r.sessionId = :sessionId')
            ->andWhere('r.expireAt > :now')
            ->setParameter('sessionId', $sessionId)
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getResult();

        return array_map(static fn (ReservationTemporaire $r): array => [
            'produit' => $r->getProduit(),
            'quantite' => $r->getQuantite(),
            'expireAt' => $r->getExpireAt(),
        ], $rows);
    }

    public function validateCart(string $sessionId, Utilisateur $utilisateur): Commande
    {
        $action = fn (): Commande => $this->validateCartInternal($sessionId, $utilisateur);

        if ($this->em->getConnection()->isTransactionActive()) {
            return $action();
        }

        return $this->em->wrapInTransaction($action);
    }

    public function releaseExpired(): int
    {
        return $this->em->createQuery('DELETE FROM App\\Entity\\ReservationTemporaire r WHERE r.expireAt < :now')
            ->setParameter('now', new \DateTimeImmutable())
            ->execute();
    }

    public function clear(string $sessionId): void
    {
        $this->em->createQuery('DELETE FROM App\\Entity\\ReservationTemporaire r WHERE r.sessionId = :sessionId')
            ->setParameter('sessionId', $sessionId)
            ->execute();

        $this->em->createQuery('DELETE FROM App\\Entity\\LignePanier lp WHERE lp.panier IN (SELECT p.id FROM App\\Entity\\Panier p WHERE p.sessionId = :sessionId)')
            ->setParameter('sessionId', $sessionId)
            ->execute();
    }

    public function getAvailableStockForDisplay(Produit $produit): int
    {
        return $this->getAvailableStock($produit, null);
    }

    private function validateCartInternal(string $sessionId, Utilisateur $utilisateur): Commande
    {
        $reservations = $this->em->getRepository(ReservationTemporaire::class)->findBy(['sessionId' => $sessionId]);
        $commande = (new Commande())
            ->setSessionId($sessionId)
            ->setUtilisateur($utilisateur);
        $this->em->persist($commande);

        foreach ($reservations as $reservation) {
            $produit = $reservation->getProduit();
            $this->em->lock($produit, LockMode::PESSIMISTIC_WRITE);
            if ($this->getAvailableStock($produit, $sessionId) < 1) {
                throw new \RuntimeException('Stock épuisé lors de la validation');
            }
            $produit->setQuantite(0);
            $this->em->persist((new LigneCommande())->setCommande($commande)->setProduit($produit)->setQuantite(1));
            $this->em->remove($reservation);
        }

        $this->em->flush();

        return $commande;
    }

    private function getAvailableStock(Produit $produit, ?string $excludeSessionId): int
    {
        $qb = $this->em->createQueryBuilder()
            ->from(ReservationTemporaire::class, 'r')
            ->select('COALESCE(SUM(r.quantite), 0)')
            ->andWhere('r.produit = :produit')
            ->andWhere('r.expireAt > :now')
            ->setParameter('produit', $produit)
            ->setParameter('now', new \DateTimeImmutable());

        if ($excludeSessionId !== null) {
            $qb->andWhere('r.sessionId != :sessionId')->setParameter('sessionId', $excludeSessionId);
        }

        $stock = $produit->getQuantite() > 0 ? 1 : 0;
        $activeReservations = (int) $qb->getQuery()->getSingleScalarResult();
        $reserved = $activeReservations > 0 ? 1 : 0;

        return max(0, $stock - $reserved);
    }

    private function countActiveCartItems(string $sessionId): int
    {
        return (int) $this->em->createQueryBuilder()
            ->from(ReservationTemporaire::class, 'r')
            ->select('COUNT(r.id)')
            ->andWhere('r.sessionId = :sessionId')
            ->andWhere('r.expireAt > :now')
            ->setParameter('sessionId', $sessionId)
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getSingleScalarResult();
    }
}
