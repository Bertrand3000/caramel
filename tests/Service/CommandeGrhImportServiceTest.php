<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Commande;
use App\Entity\Utilisateur;
use App\Enum\CommandeStatutEnum;
use App\Repository\CommandeRepository;
use App\Service\CommandeGrhImportService;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class CommandeGrhImportServiceTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private CommandeGrhImportService $service;
    private CommandeRepository $commandeRepository;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $this->service = static::getContainer()->get(CommandeGrhImportService::class);
        $this->commandeRepository = static::getContainer()->get(CommandeRepository::class);
        $this->cleanupTestData();
    }

    public function testImportSansColonneEmailNeLevePasErreurEtSupprimeLeFichier(): void
    {
        $numeroAgent = (string) random_int(70000, 79999);
        $commande = $this->createPendingCommande($numeroAgent);
        $xlsxPath = $this->createXlsx([
            ['N. agent', 'Prenom', 'Nom', 'Tel'],
            [$numeroAgent, 'Alice', 'Durand', '0601020304'],
        ]);

        $result = $this->service->importFromXlsx($xlsxPath);

        self::assertSame(1, $result->processedRows);
        self::assertSame(1, $result->matchedCount);
        self::assertFileDoesNotExist($xlsxPath);
        $this->entityManager->clear();
        $saved = $this->commandeRepository->find($commande->getId());
        self::assertNotNull($saved);
        self::assertNotNull($saved->getCommandeContactTmp());
        self::assertNull($saved->getCommandeContactTmp()?->getEmail());
        self::assertSame('0601020304', $saved->getCommandeContactTmp()?->getTelephone());
    }

    public function testSecondImportMetAJourLeContactSansDuplication(): void
    {
        $numeroAgent = (string) random_int(80000, 89999);
        $commande = $this->createPendingCommande($numeroAgent);
        $first = $this->createXlsx([
            ['N. agent', 'Prenom', 'Nom', 'Email', 'Tel'],
            [$numeroAgent, 'Bob', 'Martin', 'bob1@test.local', '0600000000'],
        ]);
        $this->service->importFromXlsx($first);

        $second = $this->createXlsx([
            ['N. agent', 'Prenom', 'Nom', 'Email', 'Tel'],
            [$numeroAgent, 'Robert', 'Martin', 'bob2@test.local', '0699999999'],
        ]);
        $this->service->importFromXlsx($second);

        self::assertFileDoesNotExist($second);
        $this->entityManager->clear();
        $saved = $this->commandeRepository->find($commande->getId());
        self::assertSame('Robert', $saved?->getCommandeContactTmp()?->getPrenomGrh());
        self::assertSame('bob2@test.local', $saved?->getCommandeContactTmp()?->getEmail());
        $count = (int) $this->entityManager->getConnection()->fetchOne(
            'SELECT COUNT(*) FROM commande_contacts_tmp WHERE commande_id = :commandeId',
            ['commandeId' => $commande->getId()],
        );
        self::assertSame(1, $count);
    }

    private function createPendingCommande(string $numeroAgent): Commande
    {
        $user = (new Utilisateur())
            ->setLogin(sprintf('grh-import-%s@test.local', bin2hex(random_bytes(4))))
            ->setPassword('dummy')
            ->setRoles(['ROLE_AGENT']);
        $commande = (new Commande())
            ->setUtilisateur($user)
            ->setSessionId(sprintf('grh-import-%s', bin2hex(random_bytes(4))))
            ->setNumeroAgent($numeroAgent)
            ->setNom('NomSaisi')
            ->setPrenom('PrenomSaisi')
            ->setStatut(CommandeStatutEnum::EN_ATTENTE_VALIDATION);
        $this->entityManager->persist($user);
        $this->entityManager->persist($commande);
        $this->entityManager->flush();

        return $commande;
    }

    private function createXlsx(array $rows): string
    {
        $tmpPath = tempnam(sys_get_temp_dir(), 'grh_xlsx_');
        self::assertNotFalse($tmpPath);
        $xlsxPath = $tmpPath.'.xlsx';
        rename($tmpPath, $xlsxPath);
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getActiveSheet()->fromArray($rows, null, 'A1');
        (new Xlsx($spreadsheet))->save($xlsxPath);
        $spreadsheet->disconnectWorksheets();

        return $xlsxPath;
    }

    private function cleanupTestData(): void
    {
        $connection = $this->entityManager->getConnection();
        $connection->executeStatement("DELETE FROM commande_contacts_tmp WHERE commande_id IN (SELECT id FROM commandes WHERE session_id LIKE 'grh-import-%')");
        $connection->executeStatement("DELETE FROM lignes_commande WHERE commande_id IN (SELECT id FROM commandes WHERE session_id LIKE 'grh-import-%')");
        $connection->executeStatement("DELETE FROM commandes WHERE session_id LIKE 'grh-import-%'");
        $connection->executeStatement("DELETE FROM utilisateurs WHERE login LIKE 'grh-import-%@test.local'");
    }
}
