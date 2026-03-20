<?php

declare(strict_types=1);

namespace App\Interface;

interface AdminDashboardStatsProviderInterface
{
    /**
     * @return array{
     *     totalProduitsCommandes: int,
     *     totalCommandesEffectuees: int,
     *     commandesEffectueesParJour: list<array{date: string, total: int}>
     * }
     */
    public function getStats(): array;
}
