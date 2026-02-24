<?php

declare(strict_types=1);

namespace App\Interface;

interface TaggingRuleServiceInterface
{
    public function resolveTagForLibelle(string $libelle): ?bool;
}
