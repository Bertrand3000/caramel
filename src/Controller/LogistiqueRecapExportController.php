<?php

declare(strict_types=1);

namespace App\Controller;

use App\Interface\LogistiqueServiceInterface;
use App\Interface\RecapMaterielExportServiceInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_DMAX')]
final class LogistiqueRecapExportController extends AbstractController
{
    #[Route('/logistique/recap/export.xlsx', name: 'logistique_recap_export_xlsx', methods: ['GET'])]
    public function exportXlsx(
        LogistiqueServiceInterface $logistiqueService,
        RecapMaterielExportServiceInterface $recapExportService,
    ): Response {
        $jour = $logistiqueService->findNextDeliveryDay();
        if ($jour === null) {
            return $this->redirectWithWarning();
        }

        $recapMateriel = $logistiqueService->findRecapMateriel($jour);
        if ($recapMateriel === []) {
            return $this->redirectWithWarning();
        }

        $xlsx = $recapExportService->exportRecapMaterielXlsx($jour, $recapMateriel);
        $filename = sprintf('recap_materiel_%s.xlsx', $jour->getDate()->format('Ymd'));

        return new Response($xlsx, Response::HTTP_OK, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => sprintf('attachment; filename="%s"', $filename),
        ]);
    }

    private function redirectWithWarning(): RedirectResponse
    {
        $this->addFlash('warning', 'Aucun matériel à exporter pour la journée de livraison active.');

        return $this->redirectToRoute('logistique_recap');
    }
}
