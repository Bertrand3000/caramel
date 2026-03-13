<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\JourLivraison;
use App\Interface\RecapMaterielExportServiceInterface;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

final class RecapMaterielExportService implements RecapMaterielExportServiceInterface
{
    public function exportRecapMaterielXlsx(JourLivraison $jour, array $recapMateriel): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Recap materiel');
        $etageColors = ['FFFFE0E0', 'FFE0ECFF', 'FFE3F7E3', 'FFFFF0CC', 'FFF0E0FF', 'FFE0F7F7', 'FFFFD9E8', 'FFE6E6FA'];

        $headers = ['Jour livraison', 'Etage', 'Porte', 'Produit', 'N° inventaire', 'Quantite', 'Commande', 'Agent'];
        $sheet->fromArray($headers, null, 'A1');

        $rowIndex = 2;
        $etageIndex = 0;
        foreach ($recapMateriel as $etage => $portes) {
            $startRow = $rowIndex;
            foreach ($portes as $porte => $items) {
                foreach ($items as $item) {
                    $sheet->fromArray([
                        $jour->getDate()->format('d/m/Y'),
                        (string) $etage,
                        (string) $porte,
                        $item['produit']->getLibelle(),
                        $item['produit']->getNumeroInventaire() ?? '',
                        (int) $item['quantite'],
                        (string) $item['commandeId'],
                        $item['agent'],
                    ], null, 'A' . $rowIndex);
                    ++$rowIndex;
                }
            }

            $endRow = $rowIndex - 1;
            if ($endRow >= $startRow) {
                $color = $etageColors[$etageIndex % count($etageColors)];
                $sheet->getStyle(sprintf('A%d:H%d', $startRow, $endRow))
                    ->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()
                    ->setARGB($color);
                ++$etageIndex;
            }
        }

        foreach (range('A', 'H') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $lastDataRow = max($rowIndex - 1, 1);

        $sheet->getStyle('A1:H1')->getFont()->setBold(true);
        $sheet->getStyle(sprintf('A1:G%d', $lastDataRow))
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);

        $spreadsheet->setActiveSheetIndex(0);
        $sheet->setSelectedCell('A1');
        $sheet->setTopLeftCell('A1');

        return $this->toXlsxBinary($spreadsheet);
    }

    private function toXlsxBinary(Spreadsheet $spreadsheet): string
    {
        $stream = fopen('php://temp', 'wb+');
        if ($stream === false) {
            throw new \RuntimeException('Impossible de générer le fichier Excel.');
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save($stream);
        rewind($stream);
        $content = stream_get_contents($stream);
        fclose($stream);
        $spreadsheet->disconnectWorksheets();

        if ($content === false) {
            throw new \RuntimeException('Impossible de lire le fichier Excel généré.');
        }

        return $content;
    }
}
