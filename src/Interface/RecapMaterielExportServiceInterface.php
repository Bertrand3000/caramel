<?php

declare(strict_types=1);

namespace App\Interface;

use App\Entity\JourLivraison;

interface RecapMaterielExportServiceInterface
{
    /**
     * @param array<string, array<string, list<array{
     *     produit: \App\Entity\Produit,
     *     quantite: int,
     *     commandeId: int,
     *     agent: string
     * }>>> $recapMateriel
     */
    public function exportRecapMaterielXlsx(JourLivraison $jour, array $recapMateriel): string;
}
