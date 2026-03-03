<?php

declare(strict_types=1);

namespace App\Interface;

interface AgentEligibleImportServiceInterface
{
    public function replaceAllFromXlsx(string $filePath): int;
}
