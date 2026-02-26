<?php

declare(strict_types=1);

namespace App\DTO;

readonly class CommandeGrhImportResult
{
    public function __construct(
        public int $matchedCount,
        public int $processedRows,
    ) {
    }
}
