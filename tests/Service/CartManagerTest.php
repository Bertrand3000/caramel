<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Produit;
use App\Entity\ReservationTemporaire;
use App\Entity\Utilisateur;
use App\Repository\ParametreRepository;
use App\Service\CartManager;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;

final class CartManagerTest extends TestCase
{
    private function buildQb(EntityManagerInterface $em, Query $query): QueryBuilder
    {
        $qb = $this->getMockBuilder(QueryBuilder::class)
            ->setConstructorArgs([$em])
            ->onlyMethods(['getQuery'])
            ->getMock();
        $qb->method('getQuery')->willReturn($query);

        return $qb;
    }

    public function testAddItemDecrementsAvailableStock(): void
    {
        $produit = (new Produit())->setQuantite(5);
        $reservationRepo = $this->createMock(EntityRepository::class);
        $reservationRepo->method('findOneBy')->willReturn(null);
        $panierRepo = $this->createMock(EntityRepository::class);
        $panierRepo->method('findOneBy')->willReturn(null);
        $ligneRepo = $this->createMock(EntityRepository::class);
        $ligneRepo->method('findOneBy')->willReturn(null);

        $query = $this->createMock(Query::class);
        $query->method('getSingleScalarResult')->willReturn(0);
        $qb = $this->buildQb($em = $this->createMock(EntityManagerInterface::class), $query);

        $em->method('getRepository')->willReturnMap([
            [ReservationTemporaire::class, $reservationRepo],
            ['App\\Entity\\Panier', $panierRepo],
            ['App\\Entity\\LignePanier', $ligneRepo],
        ]);
        $em->method('createQueryBuilder')->willReturn($qb);
        $em->expects(self::once())->method('flush');

        $service = new CartManager($em, $this->createMock(ParametreRepository::class));
        $service->addItem('sess', $produit, 2);

        self::assertSame(5, $produit->getQuantite());
    }

    public function testAddItemThrowsWhenStockInsuffisant(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Stock insuffisant');

        $produit = (new Produit())->setQuantite(1);
        $em = $this->createMock(EntityManagerInterface::class);
        $query = $this->createMock(Query::class);
        $query->method('getSingleScalarResult')->willReturn(0);
        $qb = $this->buildQb($em, $query);
        $em->method('createQueryBuilder')->willReturn($qb);

        (new CartManager($em, $this->createMock(ParametreRepository::class)))->addItem('s', $produit, 2);
    }

    public function testValidateCartUsesLockPessimisticWrite(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $conn = $this->createMock(Connection::class);
        $conn->method('isTransactionActive')->willReturn(false);
        $em->method('getConnection')->willReturn($conn);
        $em->expects(self::once())->method('wrapInTransaction')->willReturnCallback(fn ($cb) => $cb());
        $repo = $this->createMock(EntityRepository::class);
        $repo->method('findBy')->willReturn([]);
        $em->method('getRepository')->willReturn($repo);

        $service = new CartManager($em, $this->createMock(ParametreRepository::class));
        $service->validateCart('sess', (new Utilisateur())->setLogin('agent@test.local')->setPassword('dummy')->setRoles(['ROLE_AGENT']));
    }

    public function testAddItemWithExistingReservationThrowsWhenNewTotalExceedsAvailableStock(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Stock insuffisant');

        $produit = (new Produit())->setQuantite(3);
        $reservationExistante = (new ReservationTemporaire())
            ->setSessionId('sess')
            ->setProduit($produit)
            ->setQuantite(2)
            ->setExpireAt(new \DateTimeImmutable('+30 minutes'));

        $reservationRepo = $this->createMock(EntityRepository::class);
        $reservationRepo->method('findOneBy')->willReturn($reservationExistante);
        $panierRepo = $this->createMock(EntityRepository::class);
        $ligneRepo = $this->createMock(EntityRepository::class);

        $query = $this->createMock(Query::class);
        $query->method('getSingleScalarResult')->willReturn(0);
        $qb = $this->buildQb($em = $this->createMock(EntityManagerInterface::class), $query);

        $em->method('getRepository')->willReturnMap([
            [ReservationTemporaire::class, $reservationRepo],
            ['App\\Entity\\Panier', $panierRepo],
            ['App\\Entity\\LignePanier', $ligneRepo],
        ]);
        $em->method('createQueryBuilder')->willReturn($qb);

        $service = new CartManager($em, $this->createMock(ParametreRepository::class));
        $service->addItem('sess', $produit, 2);
    }

    public function testReleaseExpiredDeletesOldReservations(): void
    {
        $query = $this->createMock(Query::class);
        $query->method('setParameter')->willReturnSelf();
        $query->expects(self::once())->method('execute')->willReturn(4);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('createQuery')->willReturn($query);

        $service = new CartManager($em, $this->createMock(ParametreRepository::class));
        self::assertSame(4, $service->releaseExpired());
    }
}
