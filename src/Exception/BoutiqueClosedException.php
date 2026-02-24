<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class BoutiqueClosedException extends AccessDeniedHttpException
{
    public function __construct()
    {
        parent::__construct('La boutique n\'est pas encore ouverte pour votre profil.');
    }
}
