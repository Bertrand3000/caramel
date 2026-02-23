<?php

declare(strict_types=1);

namespace App\Enum;

enum CommandeStatutEnum: string
{
    case EN_ATTENTE_VALIDATION = 'en_attente_validation';
    case VALIDEE = 'validee';
    case REFUSEE = 'refusee';
    case A_PREPARER = 'a_preparer';
    case EN_PREPARATION = 'en_preparation';
    case PRETE = 'prete';
    case RETIREE = 'retiree';
    case ANNULEE = 'annulee';
}
