<?php

declare(strict_types=1);

namespace App\DTO;

readonly class CartAddItemDTO
{
    public function __construct(
        public int $produitId,
        public int $utilisateurId,
    ) {
    }
}
