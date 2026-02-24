<?php

declare(strict_types=1);

namespace App\Interface;

interface ExportServiceInterface
{
    public function exportVentesCsv(): string;

    public function exportStockRestantCsv(): string;

    public function exportComptabiliteCsv(): string;
}
