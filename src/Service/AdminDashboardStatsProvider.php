<?php

declare(strict_types=1);

namespace App\Service;

use App\Interface\AdminDashboardStatsProviderInterface;
use App\Repository\CommandeRepository;
use App\Repository\ProduitRepository;

final class AdminDashboardStatsProvider implements AdminDashboardStatsProviderInterface
{
    public function __construct(
        private readonly CommandeRepository $commandeRepository,
        private readonly ProduitRepository $produitRepository,
    ) {}

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
            'totalProduitsDisponibles' => $this->produitRepository->countProduitsDisponibles(),
            'totalProduitsCommandes' => $this->commandeRepository->countProduitsCommandesAtLeastEnAttenteValidation(),
            'totalCommandesEffectuees' => $this->commandeRepository->countCommandesAtLeastEnAttenteValidation(),
            'commandesEffectueesParJour' => $byJour,
        ];
    }
}
