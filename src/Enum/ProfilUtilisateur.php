<?php

declare(strict_types=1);

namespace App\Enum;

enum ProfilUtilisateur: string
{
    case DMAX = 'dmax';
    case TELETRAVAILLEUR = 'teletravailleur';
    case PARTENAIRE = 'partenaire';
    case PUBLIC = 'public';
}
