<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\AgentEligible;
use App\Interface\AgentEligibleImportServiceInterface;
use App\Interface\GrhXlsxReaderInterface;
use App\Repository\AgentEligibleRepository;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

final class AgentEligibleImportService implements AgentEligibleImportServiceInterface
{
    private const BATCH_SIZE = 500;

    public function __construct(
        private readonly AgentEligibleRepository $repository,
        private readonly EntityManagerInterface $entityManager,
        private readonly GrhXlsxReaderInterface $xlsxReader,
    ) {
    }

    public function replaceAllFromXlsx(string $filePath): int
    {
        try {
            return $this->entityManager->wrapInTransaction(function () use ($filePath): int {
                $sheet = $this->xlsxReader->loadActiveSheet($filePath);
                $headerMap = $this->xlsxReader->buildHeaderMap($sheet);
                $column = $headerMap['numeroAgent'] ?? $this->xlsxReader->findFirstDataColumn($sheet);
                $startRow = isset($headerMap['numeroAgent']) ? 2 : 1;

                $this->repository->deleteAll();

                return $this->importRows($sheet, $column, $startRow);
            });
        } finally {
            $this->safeDelete($filePath);
        }
    }

    private function importRows(Worksheet $sheet, ?string $column, int $startRow): int
    {
        $count = 0;
        for ($row = $startRow; $row <= $sheet->getHighestDataRow(); ++$row) {
            $numeroAgent = $this->xlsxReader->extractNumeroAgent($sheet, $column, $row);
            if ($numeroAgent === null) {
                continue;
            }

            $this->entityManager->persist((new AgentEligible())->setNumeroAgent($numeroAgent));
            ++$count;

            if (0 === $count % self::BATCH_SIZE) {
                $this->entityManager->flush();
                $this->entityManager->clear();
            }
        }

        if (0 !== $count % self::BATCH_SIZE) {
            $this->entityManager->flush();
        }

        return $count;
    }

    private function safeDelete(string $filePath): void
    {
        if (is_file($filePath)) {
            @unlink($filePath);
        }
    }
}
