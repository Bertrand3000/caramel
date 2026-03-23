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
use App\Interface\ToutDoitDisparaitreServiceInterface;
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
    private const SESSION_KEY_CHECKOUT_DATA = 'checkout_prepared_data';

    public function __construct(
        private readonly SlotManagerInterface $creneauManager,
        private readonly CheckoutServiceInterface $checkoutService,
        private readonly BoutiqueAccessCheckerInterface $boutiqueAccessChecker,
        private readonly CartManagerInterface $cartManager,
        private readonly ToutDoitDisparaitreServiceInterface $toutDoitDisparaitreService,
    ) {
    }

    #[Route('/creneaux', name: 'checkout_creneaux', methods: ['GET'])]
    public function creneaux(Request $request): Response
    {
        $this->boutiqueAccessChecker->assertOpenForRoles($this->getUser()?->getRoles() ?? []);
        $sessionId = $request->getSession()->getId();
        if (!$this->checkoutService->hasItems($sessionId)) {
            $this->clearPreparedCheckout($request);
            $this->addFlash('error', 'Votre panier est vide.');

            return $this->redirectToRoute('cart_index');
        }
        $this->cartManager->extendActiveReservations($sessionId, 15);

        $user = $this->getUser();
        $preparedCheckout = $this->getPreparedCheckout($request);
        $requiresCreneau = $user instanceof Utilisateur && $this->requiresCreneauForUser($user);
        $creneaux = [];
        if ($preparedCheckout !== null && $requiresCreneau) {
            $date = new \DateTimeImmutable('tomorrow');
            $creneaux = $this->creneauManager->getDisponiblesPourCheckout($date);
        }

        return $this->render('checkout/creneaux.html.twig', [
            'creneaux' => $creneaux,
            'modeToutDoitDisparaitre' => $this->toutDoitDisparaitreService->isEnabled(),
            'preparedCheckout' => $preparedCheckout,
        ]);
    }

    #[Route('/preparer', name: 'checkout_preparer', methods: ['POST'])]
    public function preparerCommande(
        Request $request,
    ): RedirectResponse
    {
        $this->boutiqueAccessChecker->assertOpenForRoles($this->getUser()?->getRoles() ?? []);
        $sessionId = $request->getSession()->getId();
        if (!$this->checkoutService->hasItems($sessionId)) {
            $this->clearPreparedCheckout($request);
            $this->addFlash('error', 'Votre panier est vide.');

            return $this->redirectToRoute('cart_index');
        }

        $nom = trim($request->request->getString('nom'));
        $prenom = trim($request->request->getString('prenom'));
        $numeroAgent = trim($request->request->getString('numeroAgent'));

        $user = $this->getUser();
        if (!$user instanceof Utilisateur) {
            $this->addFlash('error', 'Connexion requise pour valider la commande.');

            return $this->redirectToRoute('login');
        }

        $profil = $this->resolveProfilUtilisateur($user);
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

        $commandeACompleter = $this->toutDoitDisparaitreService->findCommandeACompleter($numeroAgent, $profil);
        $this->storePreparedCheckout($request, $nom, $prenom, $requiresNumeroAgent ? $numeroAgent : null);

        if ($requiresCreneau && $commandeACompleter === null) {
            return $this->redirectToRoute('checkout_creneaux');
        }

        return $this->finalizePreparedCheckout($request, null, $profil, $user, $commandeACompleter !== null);
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
            $this->clearPreparedCheckout($request);
            $this->addFlash('error', 'Votre panier est vide.');

            return $this->redirectToRoute('cart_index');
        }

        $user = $this->getUser();
        if (!$user instanceof Utilisateur) {
            $this->addFlash('error', 'Connexion requise pour valider la commande.');

            return $this->redirectToRoute('login');
        }

        $profil = $this->resolveProfilUtilisateur($user);
        $preparedCheckout = $this->getPreparedCheckout($request);
        if ($preparedCheckout === null) {
            $this->addFlash('error', 'Vos informations de commande ont expiré. Merci de les ressaisir.');

            return $this->redirectToRoute('checkout_creneaux');
        }

        $numeroAgent = $preparedCheckout['numeroAgent'];
        $commandeACompleter = $this->toutDoitDisparaitreService->findCommandeACompleter($numeroAgent ?? '', $profil);
        if ($commandeACompleter !== null) {
            return $this->finalizePreparedCheckout($request, null, $profil, $user, true);
        }

        $creneauId = $request->request->getInt('creneauId');
        if ($creneauId <= 0) {
            $this->addFlash('error', 'Veuillez choisir un créneau.');

            return $this->redirectToRoute('checkout_creneaux');
        }

        $creneau = $creneauRepository->find($creneauId);
        if ($creneau === null) {
            $this->addFlash('error', 'Créneau introuvable.');

            return $this->redirectToRoute('checkout_creneaux');
        }

        $disponibles = $this->creneauManager->getDisponiblesPourCheckout(new \DateTimeImmutable('tomorrow'));
        $disponibleIds = array_map(static fn (\App\Entity\Creneau $slot): ?int => $slot->getId(), $disponibles);
        if (!in_array($creneau->getId(), $disponibleIds, true)) {
            $this->addFlash('error', 'Ce creneau n\'est plus disponible.');

            return $this->redirectToRoute('checkout_creneaux');
        }

        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');
        if ($creneau->getDateHeure()->format('Y-m-d') === $today) {
            $this->addFlash('error', 'La réservation le jour même n\'est pas autorisée.');

            return $this->redirectToRoute('checkout_creneaux');
        }

        return $this->finalizePreparedCheckout($request, $creneau, $profil, $user, false);
    }

    #[Route('/identite/reinitialiser', name: 'checkout_reset_identite', methods: ['POST'])]
    public function resetIdentite(Request $request): RedirectResponse
    {
        $this->boutiqueAccessChecker->assertOpenForRoles($this->getUser()?->getRoles() ?? []);
        $this->clearPreparedCheckout($request);

        return $this->redirectToRoute('checkout_creneaux');
    }

    private function finalizePreparedCheckout(
        Request $request,
        ?\App\Entity\Creneau $creneau,
        ProfilUtilisateur $profil,
        Utilisateur $user,
        bool $isCompletion,
    ): RedirectResponse {
        $preparedCheckout = $this->getPreparedCheckout($request);
        if ($preparedCheckout === null) {
            $this->addFlash('error', 'Vos informations de commande ont expiré. Merci de les ressaisir.');

            return $this->redirectToRoute('checkout_creneaux');
        }

        try {
            $commande = $this->checkoutService->confirmCommande(
                $request->getSession()->getId(),
                $creneau,
                $profil,
                $user,
                $preparedCheckout['numeroAgent'],
                $preparedCheckout['nom'],
                $preparedCheckout['prenom'],
            );
            $request->getSession()->set('checkout_last_commande_id', $commande->getId());
            $this->clearPreparedCheckout($request);
            $this->addFlash(
                'success',
                !$isCompletion
                    ? 'Commande confirmée.'
                    : 'Commande complétée sur votre créneau existant.',
            );

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

    private function storePreparedCheckout(Request $request, string $nom, string $prenom, ?string $numeroAgent): void
    {
        $request->getSession()->set(self::SESSION_KEY_CHECKOUT_DATA, [
            'nom' => $nom,
            'prenom' => $prenom,
            'numeroAgent' => $numeroAgent,
        ]);
    }

    /**
     * @return array{nom: string, prenom: string, numeroAgent: ?string}|null
     */
    private function getPreparedCheckout(Request $request): ?array
    {
        $data = $request->getSession()->get(self::SESSION_KEY_CHECKOUT_DATA);
        if (!is_array($data)) {
            return null;
        }

        $nom = trim((string) ($data['nom'] ?? ''));
        $prenom = trim((string) ($data['prenom'] ?? ''));
        if ($nom === '' || $prenom === '') {
            return null;
        }

        $numeroAgent = $data['numeroAgent'] ?? null;
        if (!is_string($numeroAgent) || trim($numeroAgent) === '') {
            $numeroAgent = null;
        }

        return [
            'nom' => $nom,
            'prenom' => $prenom,
            'numeroAgent' => $numeroAgent,
        ];
    }

    private function clearPreparedCheckout(Request $request): void
    {
        $request->getSession()->remove(self::SESSION_KEY_CHECKOUT_DATA);
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
