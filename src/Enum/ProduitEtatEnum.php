<?php

declare(strict_types=1);

namespace App\Enum;

enum ProduitEtatEnum: string
{
    case TRES_BON_ETAT = 'tbe';
    case BON = 'bon';
    case ABIME = 'abime';

    public function label(): string
    {
        return match($this) {
            self::TRES_BON_ETAT => 'Très bon état',
            self::BON            => 'Bon état',
            self::ABIME          => 'Abîmé',
        };
    }
}
