<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\CommandeGrhImportResult;
use App\Entity\Commande;
use App\Entity\CommandeContactTmp;
use App\Interface\CommandeGrhImportServiceInterface;
use App\Interface\GrhXlsxReaderInterface;
use App\Repository\CommandeRepository;
use Doctrine\ORM\EntityManagerInterface;

class CommandeGrhImportService implements CommandeGrhImportServiceInterface
{
    public function __construct(
        private readonly CommandeRepository $commandeRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly GrhXlsxReaderInterface $xlsxReader,
    ) {
    }

    public function importFromXlsx(string $filePath): CommandeGrhImportResult
    {
        try {
            $sheet = $this->xlsxReader->loadActiveSheet($filePath);
            $headerMap = $this->xlsxReader->buildHeaderMap($sheet);
            $processedRows = $matchedCount = 0;
            $batchId = uniqid('grh_', true);
            $now = new \DateTimeImmutable();

            $this->entityManager->beginTransaction();
            try {
                for ($row = 2; $row <= $sheet->getHighestDataRow(); ++$row) {
                    $numeroAgent = $this->xlsxReader->extractNumeroAgent($sheet, $headerMap['numeroAgent'] ?? null, $row);
                    if ($numeroAgent === null) {
                        continue;
                    }

                    ++$processedRows;
                    $matches = $this->commandeRepository->findEnAttenteValidationByNumeroAgent($numeroAgent);
                    if ($matches === []) {
                        continue;
                    }

                    $nom = $this->xlsxReader->extractNullableValue($sheet, $headerMap['nom'] ?? null, $row);
                    $prenom = $this->xlsxReader->extractNullableValue($sheet, $headerMap['prenom'] ?? null, $row);
                    $email = $this->xlsxReader->extractNullableValue($sheet, $headerMap['email'] ?? null, $row);
                    $telephone = $this->xlsxReader->extractNullableValue($sheet, $headerMap['telephone'] ?? null, $row);
                    foreach ($matches as $commande) {
                        $this->upsertContact($commande, $nom, $prenom, $email, $telephone, $batchId, $now);
                        ++$matchedCount;
                    }
                }

                $this->entityManager->flush();
                $this->entityManager->commit();
            } catch (\Throwable $exception) {
                $this->entityManager->rollback();

                throw $exception;
            }

            return new CommandeGrhImportResult($matchedCount, $processedRows);
        } finally {
            $this->safeDelete($filePath);
        }
    }

    private function upsertContact(
        Commande $commande,
        ?string $nom,
        ?string $prenom,
        ?string $email,
        ?string $telephone,
        string $batchId,
        \DateTimeImmutable $importedAt,
    ): void {
        $contact = $commande->getCommandeContactTmp() ?? (new CommandeContactTmp())->setCommande($commande);
        $commande->setCommandeContactTmp($contact);
        $contact->setNomGrh($nom)
            ->setPrenomGrh($prenom)
            ->setEmail($email)
            ->setTelephone($telephone)
            ->setImportBatchId($batchId)
            ->setImportedAt($importedAt);
        $this->entityManager->persist($contact);
    }

    private function safeDelete(string $filePath): void
    {
        if (is_file($filePath)) {
            @unlink($filePath);
        }
    }
}
