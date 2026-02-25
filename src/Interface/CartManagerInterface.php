<?php

declare(strict_types=1);

namespace App\Interface;

use App\Entity\Commande;
use App\Entity\Produit;
use App\Entity\Utilisateur;

interface CartManagerInterface
{
    /**
     * @param list<string> $roles
     */
    public function addItem(string $sessionId, Produit $produit, array $roles): void;

    public function removeItem(string $sessionId, int $produitId): void;

    public function getContents(string $sessionId): array;

    public function validateCart(string $sessionId, Utilisateur $utilisateur): Commande;

    public function releaseExpired(): int;

    public function clear(string $sessionId): void;
}
