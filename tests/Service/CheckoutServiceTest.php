<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Commande;
use App\Entity\Creneau;
use App\Entity\LigneCommande;
use App\Entity\Produit;
use App\Entity\Utilisateur;
use App\Enum\CommandeProfilEnum;
use App\Enum\ProfilUtilisateur;
use App\Interface\CartManagerInterface;
use App\Interface\SlotManagerInterface;
use App\Service\CheckoutService;
use App\Service\QuotaCheckerService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Workflow\WorkflowInterface;

final class CheckoutServiceTest extends TestCase
{
    public function testConfirmCommandeCompleteParcours(): void
    {
        $commande = new Commande();
        $utilisateur = (new Utilisateur())->setLogin('agent@test.local')->setPassword('dummy')->setRoles(['ROLE_AGENT']);
        $cart = $this->createMock(CartManagerInterface::class);
        $cart->method('getContents')->willReturn([['quantite' => 1], ['quantite' => 1]]);
        $cart->method('validateCart')->willReturn($commande);

        $quota = $this->createMock(QuotaCheckerService::class);
        $quota->method('check')->willReturn(true);

        $slot = $this->createMock(SlotManagerInterface::class);
        $slot->expects(self::once())->method('reserverCreneau');
        $workflow = $this->createMock(WorkflowInterface::class);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('wrapInTransaction')->willReturnCallback(fn ($cb) => $cb());

        $result = (new CheckoutService($em, $cart, $quota, $slot, $workflow))
            ->confirmCommande('s', new Creneau(), ProfilUtilisateur::PUBLIC, $utilisateur, '12345');
        self::assertSame($commande, $result);
        self::assertSame('12345', $commande->getNumeroAgent());
        self::assertSame(CommandeProfilEnum::AGENT, $commande->getProfilCommande());
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
        $workflow = $this->createMock(WorkflowInterface::class);

        $utilisateur = (new Utilisateur())->setLogin('agent@test.local')->setPassword('dummy')->setRoles(['ROLE_AGENT']);
        (new CheckoutService($em, $cart, $quota, $this->createMock(SlotManagerInterface::class), $workflow))
            ->confirmCommande('s', new Creneau(), ProfilUtilisateur::PUBLIC, $utilisateur, '12345');
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
        $workflow = $this->createMock(WorkflowInterface::class);

        $utilisateur = (new Utilisateur())->setLogin('agent@test.local')->setPassword('dummy')->setRoles(['ROLE_AGENT']);
        (new CheckoutService($em, $cart, $quota, $slot, $workflow))
            ->confirmCommande('s', new Creneau(), ProfilUtilisateur::PUBLIC, $utilisateur, '12345');
    }

    public function testAnnulerCommandeRestitueStock(): void
    {
        $produit = (new Produit())->setQuantite(0);
        $ligne = (new LigneCommande())->setProduit($produit)->setQuantite(1);
        $commande = new Commande();
        $commande->getLignesCommande()->add($ligne);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('wrapInTransaction')->willReturnCallback(fn ($cb) => $cb());
        $em->expects(self::once())->method('flush');
        $workflow = $this->createMock(WorkflowInterface::class);
        $workflow->expects(self::once())->method('can')->with($commande, 'annuler_commande')->willReturn(true);
        $workflow->expects(self::once())->method('apply')->with($commande, 'annuler_commande');

        $service = new CheckoutService(
            $em,
            $this->createMock(CartManagerInterface::class),
            $this->createMock(QuotaCheckerService::class),
            $this->createMock(SlotManagerInterface::class),
            $workflow,
        );
        $service->annulerCommande($commande);

        self::assertSame(1, $produit->getQuantite());
    }

    public function testConfirmCommandePartenaireAssigneProfilCommandePartenaire(): void
    {
        $commande = new Commande();
        $utilisateur = (new Utilisateur())->setLogin('partenaire@test.local')->setPassword('dummy')->setRoles(['ROLE_PARTENAIRE']);
        $cart = $this->createMock(CartManagerInterface::class);
        $cart->method('getContents')->willReturn([['quantite' => 1]]);
        $cart->method('validateCart')->willReturn($commande);

        $quota = $this->createMock(QuotaCheckerService::class);
        $quota->method('check')->willReturn(true);

        $slot = $this->createMock(SlotManagerInterface::class);
        $slot->method('reserverCreneau');
        $workflow = $this->createMock(WorkflowInterface::class);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('wrapInTransaction')->willReturnCallback(fn ($cb) => $cb());

        (new CheckoutService($em, $cart, $quota, $slot, $workflow))
            ->confirmCommande('s', new Creneau(), ProfilUtilisateur::PARTENAIRE, $utilisateur, null);

        self::assertSame(CommandeProfilEnum::PARTENAIRE, $commande->getProfilCommande());
    }

    public function testConfirmCommandeDmaxAssigneProfilCommandeDmax(): void
    {
        $commande = new Commande();
        $utilisateur = (new Utilisateur())->setLogin('dmax@test.local')->setPassword('dummy')->setRoles(['ROLE_DMAX']);
        $cart = $this->createMock(CartManagerInterface::class);
        $cart->method('getContents')->willReturn([['quantite' => 1]]);
        $cart->method('validateCart')->willReturn($commande);

        $quota = $this->createMock(QuotaCheckerService::class);
        $quota->method('check')->willReturn(true);

        $slot = $this->createMock(SlotManagerInterface::class);
        $slot->method('reserverCreneau');
        $workflow = $this->createMock(WorkflowInterface::class);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('wrapInTransaction')->willReturnCallback(fn ($cb) => $cb());

        (new CheckoutService($em, $cart, $quota, $slot, $workflow))
            ->confirmCommande('s', new Creneau(), ProfilUtilisateur::DMAX, $utilisateur, null);

        self::assertSame(CommandeProfilEnum::DMAX, $commande->getProfilCommande());
    }
}
