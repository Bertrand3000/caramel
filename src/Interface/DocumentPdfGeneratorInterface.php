<?php

declare(strict_types=1);

namespace App\Interface;

use App\Entity\Commande;

/**
 * Génère les documents PDF liés à une commande (bon de commande, bon de préparation, bon de livraison).
 */
interface DocumentPdfGeneratorInterface
{
    /**
     * Génère un PDF "bon de commande" pour la commande donnée,
     * accessible quel que soit le statut de la commande.
     *
     * @return string Contenu binaire PDF
     */
    public function generateBonCommande(Commande $commande): string;

    /**
     * Génère un PDF "bon de préparation" avec 3 sections produit (découpables),
     * paginé par 3 produits par page si la commande en contient plus.
     *
     * @return string Contenu binaire PDF
     */
    public function generateBonPreparation(Commande $commande): string;

    /**
     * Génère un PDF "bon de livraison" (attestation de cession à titre gratuit)
     * avec la liste des biens pré-remplie et les espaces de signature vides.
     *
     * @return string Contenu binaire PDF
     */
    public function generateBonLivraison(Commande $commande): string;
}
