<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Utilisateur;
use App\Enum\ProfilUtilisateur;
use App\Exception\JourLivraisonNonPleinException;
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
        $creneaux = $this->creneauManager->getDisponiblesPourCheckout($date);

        return $this->render('checkout/creneaux.html.twig', [
            'creneaux' => $creneaux,
        ]);
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
        $nom = trim($request->request->getString('nom'));
        $prenom = trim($request->request->getString('prenom'));
        $numeroAgent = trim($request->request->getString('numeroAgent'));
        if ($nom === '' || $prenom === '' || !preg_match('/^\d{5}$/', $numeroAgent)) {
            $this->addFlash('error', 'Nom, prenom et numero d\'agent (5 chiffres) sont obligatoires.');

            return $this->redirectToRoute('checkout_creneaux');
        }

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
                $nom,
                $prenom,
            );
            $request->getSession()->set('checkout_last_commande_id', $commande->getId());
            $this->addFlash('success', 'Commande confirmée.');

            return $this->redirectToRoute('checkout_confirmation');
        } catch (JourLivraisonNonPleinException $exception) {
            $this->addFlash('warning', $exception->getMessage());

            return $this->redirectToRoute('checkout_creneaux');
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
