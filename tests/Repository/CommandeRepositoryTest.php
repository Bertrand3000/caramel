<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\Commande;
use App\Entity\LigneCommande;
use App\Entity\Produit;
use App\Entity\Utilisateur;
use App\Enum\CommandeProfilEnum;
use App\Enum\CommandeStatutEnum;
use App\Enum\ProduitEtatEnum;
use App\Repository\CommandeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class CommandeRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private CommandeRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $this->repository = static::getContainer()->get(CommandeRepository::class);
        $this->cleanupTestData();
    }

    public function testHasCommandeActiveForNumeroAgentEtProfilRetourneFalseSiSeuleCommandeAnnulee(): void
    {
        $this->createCommandeAvecLignes('11111', CommandeProfilEnum::AGENT, CommandeStatutEnum::ANNULEE, 1);
        $this->entityManager->flush();

        self::assertFalse(
            $this->repository->hasCommandeActiveForNumeroAgentEtProfil('11111', CommandeProfilEnum::AGENT),
        );
    }

    public function testHasCommandeActiveForNumeroAgentEtProfilRetourneTrueSiCommandeRetiree(): void
    {
        $this->createCommandeAvecLignes('22222', CommandeProfilEnum::AGENT, CommandeStatutEnum::RETIREE, 1);
        $this->entityManager->flush();

        self::assertTrue(
            $this->repository->hasCommandeActiveForNumeroAgentEtProfil('22222', CommandeProfilEnum::AGENT),
        );
    }

    public function testCountArticlesActifsForNumeroAgentEtProfilIgnoreAutreProfil(): void
    {
        $this->createCommandeAvecLignes('33333', CommandeProfilEnum::AGENT, CommandeStatutEnum::PRETE, 2);
        $this->createCommandeAvecLignes('33333', CommandeProfilEnum::TELETRAVAILLEUR, CommandeStatutEnum::PRETE, 3);
        $this->entityManager->flush();

        self::assertSame(
            2,
            $this->repository->countArticlesActifsForNumeroAgentEtProfil('33333', CommandeProfilEnum::AGENT),
        );
        self::assertSame(
            3,
            $this->repository->countArticlesActifsForNumeroAgentEtProfil('33333', CommandeProfilEnum::TELETRAVAILLEUR),
        );
    }

    public function testFindDerniereCommandeActiveForNumeroAgentEtProfilIgnoreAnnulees(): void
    {
        $this->createCommandeAvecLignes('44444', CommandeProfilEnum::AGENT, CommandeStatutEnum::ANNULEE, 1);
        $this->createCommandeAvecLignes('44444', CommandeProfilEnum::AGENT, CommandeStatutEnum::PRETE, 1);
        $this->entityManager->flush();

        $result = $this->repository->findDerniereCommandeActiveForNumeroAgentEtProfil('44444', CommandeProfilEnum::AGENT);

        self::assertInstanceOf(Commande::class, $result);
        self::assertSame(CommandeStatutEnum::PRETE, $result->getStatut());
    }

    private function createCommandeAvecLignes(
        string $numeroAgent,
        CommandeProfilEnum $profilCommande,
        CommandeStatutEnum $statut,
        int $nbLignes,
    ): void {
        $user = (new Utilisateur())
            ->setLogin(sprintf('repo-cmd-%s@test.local', bin2hex(random_bytes(4))))
            ->setPassword('dummy')
            ->setRoles(['ROLE_AGENT']);
        $this->entityManager->persist($user);

        $commande = (new Commande())
            ->setUtilisateur($user)
            ->setSessionId(sprintf('repo-cmd-%s', bin2hex(random_bytes(4))))
            ->setNumeroAgent($numeroAgent)
            ->setProfilCommande($profilCommande)
            ->setStatut($statut);
        $this->entityManager->persist($commande);

        for ($index = 0; $index < $nbLignes; ++$index) {
            $produit = (new Produit())
                ->setLibelle(sprintf('repo-cmd-%s-%d', $numeroAgent, $index))
                ->setPhotoProduit('repo-test.jpg')
                ->setEtat(ProduitEtatEnum::BON)
                ->setEtage('1')
                ->setPorte('A')
                ->setLargeur(60.0)
                ->setHauteur(70.0)
                ->setProfondeur(50.0)
                ->setQuantite(1);
            $this->entityManager->persist($produit);

            $ligne = (new LigneCommande())
                ->setCommande($commande)
                ->setProduit($produit)
                ->setQuantite(1);
            $this->entityManager->persist($ligne);
        }
    }

    private function cleanupTestData(): void
    {
        $connection = $this->entityManager->getConnection();
        $connection->executeStatement("DELETE FROM lignes_commande WHERE commande_id IN (SELECT id FROM commandes WHERE session_id LIKE 'repo-cmd-%')");
        $connection->executeStatement("DELETE FROM commandes WHERE session_id LIKE 'repo-cmd-%'");
        $connection->executeStatement("DELETE FROM produits WHERE libelle LIKE 'repo-cmd-%'");
        $connection->executeStatement("DELETE FROM utilisateurs WHERE login LIKE 'repo-cmd-%@test.local'");
    }
}
