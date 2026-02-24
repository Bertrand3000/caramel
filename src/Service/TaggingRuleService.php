<?php

declare(strict_types=1);

namespace App\Service;

use App\Interface\TaggingRuleServiceInterface;
use App\Repository\RegleTaggerRepository;

final class TaggingRuleService implements TaggingRuleServiceInterface
{
    public function __construct(
        private readonly RegleTaggerRepository $regleTaggerRepository,
    ) {
    }

    public function resolveTagForLibelle(string $libelle): ?bool
    {
        $regle = $this->regleTaggerRepository->findLatestMatchingRule($libelle);

        return $regle?->isTagTeletravailleur();
    }
}
