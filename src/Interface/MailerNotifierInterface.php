<?php

declare(strict_types=1);

namespace App\Interface;

use App\Entity\Commande;

interface MailerNotifierInterface
{
    public function sendValidationEmail(Commande $commande): void;

    public function sendRefusalOrCancellationEmail(Commande $commande): void;
}
