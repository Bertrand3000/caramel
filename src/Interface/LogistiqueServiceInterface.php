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

    public function markEnPreparation(Commande $commande): void;

    public function markAsPrete(Commande $commande): void;

    public function markAsValidee(Commande $commande): void;

    public function markRevenirEnPreparation(Commande $commande): void;

    public function markRevenirPrete(Commande $commande): void;
}
