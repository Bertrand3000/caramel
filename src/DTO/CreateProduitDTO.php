<?php

declare(strict_types=1);

namespace App\DTO;

use App\Enum\ProduitEtatEnum;

readonly class CreateProduitDTO
{
    public function __construct(
        public string $libelle,
        public ProduitEtatEnum $etat,
        public ?float $largeur,
        public ?float $hauteur,
        public ?float $profondeur,
        public string $etage,
        public string $porte,
        public bool $tagTeletravailleur,
    ) {
    }
}
