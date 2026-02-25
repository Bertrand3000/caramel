<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Commande;
use App\Entity\JourLivraison;
use App\Form\JourLivraisonType;
use App\Interface\CheckoutServiceInterface;
use App\Interface\CreneauGeneratorInterface;
use App\Repository\CreneauRepository;
use App\Repository\JourLivraisonRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Workflow\Exception\ExceptionInterface as WorkflowException;

#[Route('/admin/jours-livraison', name: 'admin_jours_livraison_')]
class JourLivraisonController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly JourLivraisonRepository $jourLivraisonRepository,
        private readonly CreneauRepository $creneauRepository,
        private readonly CreneauGeneratorInterface $creneauGenerator,
        private readonly CheckoutServiceInterface $checkoutService,
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        $rows = array_map(fn (JourLivraison $jour) => $this->buildIndexRow($jour), $this->jourLivraisonRepository->findAllWithCreneauxOrderedByDate());

        return $this->render('admin/jours_livraison/index.html.twig', ['rows' => $rows]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $jour = (new JourLivraison())
            ->setCoupureMeridienne(true)
            ->setHeureCoupureDebut(new \DateTime('12:00:00'))
            ->setHeureCoupureFin(new \DateTime('13:00:00'));
        $form = $this->createForm(JourLivraisonType::class, $jour);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->normalizeBreakFields($jour);
            $this->entityManager->persist($jour);
            $this->entityManager->flush();
            $this->addFlash('success', 'Journée de livraison créée.');

            return $this->redirectToRoute('admin_jours_livraison_index');
        }
        if ($form->isSubmitted() && !$form->isValid()) {
            $this->addFlash('error', 'Création impossible: merci de corriger les champs du formulaire.');
        }

        return $this->render('admin/jours_livraison/form.html.twig', [
            'form' => $form->createView(),
            'jour' => $jour,
            'isEdit' => false,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(JourLivraison $jour, Request $request): Response
    {
        $form = $this->createForm(JourLivraisonType::class, $jour);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->normalizeBreakFields($jour);
            $this->entityManager->flush();
            $this->addFlash('success', 'Journée de livraison mise à jour.');

            return $this->redirectToRoute('admin_jours_livraison_index');
        }
        if ($form->isSubmitted() && !$form->isValid()) {
            $this->addFlash('error', 'Enregistrement impossible: merci de corriger les champs du formulaire.');
        }

        return $this->render('admin/jours_livraison/form.html.twig', [
            'form' => $form->createView(),
            'jour' => $jour,
            'isEdit' => true,
        ]);
    }

    #[Route('/{id}/generer', name: 'generer', methods: ['POST'])]
    public function generer(JourLivraison $jour, Request $request): RedirectResponse
    {
        if (!$this->isCsrfTokenValid(sprintf('generer_jour_livraison_%d', $jour->getId()), $request->request->getString('_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');

            return $this->redirectToRoute('admin_jours_livraison_index');
        }

        $result = $this->creneauGenerator->generate($jour);
        $this->addFlash('success', sprintf('%d créneaux créés, %d supprimés, %d verrouillés.', $result->crees, $result->supprimes, $result->verrouilles));
        foreach ($result->avertissements as $warning) {
            $this->addFlash('warning', $warning);
        }

        return $this->redirectToRoute('admin_jours_livraison_creneaux', ['id' => $jour->getId()]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(JourLivraison $jour, Request $request): RedirectResponse
    {
        if (!$this->isCsrfTokenValid(sprintf('delete_jour_livraison_%d', $jour->getId()), $request->request->getString('_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');

            return $this->redirectToRoute('admin_jours_livraison_edit', ['id' => $jour->getId()]);
        }

        foreach ($this->creneauRepository->findByJourWithCommandes($jour) as $creneau) {
            foreach ($creneau->getCommandes() as $commande) {
                $commande->setCreneau(null);
            }
            $this->entityManager->remove($creneau);
        }

        $this->entityManager->remove($jour);
        $this->entityManager->flush();
        $this->addFlash('success', 'Journée de livraison supprimée avec ses créneaux.');

        return $this->redirectToRoute('admin_jours_livraison_index');
    }

    #[Route('/{id}/creneaux', name: 'creneaux', methods: ['GET'])]
    public function vueCreneaux(JourLivraison $jour): Response
    {
        $creneaux = $this->creneauRepository->findByJourWithCommandes($jour);
        $stats = $this->computeStats($creneaux);

        return $this->render('admin/jours_livraison/creneaux.html.twig', ['jour' => $jour, 'creneaux' => $creneaux, 'stats' => $stats]);
    }

    #[Route('/{id}/creneaux/{creneauId}/commandes/{commandeId}/annuler', name: 'annuler_reservation', methods: ['POST'])]
    public function annulerReservation(JourLivraison $jour, int $creneauId, int $commandeId, Request $request): RedirectResponse
    {
        if (!$this->isCsrfTokenValid(sprintf('admin_annuler_commande_%d', $commandeId), $request->request->getString('_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');

            return $this->redirectToRoute('admin_jours_livraison_creneaux', ['id' => $jour->getId()]);
        }

        $creneau = $this->creneauRepository->find($creneauId);
        $commande = $this->entityManager->find(Commande::class, $commandeId);
        if ($creneau === null || $commande === null || $creneau->getJourLivraison()?->getId() !== $jour->getId() || $commande->getCreneau()?->getId() !== $creneau->getId()) {
            $this->addFlash('error', 'Réservation introuvable pour cette journée.');

            return $this->redirectToRoute('admin_jours_livraison_creneaux', ['id' => $jour->getId()]);
        }

        try {
            $this->checkoutService->annulerCommande($commande);
            $this->addFlash('success', 'Commande annulée et créneau libéré.');
        } catch (WorkflowException $exception) {
            $this->addFlash('error', sprintf('Transition workflow impossible : %s', $exception->getMessage()));
        } catch (\RuntimeException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('admin_jours_livraison_creneaux', ['id' => $jour->getId()]);
    }

    private function normalizeBreakFields(JourLivraison $jour): void
    {
        if (!$jour->isCoupureMeridienne()) {
            $jour->setHeureCoupureDebut(null);
            $jour->setHeureCoupureFin(null);
        }
    }

    /** @param list<\App\Entity\Creneau> $creneaux */
    private function computeStats(array $creneaux): array
    {
        $used = array_sum(array_map(static fn ($c) => $c->getCapaciteUtilisee(), $creneaux));
        $capacity = array_sum(array_map(static fn ($c) => $c->getCapaciteMax(), $creneaux));

        return ['used' => $used, 'capacity' => $capacity, 'percent' => $capacity > 0 ? (int) round(($used / $capacity) * 100) : 0];
    }

    private function buildIndexRow(JourLivraison $jour): array
    {
        $stats = $this->computeStats($jour->getCreneaux()->toArray());

        return ['jour' => $jour, 'nbCreneaux' => $jour->getCreneaux()->count(), 'stats' => $stats];
    }
}
