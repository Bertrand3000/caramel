<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Utilisateur;
use App\Enum\ProfilUtilisateur;
use App\Interface\CheckoutServiceInterface;
use App\Interface\SlotManagerInterface;
use App\Repository\CommandeRepository;
use App\Repository\CreneauRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/commande')]
final class CheckoutController extends AbstractController
{
    public function __construct(
        private readonly SlotManagerInterface $creneauManager,
        private readonly CheckoutServiceInterface $checkoutService,
    ) {
    }

    #[Route('/creneaux', name: 'checkout_creneaux', methods: ['GET'])]
    public function creneaux(): Response
    {
        $date = new \DateTimeImmutable('today');
        $creneaux = [];

        foreach ($this->creneauManager->getDisponibles($date) as $creneau) {
            $disponible = $this->creneauManager->getJaugeDisponible($creneau);
            $used = max(0, $creneau->getCapaciteMax() - $disponible);
            $creneaux[] = [
                'entity' => $creneau,
                'used' => $used,
                'percent' => $creneau->getCapaciteMax() > 0 ? (int) round(($used / $creneau->getCapaciteMax()) * 100) : 0,
            ];
        }

        return $this->render('checkout/creneaux.html.twig', ['creneaux' => $creneaux]);
    }

    #[Route('/confirmer', name: 'checkout_confirmer', methods: ['POST'])]
    public function confirmCommande(Request $request, CreneauRepository $creneauRepository): RedirectResponse
    {
        $sessionId = $request->getSession()->getId();
        $creneauId = $request->request->getInt('creneauId');
        $numeroAgent = trim($request->request->getString('numeroAgent'));
        $creneau = $creneauRepository->find($creneauId);

        if ($creneau === null) {
            $this->addFlash('error', 'Créneau obligatoire pour valider la commande.');

            return $this->redirectToRoute('checkout_creneaux');
        }

        $user = $this->getUser();
        if (!$user instanceof Utilisateur) {
            $this->addFlash('error', 'Connexion requise pour valider la commande.');

            return $this->redirectToRoute('login');
        }

        try {
            $commande = $this->checkoutService->confirmCommande(
                $sessionId,
                $creneau,
                $this->resolveProfilUtilisateur($user),
                $user,
                $numeroAgent !== '' ? $numeroAgent : null,
            );
            $request->getSession()->set('checkout_last_commande_id', $commande->getId());
            $this->addFlash('success', 'Commande confirmée.');

            return $this->redirectToRoute('checkout_confirmation');
        } catch (\RuntimeException $exception) {
            $this->addFlash('error', $exception->getMessage());

            return $this->redirectToRoute('checkout_creneaux');
        }
    }

    #[Route('/confirmation', name: 'checkout_confirmation', methods: ['GET'])]
    public function confirmation(Request $request, CommandeRepository $commandeRepository): Response
    {
        $id = $request->getSession()->get('checkout_last_commande_id');
        $commande = $id ? $commandeRepository->find($id) : null;

        return $this->render('checkout/confirmation.html.twig', ['commande' => $commande]);
    }

    #[Route('/annuler/{id}', name: 'checkout_annuler', methods: ['GET'])]
    public function annulerCommande(int $id, CommandeRepository $commandeRepository): RedirectResponse
    {
        $commande = $commandeRepository->find($id);

        if ($commande === null) {
            $this->addFlash('error', 'Commande introuvable.');

            return $this->redirectToRoute('checkout_confirmation');
        }

        try {
            $this->checkoutService->annulerCommande($commande);
            $this->addFlash('success', 'Commande annulée.');
        } catch (\RuntimeException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('checkout_confirmation');
    }

    private function resolveProfilUtilisateur(Utilisateur $user): ProfilUtilisateur
    {
        if (in_array('ROLE_PARTENAIRE', $user->getRoles(), true)) {
            return ProfilUtilisateur::PARTENAIRE;
        }

        if (in_array('ROLE_TELETRAVAILLEUR', $user->getRoles(), true)) {
            return ProfilUtilisateur::TELETRAVAILLEUR;
        }

        return ProfilUtilisateur::PUBLIC;
    }
}
