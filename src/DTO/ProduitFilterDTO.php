<?php

declare(strict_types=1);

namespace App\DTO;

use App\Enum\ProduitEtatEnum;

readonly class ProduitFilterDTO
{
    public function __construct(
        public ?bool $tagTeletravailleur = null,
        public ?ProduitEtatEnum $etat = null,
    ) {
    }
}
