<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Utilisateur;
use App\Enum\ProfilUtilisateur;
use App\Interface\BoutiqueAccessCheckerInterface;
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
        private readonly BoutiqueAccessCheckerInterface $boutiqueAccessChecker,
    ) {
    }

    #[Route('/creneaux', name: 'checkout_creneaux', methods: ['GET'])]
    public function creneaux(Request $request): Response
    {
        $this->boutiqueAccessChecker->assertOpenForRoles($this->getUser()?->getRoles() ?? []);
        if (!$this->checkoutService->hasItems($request->getSession()->getId())) {
            $this->addFlash('error', 'Votre panier est vide.');

            return $this->redirectToRoute('cart_index');
        }

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
        $this->boutiqueAccessChecker->assertOpenForRoles($this->getUser()?->getRoles() ?? []);
        $sessionId = $request->getSession()->getId();
        if (!$this->checkoutService->hasItems($sessionId)) {
            $this->addFlash('error', 'Votre panier est vide.');

            return $this->redirectToRoute('cart_index');
        }

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
        $this->boutiqueAccessChecker->assertOpenForRoles($this->getUser()?->getRoles() ?? []);
        $id = $request->getSession()->get('checkout_last_commande_id');
        $commande = $id ? $commandeRepository->find($id) : null;

        return $this->render('checkout/confirmation.html.twig', ['commande' => $commande]);
    }

    #[Route('/annuler/{id}', name: 'checkout_annuler', methods: ['POST'])]
    public function annulerCommande(int $id, Request $request, CommandeRepository $commandeRepository): RedirectResponse
    {
        $this->boutiqueAccessChecker->assertOpenForRoles($this->getUser()?->getRoles() ?? []);
        $user = $this->getUser();
        if (!$user instanceof Utilisateur) {
            $this->addFlash('error', 'Connexion requise pour annuler la commande.');

            return $this->redirectToRoute('login');
        }

        $commande = $commandeRepository->find($id);

        if ($commande === null) {
            $this->addFlash('error', 'Commande introuvable.');

            return $this->redirectToRoute('checkout_confirmation');
        }

        $commandeUser = $commande->getUtilisateur();
        if (!$commandeUser instanceof Utilisateur || $commandeUser->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid(
            sprintf('annuler_commande_%d', $commande->getId()),
            $request->request->getString('_token'),
        )) {
            $this->addFlash('error', 'Jeton CSRF invalide.');

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
        if (in_array('ROLE_DMAX', $user->getRoles(), true)) {
            return ProfilUtilisateur::DMAX;
        }

        if (in_array('ROLE_PARTENAIRE', $user->getRoles(), true)) {
            return ProfilUtilisateur::PARTENAIRE;
        }

        if (in_array('ROLE_TELETRAVAILLEUR', $user->getRoles(), true)) {
            return ProfilUtilisateur::TELETRAVAILLEUR;
        }

        return ProfilUtilisateur::PUBLIC;
    }
}
