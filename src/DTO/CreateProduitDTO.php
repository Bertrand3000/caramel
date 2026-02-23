<?php

declare(strict_types=1);

namespace App\DTO;

use App\Enum\ProduitEtatEnum;

readonly class CreateProduitDTO
{
    public function __construct(
        public string $libelle,
        public ProduitEtatEnum $etat,
        public string $etage,
        public string $porte,
        public bool $tagTeletravailleur,
        public ?float $largeur = null,
        public ?float $hauteur = null,
        public ?float $profondeur = null,
    ) {
    }
}
