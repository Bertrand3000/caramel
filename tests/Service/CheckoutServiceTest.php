<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Commande;
use App\Entity\Creneau;
use App\Entity\LigneCommande;
use App\Entity\Produit;
use App\Enum\ProfilUtilisateur;
use App\Interface\CartManagerInterface;
use App\Interface\SlotManagerInterface;
use App\Service\CheckoutService;
use App\Service\QuotaCheckerService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class CheckoutServiceTest extends TestCase
{
    public function testConfirmCommandeCompleteParcours(): void
    {
        $commande = new Commande();
        $cart = $this->createMock(CartManagerInterface::class);
        $cart->method('getContents')->willReturn([['quantite' => 2]]);
        $cart->method('validateCart')->willReturn($commande);

        $quota = $this->createMock(QuotaCheckerService::class);
        $quota->method('check')->willReturn(true);

        $slot = $this->createMock(SlotManagerInterface::class);
        $slot->expects(self::once())->method('reserverCreneau');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('wrapInTransaction')->willReturnCallback(fn ($cb) => $cb());

        $result = (new CheckoutService($em, $cart, $quota, $slot))
            ->confirmCommande('s', new Creneau(), ProfilUtilisateur::PUBLIC, '12345');
        self::assertSame($commande, $result);
        self::assertSame('12345', $commande->getNumeroAgent());
    }

    public function testConfirmCommandeEchoueQuotaDepasse(): void
    {
        $this->expectException(\RuntimeException::class);

        $cart = $this->createMock(CartManagerInterface::class);
        $cart->method('getContents')->willReturn([['quantite' => 1]]);
        $quota = $this->createMock(QuotaCheckerService::class);
        $quota->method('check')->willReturn(false);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('wrapInTransaction')->willReturnCallback(fn ($cb) => $cb());

        (new CheckoutService($em, $cart, $quota, $this->createMock(SlotManagerInterface::class)))
            ->confirmCommande('s', new Creneau(), ProfilUtilisateur::PUBLIC, '12345');
    }

    public function testConfirmCommandeEchoueCreneauPlein(): void
    {
        $this->expectException(\RuntimeException::class);

        $cart = $this->createMock(CartManagerInterface::class);
        $cart->method('getContents')->willReturn([['quantite' => 1]]);
        $cart->method('validateCart')->willReturn(new Commande());
        $quota = $this->createMock(QuotaCheckerService::class);
        $quota->method('check')->willReturn(true);
        $slot = $this->createMock(SlotManagerInterface::class);
        $slot->method('reserverCreneau')->willThrowException(new \RuntimeException('Créneau complet'));
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('wrapInTransaction')->willReturnCallback(fn ($cb) => $cb());

        (new CheckoutService($em, $cart, $quota, $slot))
            ->confirmCommande('s', new Creneau(), ProfilUtilisateur::PUBLIC, '12345');
    }

    public function testAnnulerCommandeRestitueStock(): void
    {
        $produit = (new Produit())->setQuantite(1);
        $ligne = (new LigneCommande())->setProduit($produit)->setQuantite(2);
        $commande = new Commande();
        $commande->getLignesCommande()->add($ligne);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('wrapInTransaction')->willReturnCallback(fn ($cb) => $cb());
        $em->expects(self::once())->method('flush');

        $service = new CheckoutService($em, $this->createMock(CartManagerInterface::class), $this->createMock(QuotaCheckerService::class), $this->createMock(SlotManagerInterface::class));
        $service->annulerCommande($commande);

        self::assertSame(3, $produit->getQuantite());
    }
}
