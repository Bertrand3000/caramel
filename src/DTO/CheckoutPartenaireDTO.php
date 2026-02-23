<?php

declare(strict_types=1);

namespace App\DTO;

readonly class CheckoutPartenaireDTO
{
    public function __construct(
        public int $utilisateurId,
    ) {
    }
}
