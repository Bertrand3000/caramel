<?php

declare(strict_types=1);

namespace App\DTO;

readonly class CheckoutAgentDTO
{
    public function __construct(
        public string $numeroAgent,
        public string $nom,
        public string $prenom,
        public int $creneauId,
    ) {
    }
}
