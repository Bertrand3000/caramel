<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Commande;
use App\Entity\Creneau;
use App\Entity\Utilisateur;
use App\Enum\CommandeStatutEnum;
use App\Enum\ProfilUtilisateur;
use App\Interface\CheckoutServiceInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class CheckoutControllerTest extends WebTestCase
{
    public function testCheckoutSansAuthentificationRedirigeVersLogin(): void
    {
        $client = static::createClient();
        $client->request('POST', '/commande/confirmer', []);

        self::assertResponseRedirects('/login');
    }

    public function testAnnulationRefuseeSiCommandeAppartientAUnAutreUtilisateur(): void
    {
        $client = static::createClient();
        $proprietaire = $this->createUser('owner');
        $commande = $this->createCommandeForUser($proprietaire);
        $attaquant = $this->createUser('attacker');
        $client->loginUser($attaquant);

        $token = $this->fetchCancelCsrfToken($client, $commande->getId() ?? 0);
        $client->request('POST', sprintf('/commande/annuler/%d', $commande->getId()), [
            '_token' => $token,
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testAnnulationRefuseeSiTokenCsrfInvalide(): void
    {
        $client = static::createClient();
        $user = $this->createUser('owner-csrf');
        $commande = $this->createCommandeForUser($user);
        $client->loginUser($user);

        $client->request('POST', sprintf('/commande/annuler/%d', $commande->getId()), [
            '_token' => 'token-invalide',
        ]);

        self::assertResponseRedirects('/commande/confirmation');

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();
        $reloaded = $entityManager->find(Commande::class, $commande->getId());
        self::assertInstanceOf(Commande::class, $reloaded);
        self::assertSame(CommandeStatutEnum::EN_ATTENTE_VALIDATION, $reloaded->getStatut());
    }

    public function testAnnulationAccepteeAvecTokenCsrfValide(): void
    {
        $client = static::createClient();
        $user = $this->createUser('owner-ok');
        $commande = $this->createCommandeForUser($user);
        $client->loginUser($user);

        $token = $this->fetchCancelCsrfToken($client, $commande->getId() ?? 0);
        $client->request('POST', sprintf('/commande/annuler/%d', $commande->getId()), [
            '_token' => $token,
        ]);

        self::assertResponseRedirects('/commande/confirmation');

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();
        $reloaded = $entityManager->find(Commande::class, $commande->getId());
        self::assertInstanceOf(Commande::class, $reloaded);
        self::assertSame(CommandeStatutEnum::ANNULEE, $reloaded->getStatut());
    }

    public function testDmaxPeutConfirmerSansNumeroAgent(): void
    {
        $client = static::createClient();
        $dmax = $this->createDmaxUser();
        $creneau = $this->createCreneau();
        $client->loginUser($dmax);
        $checkoutService = $this->createMock(CheckoutServiceInterface::class);
        $checkoutService->expects(self::once())
            ->method('confirmCommande')
            ->with(
                self::isType('string'),
                self::isInstanceOf(Creneau::class),
                ProfilUtilisateur::DMAX,
                self::callback(static fn (Utilisateur $user): bool => $user->getId() !== null),
                null,
            )
            ->willReturn((new Commande())->setUtilisateur($dmax)->setSessionId('s')->setDateValidation(new \DateTime()));
        static::getContainer()->set(CheckoutServiceInterface::class, $checkoutService);

        $client->request('POST', '/commande/confirmer', [
            'creneauId' => $creneau->getId(),
            'numeroAgent' => '',
        ]);

        self::assertResponseRedirects('/commande/confirmation');
    }

    private function createUser(string $prefix): Utilisateur
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $user = (new Utilisateur())
            ->setLogin(sprintf('%s-%s@test.local', $prefix, bin2hex(random_bytes(4))))
            ->setPassword('dummy')
            ->setRoles(['ROLE_AGENT']);
        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }

    private function createCommandeForUser(Utilisateur $user): Commande
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $commande = (new Commande())
            ->setUtilisateur($user)
            ->setSessionId(sprintf('sess-%s', bin2hex(random_bytes(4))))
            ->setDateValidation(new \DateTime())
            ->setStatut(CommandeStatutEnum::EN_ATTENTE_VALIDATION);
        $entityManager->persist($commande);
        $entityManager->flush();

        return $commande;
    }

    private function createCreneau(): Creneau
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $creneau = (new Creneau())
            ->setDateHeure(new \DateTimeImmutable('today 09:00:00'))
            ->setHeureDebut(new \DateTime('09:00:00'))
            ->setHeureFin(new \DateTime('09:30:00'));
        $entityManager->persist($creneau);
        $entityManager->flush();

        return $creneau;
    }

    private function createDmaxUser(): Utilisateur
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $user = (new Utilisateur())
            ->setLogin(sprintf('dmax-%s@test.local', bin2hex(random_bytes(4))))
            ->setPassword('dummy')
            ->setRoles(['ROLE_DMAX']);
        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }

    private function fetchCancelCsrfToken(KernelBrowser $client, int $commandeId): string
    {
        $client->request('GET', '/commande/confirmation');
        $session = $client->getRequest()->getSession();
        $session->set('checkout_last_commande_id', $commandeId);
        $session->save();
        $crawler = $client->request('GET', '/commande/confirmation');

        return (string) $crawler->filter('input[name="_token"]')->attr('value');
    }
}
