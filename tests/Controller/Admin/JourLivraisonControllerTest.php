<?php

declare(strict_types=1);

namespace App\Tests\Controller\Admin;

use App\Entity\Commande;
use App\Entity\Creneau;
use App\Entity\JourLivraison;
use App\Entity\Utilisateur;
use App\Enum\ProfilUtilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class JourLivraisonControllerTest extends WebTestCase
{
    public function testEditAfficheBoutonSuppression(): void
    {
        $client = static::createClient();
        $admin = $this->createAdminUser();
        $jour = $this->createJourLivraison();
        $client->loginUser($admin);

        $crawler = $client->request('GET', sprintf('/admin/jours-livraison/%d/edit', $jour->getId()));

        self::assertResponseIsSuccessful();
        self::assertGreaterThan(
            0,
            $crawler->filter(sprintf('form[action="/admin/jours-livraison/%d/delete"] button', $jour->getId()))->count(),
        );
    }

    public function testSuppressionJourneeSupprimeCreneauxAssocies(): void
    {
        $client = static::createClient();
        $admin = $this->createAdminUser();
        [$jour, $creneau, $commande] = $this->createJourWithCreneauAndCommande();
        $client->loginUser($admin);

        $crawler = $client->request('GET', sprintf('/admin/jours-livraison/%d/edit', $jour->getId()));
        $token = (string) $crawler
            ->filter(sprintf('form[action="/admin/jours-livraison/%d/delete"] input[name="_token"]', $jour->getId()))
            ->attr('value');

        $client->request('POST', sprintf('/admin/jours-livraison/%d/delete', $jour->getId()), [
            '_token' => $token,
        ]);

        self::assertResponseRedirects('/admin/jours-livraison');

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();
        self::assertNull($entityManager->find(JourLivraison::class, $jour->getId()));
        self::assertNull($entityManager->find(Creneau::class, $creneau->getId()));
        $savedCommande = $entityManager->find(Commande::class, $commande->getId());
        self::assertInstanceOf(Commande::class, $savedCommande);
        self::assertNull($savedCommande->getCreneau());
    }

    private function createAdminUser(): Utilisateur
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $user = (new Utilisateur())
            ->setLogin(sprintf('admin-jour-%s@test.local', bin2hex(random_bytes(4))))
            ->setPassword('dummy')
            ->setRoles(['ROLE_ADMIN'])
            ->setProfil(ProfilUtilisateur::DMAX);

        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }

    private function createJourLivraison(): JourLivraison
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $jour = (new JourLivraison())
            ->setDate(new \DateTimeImmutable('2030-03-28'))
            ->setActif(true)
            ->setHeureOuverture(new \DateTime('08:00'))
            ->setHeureFermeture(new \DateTime('12:00'));

        $entityManager->persist($jour);
        $entityManager->flush();

        return $jour;
    }

    /** @return array{0:JourLivraison,1:Creneau,2:Commande} */
    private function createJourWithCreneauAndCommande(): array
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $jour = $this->createJourLivraison();
        $creneau = (new Creneau())
            ->setJourLivraison($jour)
            ->setDateHeure(new \DateTimeImmutable('2030-03-28 08:00:00'))
            ->setHeureDebut(new \DateTime('08:00:00'))
            ->setHeureFin(new \DateTime('08:30:00'))
            ->setCapaciteMax(1)
            ->setCapaciteUtilisee(1);
        $beneficiary = (new Utilisateur())
            ->setLogin(sprintf('agent-jour-%s@test.local', bin2hex(random_bytes(4))))
            ->setPassword('dummy')
            ->setRoles(['ROLE_AGENT']);
        $commande = (new Commande())
            ->setUtilisateur($beneficiary)
            ->setCreneau($creneau)
            ->setDateValidation(new \DateTime())
            ->setSessionId(sprintf('session-%s', bin2hex(random_bytes(4))));

        $entityManager->persist($creneau);
        $entityManager->persist($beneficiary);
        $entityManager->persist($commande);
        $entityManager->flush();

        return [$jour, $creneau, $commande];
    }
}
