<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Commande;
use App\Interface\UrssafExportServiceInterface;
use App\Repository\CommandeRepository;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

final class UrssafExportService implements UrssafExportServiceInterface
{
    public function __construct(private readonly CommandeRepository $commandeRepository)
    {
    }

    public function exportXlsx(): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('URSSAF');
        $sheet->fromArray(
            ['Libelle', 'Numero inventaire', 'VNC', 'Type client', 'Nom', 'Prenom'],
            null,
            'A1',
        );

        $row = 2;
        foreach ($this->commandeRepository->findForVentesExport() as $commande) {
            foreach ($commande->getLignesCommande() as $ligneCommande) {
                $produit = $ligneCommande->getProduit();
                $sheet->setCellValue('A' . $row, $produit->getLibelle());
                $sheet->setCellValue('B' . $row, $produit->getNumeroInventaire() ?? '');
                $sheet->setCellValueExplicit('C' . $row, $produit->getVnc(), DataType::TYPE_STRING);
                $sheet->setCellValue('D' . $row, $this->resolveClientType($commande));
                $sheet->setCellValue('E' . $row, $commande->getNom() ?? '');
                $sheet->setCellValue('F' . $row, $commande->getPrenom() ?? '');
                ++$row;
            }
        }

        foreach (range('A', 'F') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $sheet->getStyle('A1:F1')->getFont()->setBold(true);
        $sheet->getStyle('C:C')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);

        return $this->toXlsxBinary($spreadsheet);
    }

    private function resolveClientType(Commande $commande): string
    {
        $roles = $commande->getUtilisateur()->getRoles();

        return match (true) {
            in_array('ROLE_ADMIN', $roles, true) => 'institution/partenaire',
            in_array('ROLE_PARTENAIRE', $roles, true) => 'institution/partenaire',
            in_array('ROLE_TELETRAVAILLEUR', $roles, true) => 'agent (teletravailleur)',
            default => $commande->getProfilCommande()->value,
        };
    }

    private function toXlsxBinary(Spreadsheet $spreadsheet): string
    {
        $stream = fopen('php://temp', 'wb+');
        if ($stream === false) {
            throw new \RuntimeException('Impossible de generer le fichier Excel URSSAF.');
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save($stream);
        rewind($stream);
        $content = stream_get_contents($stream);
        fclose($stream);
        $spreadsheet->disconnectWorksheets();

        if ($content === false) {
            throw new \RuntimeException('Impossible de lire le fichier Excel URSSAF genere.');
        }

        return $content;
    }
}
