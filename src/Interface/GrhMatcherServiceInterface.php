<?php

declare(strict_types=1);

namespace App\Interface;

use App\Entity\CommandeContactTmp;

interface GrhMatcherServiceInterface
{
    public function matchContact(CommandeContactTmp $contact): bool;

    /** @param array<int, array<string, mixed>> $csvData */
    public function processImport(array $csvData): int;
}
