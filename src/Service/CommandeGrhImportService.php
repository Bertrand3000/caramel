<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\CommandeGrhImportResult;
use App\Entity\Commande;
use App\Entity\CommandeContactTmp;
use App\Interface\CommandeGrhImportServiceInterface;
use App\Repository\CommandeRepository;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\IOFactory;

class CommandeGrhImportService implements CommandeGrhImportServiceInterface
{
    public function __construct(
        private readonly CommandeRepository $commandeRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function importFromXlsx(string $filePath): CommandeGrhImportResult
    {
        $sheet = IOFactory::load($filePath)->getActiveSheet();
        $headerMap = $this->buildHeaderMap($sheet->toArray(null, true, true, true)[1] ?? []);
        $processedRows = $matchedCount = 0;
        $batchId = uniqid('grh_', true);
        $now = new \DateTimeImmutable();

        $this->entityManager->beginTransaction();
        try {
            for ($row = 2; $row <= $sheet->getHighestDataRow(); ++$row) {
                $numeroAgent = $this->extractNumeroAgent($sheet, $headerMap['numeroAgent'] ?? null, $row);
                if ($numeroAgent === null) {
                    continue;
                }

                ++$processedRows;
                $matches = $this->commandeRepository->findEnAttenteValidationByNumeroAgent($numeroAgent);
                if ($matches === []) {
                    continue;
                }

                $nom = $this->extractNullableValue($sheet, $headerMap['nom'] ?? null, $row);
                $prenom = $this->extractNullableValue($sheet, $headerMap['prenom'] ?? null, $row);
                $email = $this->extractNullableValue($sheet, $headerMap['email'] ?? null, $row);
                $telephone = $this->extractNullableValue($sheet, $headerMap['telephone'] ?? null, $row);
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

        $this->safeDelete($filePath);

        return new CommandeGrhImportResult($matchedCount, $processedRows);
    }

    private function buildHeaderMap(array $headers): array
    {
        $map = [];
        foreach ($headers as $column => $rawLabel) {
            $label = $this->normalizeHeader((string) $rawLabel);
            if ($label === 'n agent') {
                $map['numeroAgent'] = (string) $column;
            } elseif ($label === 'prenom') {
                $map['prenom'] = (string) $column;
            } elseif ($label === 'nom') {
                $map['nom'] = (string) $column;
            } elseif ($label === 'email') {
                $map['email'] = (string) $column;
            } elseif (in_array($label, ['tel', 'telephone'], true)) {
                $map['telephone'] = (string) $column;
            }
        }

        return $map;
    }

    private function normalizeHeader(string $value): string
    {
        $normalized = mb_strtolower(trim($value));
        $normalized = str_replace('.', '', $normalized);
        return (string) preg_replace('/\s+/', ' ', $normalized);
    }

    private function extractNumeroAgent($sheet, ?string $column, int $row): ?string
    {
        if ($column === null) {
            return null;
        }

        $raw = preg_replace('/\D+/', '', (string) $sheet->getCell($column.$row)->getFormattedValue());
        if (!is_string($raw) || $raw === '') {
            return null;
        }

        return str_pad($raw, 5, '0', STR_PAD_LEFT);
    }

    private function extractNullableValue($sheet, ?string $column, int $row): ?string
    {
        if ($column === null) {
            return null;
        }

        $value = trim((string) $sheet->getCell($column.$row)->getFormattedValue());

        return $value !== '' ? $value : null;
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
