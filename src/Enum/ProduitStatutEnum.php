<?php

declare(strict_types=1);

namespace App\Enum;

enum ProduitStatutEnum: string
{
    case DISPONIBLE = 'disponible';
    case RESERVE_TEMPORAIRE = 'reserve_temporaire';
    case RESERVE = 'reserve';
    case REMIS = 'remis';
}
