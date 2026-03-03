<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Interface\GrhXlsxReaderInterface;
use App\Repository\AgentEligibleRepository;
use App\Service\AgentEligibleImportService;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PHPUnit\Framework\TestCase;

final class AgentEligibleImportServiceTest extends TestCase
{
    public function testReplaceAllFromXlsxTraiteParLotsEtSupprimeLeFichier(): void
    {
        $path = $this->createTempFile('agents_eligibles_');
        $sheet = $this->createMock(Worksheet::class);
        $sheet->method('getHighestDataRow')->willReturn(502);

        $repository = $this->createMock(AgentEligibleRepository::class);
        $repository->expects(self::once())->method('deleteAll');

        $reader = $this->createMock(GrhXlsxReaderInterface::class);
        $reader->method('loadActiveSheet')->with($path)->willReturn($sheet);
        $reader->method('buildHeaderMap')->with($sheet)->willReturn(['numeroAgent' => 'A']);
        $reader->expects(self::never())->method('findFirstDataColumn');
        $reader->method('extractNumeroAgent')
            ->willReturnCallback(static fn (Worksheet $currentSheet, ?string $column, int $row): string => str_pad((string) $row, 5, '0', STR_PAD_LEFT));

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())
            ->method('wrapInTransaction')
            ->willReturnCallback(static fn (callable $callback): int => $callback());
        $entityManager->expects(self::exactly(501))->method('persist');
        $entityManager->expects(self::exactly(2))->method('flush');
        $entityManager->expects(self::once())->method('clear');

        $count = (new AgentEligibleImportService($repository, $entityManager, $reader))
            ->replaceAllFromXlsx($path);

        self::assertSame(501, $count);
        self::assertFileDoesNotExist($path);
    }

    public function testReplaceAllFromXlsxSupprimeLeFichierMemeEnErreur(): void
    {
        $this->expectException(\RuntimeException::class);

        $path = $this->createTempFile('agents_eligibles_fail_');
        $repository = $this->createMock(AgentEligibleRepository::class);
        $repository->expects(self::never())->method('deleteAll');

        $reader = $this->createMock(GrhXlsxReaderInterface::class);
        $reader->method('loadActiveSheet')->willThrowException(new \RuntimeException('Lecture impossible'));

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())
            ->method('wrapInTransaction')
            ->willReturnCallback(static fn (callable $callback): int => $callback());

        try {
            (new AgentEligibleImportService($repository, $entityManager, $reader))
                ->replaceAllFromXlsx($path);
        } finally {
            self::assertFileDoesNotExist($path);
        }
    }

    private function createTempFile(string $prefix): string
    {
        $path = tempnam(sys_get_temp_dir(), $prefix);
        self::assertNotFalse($path);
        file_put_contents($path, 'content');

        return $path;
    }
}
