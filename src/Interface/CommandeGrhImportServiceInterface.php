<?php

declare(strict_types=1);

namespace App\Interface;

use App\DTO\CommandeGrhImportResult;

interface CommandeGrhImportServiceInterface
{
    public function importFromXlsx(string $filePath): CommandeGrhImportResult;
}
