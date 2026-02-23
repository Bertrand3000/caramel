<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Repository\TeletravailleurListeRepository;
use App\Service\GrhImportService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class GrhImportServiceTest extends TestCase
{
    public function testImportFromCsvInsertsCorrectCount(): void
    {
        $repo = $this->createMock(TeletravailleurListeRepository::class);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::exactly(3))->method('persist');
        $em->expects(self::once())->method('flush');

        $service = new GrhImportService($repo, $em);
        $csv = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($csv, "12345\n54321\n99999\n");

        self::assertSame(3, $service->importFromCsv($csv));
    }

    public function testImportIgnoresInvalidNumeroAgent(): void
    {
        $repo = $this->createMock(TeletravailleurListeRepository::class);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('persist');
        $em->expects(self::once())->method('flush');

        $service = new GrhImportService($repo, $em);
        $csv = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($csv, "12345\nabcde\n1234\n");

        self::assertSame(1, $service->importFromCsv($csv));
    }
}
