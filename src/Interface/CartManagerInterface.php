<?php

declare(strict_types=1);

namespace App\Interface;

use App\Entity\Commande;
use App\Entity\Produit;

interface CartManagerInterface
{
    public function addItem(string $sessionId, Produit $produit, int $quantite): void;

    public function removeItem(string $sessionId, int $produitId): void;

    public function getContents(string $sessionId): array;

    public function validateCart(string $sessionId): Commande;

    public function releaseExpired(): int;

    public function clear(string $sessionId): void;
}
