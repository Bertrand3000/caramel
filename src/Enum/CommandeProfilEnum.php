<?php

declare(strict_types=1);

namespace App\Enum;

enum CommandeProfilEnum: string
{
    case AGENT = 'agent';
    case TELETRAVAILLEUR = 'teletravailleur';
    case PARTENAIRE = 'partenaire';
}
