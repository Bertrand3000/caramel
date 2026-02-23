<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Commande;
use App\Entity\Creneau;
use App\Service\CreneauManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

final class CreneauManagerTest extends TestCase
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

    public function testGetJaugeDisponibleRetourneDiff(): void
    {
        $creneau = (new Creneau())->setCapaciteMax(10);
        $item = $this->createMock(CacheItemInterface::class);
        $item->method('isHit')->willReturn(false);
        $item->method('set')->willReturnSelf();
        $item->method('expiresAfter')->willReturnSelf();
        $item->method('get')->willReturn(4);

        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cache->method('getItem')->willReturn($item);

        $query = $this->createMock(Query::class);
        $query->method('getSingleScalarResult')->willReturn(4);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('createQueryBuilder')->willReturn($this->buildQb($em, $query));

        self::assertSame(6, (new CreneauManager($em, $cache))->getJaugeDisponible($creneau));
    }

    public function testReserverCreneauPleinsLanceException(): void
    {
        $this->expectException(\RuntimeException::class);

        $creneau = (new Creneau())->setCapaciteMax(1);
        $cache = $this->createMock(CacheItemPoolInterface::class);
        $query = $this->createMock(Query::class);
        $query->method('getSingleScalarResult')->willReturn(1);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('createQueryBuilder')->willReturn($this->buildQb($em, $query));

        (new CreneauManager($em, $cache))->reserverCreneau($creneau, new Commande());
    }

    public function testGetDisponiblesFiltreDateEtJauge(): void
    {
        $d = new \DateTimeImmutable('2026-03-21 10:00:00');
        $c1 = (new Creneau())->setDateHeure($d)->setCapaciteMax(1);
        $c2 = (new Creneau())->setDateHeure($d->modify('+1 hour'))->setCapaciteMax(1);

        $queryList = $this->createMock(Query::class);
        $queryList->method('getResult')->willReturn([$c1, $c2]);
        $queryCount = $this->createMock(Query::class);
        $queryCount->method('getSingleScalarResult')->willReturnOnConsecutiveCalls(0, 1);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('createQueryBuilder')->willReturnOnConsecutiveCalls($this->buildQb($em, $queryList), $this->buildQb($em, $queryCount), $this->buildQb($em, $queryCount));

        $item = $this->createMock(CacheItemInterface::class);
        $item->method('isHit')->willReturn(false);
        $item->method('set')->willReturnSelf();
        $item->method('expiresAfter')->willReturnSelf();
        $item->method('get')->willReturnOnConsecutiveCalls(0, 1);
        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cache->method('getItem')->willReturn($item);

        self::assertCount(1, (new CreneauManager($em, $cache))->getDisponibles($d));
    }
}
