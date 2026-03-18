<?php

declare(strict_types=1);

namespace App\Interface;

use App\Entity\Commande;
use App\Entity\JourLivraison;

interface LogistiqueServiceInterface
{
    /** @return list<Commande> */
    public function findTodayReadyOrders(): array;

    public function validateRetrait(Commande $commande): void;

    public function findNextDeliveryDay(): ?JourLivraison;

    /** @return list<Commande> */
    public function findOrdersForPreparation(JourLivraison $jour): array;

    /** @return list<Commande> */
    public function findAllOrdersForLogistique(JourLivraison $jour): array;

    /** @return list<Commande> */
    public function findAgentOrdersForLogistique(JourLivraison $jour): array;

    /**
     * @param int|null    $filtreId        Filtre par numéro de commande
     * @param string|null $filtreNumeroAgent Filtre par numéro d'agent
     * @param int|null    $filtreCreneauId Filtre par créneau
     *
     * @return list<Commande>
     */
    public function findFilteredOrdersForLogistique(JourLivraison $jour, ?int $filtreId = null, ?string $filtreNumeroAgent = null, ?int $filtreCreneauId = null): array;

    /** @return list<\App\Entity\Creneau> */
    public function findCreneauxForLogistique(JourLivraison $jour): array;

    /**
     * Récupère le matériel à récupérer groupé par étage puis par porte.
     *
     * @return array<string, array<string, list<array{
     *     produit: \App\Entity\Produit,
     *     quantite: int,
     *     commandeId: int,
     *     agent: string
     * }>>>
     */
    public function findRecapMateriel(JourLivraison $jour): array;

    public function markEnPreparation(Commande $commande): void;

    public function markAsPrete(Commande $commande): void;

    public function markAsValidee(Commande $commande): void;

    public function markAsEnAttenteValidation(Commande $commande): void;

    public function markRevenirEnPreparation(Commande $commande): void;

    public function markRevenirPrete(Commande $commande): void;
}
