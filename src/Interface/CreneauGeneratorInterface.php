<?php

declare(strict_types=1);

namespace App\Interface;

use App\DTO\GenerationResult;
use App\Entity\JourLivraison;

interface CreneauGeneratorInterface
{
    public function generate(JourLivraison $jour): GenerationResult;
}
