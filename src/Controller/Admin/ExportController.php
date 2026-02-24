<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Interface\ExportServiceInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ExportController extends AbstractController
{
    #[Route('/admin/exports/ventes.{format}', name: 'admin_export_ventes', requirements: ['format' => 'csv|xls'], methods: ['GET'])]
    public function ventes(string $format, ExportServiceInterface $exportService): Response
    {
        return $this->download($exportService->exportVentesCsv(), 'ventes', $format);
    }

    #[Route('/admin/exports/stock-restant.{format}', name: 'admin_export_stock_restant', requirements: ['format' => 'csv|xls'], methods: ['GET'])]
    public function stockRestant(string $format, ExportServiceInterface $exportService): Response
    {
        return $this->download($exportService->exportStockRestantCsv(), 'stock_restant', $format);
    }

    #[Route('/admin/exports/comptabilite.{format}', name: 'admin_export_comptabilite', requirements: ['format' => 'csv|xls'], methods: ['GET'])]
    public function comptabilite(string $format, ExportServiceInterface $exportService): Response
    {
        return $this->download($exportService->exportComptabiliteCsv(), 'comptabilite', $format);
    }

    private function download(string $content, string $basename, string $format): Response
    {
        $date = (new \DateTimeImmutable())->format('Ymd_His');
        $contentType = $format === 'xls'
            ? 'application/vnd.ms-excel; charset=UTF-8'
            : 'text/csv; charset=UTF-8';

        return new Response($content, Response::HTTP_OK, [
            'Content-Type' => $contentType,
            'Content-Disposition' => sprintf('attachment; filename="%s_%s.%s"', $basename, $date, $format),
        ]);
    }
}
