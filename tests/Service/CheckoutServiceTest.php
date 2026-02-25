<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Commande;
use App\Entity\Creneau;
use App\Entity\JourLivraison;
use App\Entity\LigneCommande;
use App\Entity\Produit;
use App\Entity\Utilisateur;
use App\Enum\CommandeProfilEnum;
use App\Enum\ProfilUtilisateur;
use App\Exception\JourLivraisonNonPleinException;
use App\Interface\CartManagerInterface;
use App\Interface\SlotManagerInterface;
use App\Repository\JourLivraisonRepository;
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
            ->confirmCommande('s', new Creneau(), ProfilUtilisateur::PUBLIC, $utilisateur, '12345', 'Durand', 'Alice');
        self::assertSame($commande, $result);
        self::assertSame('12345', $commande->getNumeroAgent());
        self::assertSame('Durand', $commande->getNom());
        self::assertSame('Alice', $commande->getPrenom());
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

    public function testConfirmCommandeEchouePanierVide(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Panier vide');

        $cart = $this->createMock(CartManagerInterface::class);
        $cart->method('getContents')->willReturn([]);

        $quota = $this->createMock(QuotaCheckerService::class);
        $quota->expects(self::never())->method('check');

        $slot = $this->createMock(SlotManagerInterface::class);
        $slot->expects(self::never())->method('reserverCreneau');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('wrapInTransaction')->willReturnCallback(fn ($cb) => $cb());
        $workflow = $this->createMock(WorkflowInterface::class);

        $utilisateur = (new Utilisateur())->setLogin('agent@test.local')->setPassword('dummy')->setRoles(['ROLE_AGENT']);
        (new CheckoutService($em, $cart, $quota, $slot, $workflow))
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

    public function testConfirmCommandeBloqueeSiJourPrecedentNonPlein(): void
    {
        $this->expectException(JourLivraisonNonPleinException::class);

        $jour = (new JourLivraison())
            ->setDate(new \DateTimeImmutable('2030-03-28'));
        $creneau = (new Creneau())->setJourLivraison($jour);
        $blockingDay = (new JourLivraison())->setDate(new \DateTimeImmutable('2030-03-21'));

        $cart = $this->createMock(CartManagerInterface::class);
        $cart->method('getContents')->willReturn([['quantite' => 1]]);
        $cart->expects(self::never())->method('validateCart');

        $quota = $this->createMock(QuotaCheckerService::class);
        $quota->method('check')->willReturn(true);

        $slot = $this->createMock(SlotManagerInterface::class);
        $slot->expects(self::never())->method('reserverCreneau');

        $repository = $this->createMock(JourLivraisonRepository::class);
        $repository->method('findPremierJourNonPleinAvant')->with($jour->getDate())->willReturn($blockingDay);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('wrapInTransaction')->willReturnCallback(fn ($cb) => $cb());
        $workflow = $this->createMock(WorkflowInterface::class);
        $utilisateur = (new Utilisateur())->setLogin('agent@test.local')->setPassword('dummy')->setRoles(['ROLE_AGENT']);

        (new CheckoutService($em, $cart, $quota, $slot, $workflow, $repository))
            ->confirmCommande('s', $creneau, ProfilUtilisateur::PUBLIC, $utilisateur, '12345');
    }

    public function testConfirmCommandeAutoriseeSiAucunJourPrecedentNonPlein(): void
    {
        $commande = new Commande();
        $jour = (new JourLivraison())
            ->setDate(new \DateTimeImmutable('2030-03-28'));
        $creneau = (new Creneau())->setJourLivraison($jour);

        $cart = $this->createMock(CartManagerInterface::class);
        $cart->method('getContents')->willReturn([['quantite' => 1]]);
        $cart->method('validateCart')->willReturn($commande);

        $quota = $this->createMock(QuotaCheckerService::class);
        $quota->method('check')->willReturn(true);

        $slot = $this->createMock(SlotManagerInterface::class);
        $slot->expects(self::once())->method('reserverCreneau');

        $repository = $this->createMock(JourLivraisonRepository::class);
        $repository->method('findPremierJourNonPleinAvant')->with($jour->getDate())->willReturn(null);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('wrapInTransaction')->willReturnCallback(fn ($cb) => $cb());
        $workflow = $this->createMock(WorkflowInterface::class);
        $utilisateur = (new Utilisateur())->setLogin('agent@test.local')->setPassword('dummy')->setRoles(['ROLE_AGENT']);

        $result = (new CheckoutService($em, $cart, $quota, $slot, $workflow, $repository))
            ->confirmCommande('s', $creneau, ProfilUtilisateur::PUBLIC, $utilisateur, '12345');

        self::assertSame($commande, $result);
    }

    public function testConfirmCommandeSansJourLivraisonIgnoreRegle(): void
    {
        $commande = new Commande();

        $cart = $this->createMock(CartManagerInterface::class);
        $cart->method('getContents')->willReturn([['quantite' => 1]]);
        $cart->method('validateCart')->willReturn($commande);

        $quota = $this->createMock(QuotaCheckerService::class);
        $quota->method('check')->willReturn(true);

        $slot = $this->createMock(SlotManagerInterface::class);
        $slot->expects(self::once())->method('reserverCreneau');

        $repository = $this->createMock(JourLivraisonRepository::class);
        $repository->expects(self::never())->method('findPremierJourNonPleinAvant');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('wrapInTransaction')->willReturnCallback(fn ($cb) => $cb());
        $workflow = $this->createMock(WorkflowInterface::class);
        $utilisateur = (new Utilisateur())->setLogin('agent@test.local')->setPassword('dummy')->setRoles(['ROLE_AGENT']);

        $result = (new CheckoutService($em, $cart, $quota, $slot, $workflow, $repository))
            ->confirmCommande('s', new Creneau(), ProfilUtilisateur::PUBLIC, $utilisateur, '12345');

        self::assertSame($commande, $result);
    }
}
