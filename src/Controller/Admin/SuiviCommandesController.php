<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Commande;
use App\Enum\CommandeStatutEnum;
use App\Form\ImportGrhCommandesType;
use App\Interface\CommandeDecisionServiceInterface;
use App\Interface\CommandeGrhImportServiceInterface;
use App\Repository\CommandeRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/admin/suivi-commandes', name: 'admin_suivi_commandes_')]
final class SuiviCommandesController extends AbstractController
{
    public function __construct(
        private readonly CommandeRepository $commandeRepository,
        private readonly CommandeGrhImportServiceInterface $commandeGrhImportService,
        private readonly CommandeDecisionServiceInterface $commandeDecisionService,
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/suivi_commandes/index.html.twig', [
            'commandes' => $this->commandeRepository->findEnAttenteValidationWithRelations(),
            'importForm' => $this->createForm(ImportGrhCommandesType::class)->createView(),
        ]);
    }

    #[Route('/import-grh', name: 'import_grh', methods: ['POST'])]
    public function importGrh(Request $request): RedirectResponse
    {
        $form = $this->createForm(ImportGrhCommandesType::class);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $file = $form->get('xlsxFile')->getData();
                $result = $this->commandeGrhImportService->importFromXlsx($file->getPathname());
                $this->addFlash('success', sprintf(
                    'Import terminé : %d commande(s) enrichie(s) sur %d lignes traitées.',
                    $result->matchedCount,
                    $result->processedRows,
                ));
            } catch (\Throwable $exception) {
                $this->addFlash('error', sprintf('Import GRH impossible : %s', $exception->getMessage()));
            }
        }

        return $this->redirectToRoute('admin_suivi_commandes_index');
    }

    #[Route('/{id}', name: 'show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(int $id): Response
    {
        $commande = $this->commandeRepository->findOneForSuiviCommandes($id);
        if ($commande === null) {
            throw $this->createNotFoundException('Commande introuvable.');
        }

        return $this->render('admin/suivi_commandes/show.html.twig', ['commande' => $commande]);
    }

    #[Route('/{id}/valider', name: 'valider', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function valider(int $id, Request $request): RedirectResponse
    {
        return $this->applyDecision($id, $request, 'valider_commande_%d', CommandeStatutEnum::VALIDEE);
    }

    #[Route('/{id}/refuser', name: 'refuser', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function refuser(int $id, Request $request): RedirectResponse
    {
        return $this->applyDecision($id, $request, 'refuser_commande_%d', CommandeStatutEnum::ANNULEE);
    }

    private function applyDecision(
        int $id,
        Request $request,
        string $csrfTokenPattern,
        CommandeStatutEnum $status,
    ): RedirectResponse {
        if (!$this->isCsrfTokenValid(sprintf($csrfTokenPattern, $id), $request->request->getString('_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');

            return $this->redirectToRoute('admin_suivi_commandes_index');
        }

        $commande = $this->commandeRepository->findOneForSuiviCommandes($id);
        if (!$commande instanceof Commande) {
            throw $this->createNotFoundException('Commande introuvable.');
        }

        if ($this->commandeDecisionService->apply($commande, $status)) {
            if ($status === CommandeStatutEnum::VALIDEE) {
                $this->addFlash(
                    'success',
                    'Commande validée. Le créneau est confirmé et un email de confirmation a été envoyé au client.',
                );
            } else {
                $this->addFlash(
                    'error',
                    'Commande refusée et annulée. Le créneau a été libéré, les articles remis en stock, et un email a été envoyé au client.',
                );
            }
        } else {
            $message = $status === CommandeStatutEnum::VALIDEE
                ? 'Commande validée. Le créneau est confirmé, mais aucun email n’a pu être envoyé (email GRH manquant).'
                : 'Commande refusée et annulée. Le créneau a été libéré et les articles remis en stock, mais aucun email n’a pu être envoyé (email GRH manquant).';
            $this->addFlash($status === CommandeStatutEnum::VALIDEE ? 'warning' : 'error', $message);
        }

        return $this->redirectToRoute('admin_suivi_commandes_index');
    }
}
