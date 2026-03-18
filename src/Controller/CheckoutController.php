<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Utilisateur;
use App\Enum\ProfilUtilisateur;
use App\Exception\CommandeDejaExistanteException;
use App\Exception\JourLivraisonNonPleinException;
use App\Interface\BoutiqueAccessCheckerInterface;
use App\Interface\CartManagerInterface;
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
        private readonly CartManagerInterface $cartManager,
    ) {
    }

    #[Route('/creneaux', name: 'checkout_creneaux', methods: ['GET'])]
    public function creneaux(Request $request): Response
    {
        $this->boutiqueAccessChecker->assertOpenForRoles($this->getUser()?->getRoles() ?? []);
        $sessionId = $request->getSession()->getId();
        if (!$this->checkoutService->hasItems($sessionId)) {
            $this->addFlash('error', 'Votre panier est vide.');

            return $this->redirectToRoute('cart_index');
        }
        $this->cartManager->extendActiveReservations($sessionId, 15);

        $date = new \DateTimeImmutable('tomorrow');
        $creneaux = $this->creneauManager->getDisponiblesPourCheckout($date);

        return $this->render('checkout/creneaux.html.twig', [
            'creneaux' => $creneaux,
        ]);
    }

    #[Route('/confirmer', name: 'checkout_confirmer', methods: ['POST'])]
    public function confirmCommande(
        Request $request,
        CreneauRepository $creneauRepository,
    ): RedirectResponse
    {
        $this->boutiqueAccessChecker->assertOpenForRoles($this->getUser()?->getRoles() ?? []);
        $sessionId = $request->getSession()->getId();
        if (!$this->checkoutService->hasItems($sessionId)) {
            $this->addFlash('error', 'Votre panier est vide.');

            return $this->redirectToRoute('cart_index');
        }

        $creneauId = $request->request->getInt('creneauId');
        $nom = trim($request->request->getString('nom'));
        $prenom = trim($request->request->getString('prenom'));
        $numeroAgent = trim($request->request->getString('numeroAgent'));

        $user = $this->getUser();
        $requiresNumeroAgent = $user instanceof Utilisateur && $this->requiresNumeroAgentForUser($user);
        $requiresCreneau = $user instanceof Utilisateur && $this->requiresCreneauForUser($user);

        if ($nom === '' || $prenom === '') {
            $this->addFlash('error', 'Nom et prénom sont obligatoires.');

            return $this->redirectToRoute('checkout_creneaux');
        }

        if ($requiresNumeroAgent && ($numeroAgent === '' || !preg_match('/^\d{5}$/', $numeroAgent))) {
            $this->addFlash('error', 'Le numéro d\'agent (5 chiffres) est obligatoire.');

            return $this->redirectToRoute('checkout_creneaux');
        }

        $creneau = null;
        if ($requiresCreneau) {
            if ($creneauId <= 0) {
                $this->addFlash('error', 'Veuillez choisir un créneau.');

                return $this->redirectToRoute('checkout_creneaux');
            }
            $creneau = $creneauRepository->find($creneauId);
            if ($creneau === null) {
                $this->addFlash('error', 'Créneau introuvable.');

                return $this->redirectToRoute('checkout_creneaux');
            }
            $today = (new \DateTimeImmutable('today'))->format('Y-m-d');
            if ($creneau->getDateHeure()->format('Y-m-d') === $today) {
                $this->addFlash('error', 'La réservation le jour même n\'est pas autorisée.');

                return $this->redirectToRoute('checkout_creneaux');
            }
        }

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
                $requiresNumeroAgent && $numeroAgent !== '' ? $numeroAgent : null,
                $nom,
                $prenom,
            );
            $request->getSession()->set('checkout_last_commande_id', $commande->getId());
            $this->addFlash('success', 'Commande confirmée.');

            return $this->redirectToRoute('checkout_confirmation');
        } catch (JourLivraisonNonPleinException $exception) {
            $this->addFlash('warning', $exception->getMessage());

            return $this->redirectToRoute('checkout_creneaux');
        } catch (CommandeDejaExistanteException $exception) {
            $this->addFlash('error', $exception->getMessage());

            return $this->redirectToRoute('checkout_creneaux');
        } catch (\RuntimeException $exception) {
            $this->addFlash('error', $exception->getMessage());

            return $this->redirectToRoute('checkout_creneaux');
        }
    }

    private function requiresNumeroAgentForUser(Utilisateur $user): bool
    {
        $roles = $user->getRoles();

        // ROLE_ADMIN et ROLE_PARTENAIRE ne nécessitent pas de numéro d'agent
        if (in_array('ROLE_ADMIN', $roles, true) || in_array('ROLE_PARTENAIRE', $roles, true)) {
            return false;
        }

        return in_array('ROLE_AGENT', $roles, true)
            || in_array('ROLE_TELETRAVAILLEUR', $roles, true);
    }

    private function requiresCreneauForUser(Utilisateur $user): bool
    {
        $roles = $user->getRoles();

        // ROLE_ADMIN et ROLE_PARTENAIRE ne nécessitent pas de créneau
        if (in_array('ROLE_ADMIN', $roles, true) || in_array('ROLE_PARTENAIRE', $roles, true)) {
            return false;
        }

        return in_array('ROLE_AGENT', $roles, true)
            || in_array('ROLE_TELETRAVAILLEUR', $roles, true);
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
