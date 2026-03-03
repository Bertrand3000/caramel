<?php

declare(strict_types=1);

namespace App\Service;

use App\Interface\GrhXlsxReaderInterface;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

final class GrhXlsxReaderService implements GrhXlsxReaderInterface
{
    public function loadActiveSheet(string $filePath): Worksheet
    {
        return IOFactory::load($filePath)->getActiveSheet();
    }

    public function buildHeaderMap(Worksheet $sheet): array
    {
        $map = [];
        foreach ($sheet->toArray(null, true, true, true)[1] ?? [] as $column => $rawLabel) {
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

    public function extractNumeroAgent(Worksheet $sheet, ?string $column, int $row): ?string
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

    public function extractNullableValue(Worksheet $sheet, ?string $column, int $row): ?string
    {
        if ($column === null) {
            return null;
        }

        $value = trim((string) $sheet->getCell($column.$row)->getFormattedValue());

        return $value !== '' ? $value : null;
    }

    public function findFirstDataColumn(Worksheet $sheet): ?string
    {
        foreach ($sheet->toArray(null, true, true, true)[1] ?? [] as $column => $value) {
            if (trim((string) $value) !== '') {
                return (string) $column;
            }
        }

        return null;
    }

    private function normalizeHeader(string $value): string
    {
        $normalized = mb_strtolower(trim($value));
        $normalized = str_replace('.', '', $normalized);

        return (string) preg_replace('/\s+/', ' ', $normalized);
    }
}
