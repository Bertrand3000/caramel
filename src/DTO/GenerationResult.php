<?php

declare(strict_types=1);

namespace App\DTO;

final class GenerationResult
{
    /** @param list<string> $avertissements */
    public function __construct(
        public readonly int $crees,
        public readonly int $supprimes,
        public readonly int $verrouilles,
        public readonly array $avertissements,
    ) {
    }
}
