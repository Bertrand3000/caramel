<?php

declare(strict_types=1);

namespace App\Service;

use App\Interface\ExportServiceInterface;
use App\Repository\CommandeRepository;
use App\Repository\ProduitRepository;

final class ExportService implements ExportServiceInterface
{
    private const UTF8_BOM = "\xEF\xBB\xBF";

    public function __construct(
        private readonly CommandeRepository $commandeRepository,
        private readonly ProduitRepository $produitRepository,
    ) {
    }
    public function exportVentesCsv(): string
    {
        $rows = [];
        foreach ($this->commandeRepository->findForVentesExport() as $commande) {
            foreach ($commande->getLignesCommande() as $ligneCommande) {
                $produit = $ligneCommande->getProduit();
                $rows[] = [
                    (string) $commande->getId(),
                    $commande->getDateValidation()->format('Y-m-d H:i:s'),
                    (string) ($commande->getNumeroAgent() ?? ''),
                    (string) ($commande->getNom() ?? ''),
                    (string) ($commande->getPrenom() ?? ''),
                    $commande->getProfilCommande()->value,
                    $commande->getStatut()->value,
                    (string) $produit->getId(),
                    (string) ($produit->getNumeroInventaire() ?? ''),
                    $produit->getLibelle(),
                    (string) $ligneCommande->getQuantite(),
                ];
            }
        }
        return $this->toCsv([
            'commande_id',
            'date_validation',
            'numero_agent',
            'nom',
            'prenom',
            'profil_commande',
            'statut_commande',
            'produit_id',
            'numero_inventaire',
            'libelle',
            'quantite',
        ], $rows);
    }
    public function exportStockRestantCsv(): string
    {
        $rows = [];
        foreach ($this->produitRepository->findForStockRestantExport() as $produit) {
            $rows[] = [
                (string) $produit->getId(),
                (string) ($produit->getNumeroInventaire() ?? ''),
                $produit->getLibelle(),
                $produit->getEtat()->value,
                $produit->isTagTeletravailleur() ? '1' : '0',
                $produit->getEtage(),
                $produit->getPorte(),
                (string) $produit->getLargeur(),
                (string) $produit->getHauteur(),
                (string) $produit->getProfondeur(),
                (string) $produit->getQuantite(),
                $produit->getStatut()->value,
            ];
        }
        return $this->toCsv([
            'produit_id',
            'numero_inventaire',
            'libelle',
            'etat',
            'tag_teletravailleur',
            'etage',
            'porte',
            'largeur_cm',
            'hauteur_cm',
            'profondeur_cm',
            'quantite',
            'statut',
        ], $rows);
    }
    public function exportComptabiliteCsv(): string
    {
        $rows = [];
        foreach ($this->commandeRepository->findForVentesExport() as $commande) {
            $totalArticles = 0;
            $totalLignes = 0;
            foreach ($commande->getLignesCommande() as $ligneCommande) {
                ++$totalLignes;
                $totalArticles += $ligneCommande->getQuantite();
            }
            $creneau = $commande->getCreneau();
            $rows[] = [
                (string) $commande->getId(),
                $commande->getDateValidation()->format('Y-m-d H:i:s'),
                (string) ($commande->getNumeroAgent() ?? ''),
                (string) ($commande->getNom() ?? ''),
                (string) ($commande->getPrenom() ?? ''),
                $commande->getProfilCommande()->value,
                $commande->getStatut()->value,
                $creneau?->getDateHeure()->format('Y-m-d') ?? '',
                $creneau?->getHeureDebut()->format('H:i') ?? '',
                $creneau?->getHeureFin()->format('H:i') ?? '',
                (string) $totalLignes,
                (string) $totalArticles,
            ];
        }
        return $this->toCsv([
            'commande_id',
            'date_validation',
            'numero_agent',
            'nom',
            'prenom',
            'profil_commande',
            'statut_commande',
            'date_creneau',
            'heure_debut',
            'heure_fin',
            'total_lignes',
            'total_articles',
        ], $rows);
    }
    /** @param list<string> $header @param list<list<string>> $rows */
    private function toCsv(array $header, array $rows): string
    {
        $stream = fopen('php://temp', 'wb+');
        if ($stream === false) {
            throw new \RuntimeException('Impossible de générer le CSV.');
        }

        fputcsv($stream, $header, ';');
        foreach ($rows as $row) {
            fputcsv($stream, $row, ';');
        }

        rewind($stream);
        $content = stream_get_contents($stream);
        fclose($stream);

        return self::UTF8_BOM.($content === false ? '' : $content);
    }
}
