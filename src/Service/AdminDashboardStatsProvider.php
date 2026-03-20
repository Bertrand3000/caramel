<?php

declare(strict_types=1);

namespace App\Service;

use App\Interface\AdminDashboardStatsProviderInterface;
use App\Repository\CommandeRepository;

final class AdminDashboardStatsProvider implements AdminDashboardStatsProviderInterface
{
    public function __construct(private readonly CommandeRepository $commandeRepository)
    {
    }

    public function getStats(): array
    {
        $byJour = [];
        foreach ($this->commandeRepository->countCommandesAtLeastEnAttenteValidationByJourLivraison() as $row) {
            $byJour[] = [
                'date' => $row['jourDate']->format('d/m/Y'),
                'total' => $row['total'],
            ];
        }

        return [
            'totalProduitsCommandes' => $this->commandeRepository->countProduitsCommandesAtLeastEnAttenteValidation(),
            'totalCommandesEffectuees' => $this->commandeRepository->countCommandesAtLeastEnAttenteValidation(),
            'commandesEffectueesParJour' => $byJour,
        ];
    }
}
