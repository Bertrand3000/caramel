<?php

declare(strict_types=1);

namespace App\Interface;

use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

interface GrhXlsxReaderInterface
{
    public function loadActiveSheet(string $filePath): Worksheet;

    /**
     * @return array<string, string>
     */
    public function buildHeaderMap(Worksheet $sheet): array;

    public function extractNumeroAgent(Worksheet $sheet, ?string $column, int $row): ?string;

    public function extractNullableValue(Worksheet $sheet, ?string $column, int $row): ?string;

    public function findFirstDataColumn(Worksheet $sheet): ?string;
}
