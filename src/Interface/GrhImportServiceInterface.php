<?php

declare(strict_types=1);

namespace App\Interface;

interface GrhImportServiceInterface
{
    public function importFromCsv(string $csvPath): int;

    public function replaceAll(string $csvPath): int;
}
