<?php

declare(strict_types=1);

namespace App\Interface;

use App\Entity\Commande;
use App\Entity\Creneau;

interface SlotManagerInterface
{
    public function getDisponibles(\DateTimeInterface $date): array;

    public function reserverCreneau(Creneau $creneau, Commande $commande): void;

    public function getJaugeDisponible(Creneau $creneau): int;

    public function libererCreneau(Creneau $creneau, Commande $commande): void;
}
