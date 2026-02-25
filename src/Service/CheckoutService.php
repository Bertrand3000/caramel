<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Commande;
use App\Entity\Creneau;
use App\Entity\Utilisateur;
use App\Enum\CommandeProfilEnum;
use App\Enum\ProfilUtilisateur;
use App\Exception\JourLivraisonNonPleinException;
use App\Interface\CartManagerInterface;
use App\Interface\CheckoutServiceInterface;
use App\Interface\SlotManagerInterface;
use App\Repository\JourLivraisonRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Workflow\WorkflowInterface;

class CheckoutService implements CheckoutServiceInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CartManagerInterface $cartManager,
        private readonly CommandeLimitCheckerService $commandeLimitChecker,
        private readonly QuotaCheckerService $quotaChecker,
        private readonly SlotManagerInterface $creneauManager,
        #[Autowire(service: 'state_machine.commande_lifecycle')]
        private readonly WorkflowInterface $commandeLifecycle,
        private readonly ?JourLivraisonRepository $jourLivraisonRepository = null,
    ) {
    }

    public function hasItems(string $sessionId): bool
    {
        return count($this->cartManager->getContents($sessionId)) > 0;
    }

    public function confirmCommande(
        string $sessionId,
        Creneau $creneau,
        ProfilUtilisateur $profil,
        Utilisateur $utilisateur,
        ?string $numeroAgent = null,
        ?string $nom = null,
        ?string $prenom = null,
    ): Commande {
        return $this->em->wrapInTransaction(function () use ($sessionId, $creneau, $profil, $utilisateur, $numeroAgent, $nom, $prenom): Commande {
            $panier = $this->cartManager->getContents($sessionId);
            $totalQuantite = count($panier);
            if ($totalQuantite < 1) {
                throw new \RuntimeException('Panier vide');
            }

            $this->commandeLimitChecker->assertPeutCommander(trim((string) $numeroAgent), $profil);

            if (!$this->quotaChecker->check($sessionId, $profil, $totalQuantite, $numeroAgent)) {
                throw new \RuntimeException('Quota d articles dépassé');
            }

            $this->assertJourneePleineRule($creneau);
            $commande = $this->cartManager->validateCart($sessionId, $utilisateur);
            $this->creneauManager->reserverCreneau($creneau, $commande);
            if ($numeroAgent !== null && trim($numeroAgent) !== '') {
                $commande->setNumeroAgent(trim($numeroAgent));
            }
            if ($nom !== null && trim($nom) !== '') {
                $commande->setNom(trim($nom));
            }
            if ($prenom !== null && trim($prenom) !== '') {
                $commande->setPrenom(trim($prenom));
            }
            $commande->setProfilCommande($this->mapCommandeProfil($profil));

            return $commande;
        });
    }

    public function checkQuota(string $sessionId, ProfilUtilisateur $profil, ?string $numeroAgent = null): bool
    {
        $panier = $this->cartManager->getContents($sessionId);

        return $this->quotaChecker->check(
            $sessionId,
            $profil,
            count($panier),
            $numeroAgent,
        );
    }

    public function assignCreneau(Commande $commande, Creneau $creneau): void
    {
        $this->creneauManager->reserverCreneau($creneau, $commande);
    }

    public function annulerCommande(Commande $commande): void
    {
        $this->em->wrapInTransaction(function () use ($commande): void {
            if (!$this->commandeLifecycle->can($commande, 'annuler_commande')) {
                throw new \RuntimeException('Transition annuler_commande impossible pour ce statut.');
            }

            foreach ($commande->getLignesCommande() as $ligne) {
                $produit = $ligne->getProduit();
                $produit->setQuantite(1);
            }
            if ($commande->getCreneau() !== null) {
                $this->creneauManager->libererCreneau($commande->getCreneau(), $commande);
            }
            $this->commandeLifecycle->apply($commande, 'annuler_commande');
            $this->em->flush();
        });
    }

    private function mapCommandeProfil(ProfilUtilisateur $profil): CommandeProfilEnum
    {
        return match ($profil) {
            ProfilUtilisateur::DMAX => CommandeProfilEnum::DMAX,
            ProfilUtilisateur::PARTENAIRE => CommandeProfilEnum::PARTENAIRE,
            ProfilUtilisateur::TELETRAVAILLEUR => CommandeProfilEnum::TELETRAVAILLEUR,
            default => CommandeProfilEnum::AGENT,
        };
    }

    private function assertJourneePleineRule(Creneau $creneau): void
    {
        $jour = $creneau->getJourLivraison();
        if ($jour === null || $this->jourLivraisonRepository === null) {
            return;
        }

        $blockingDay = $this->jourLivraisonRepository->findPremierJourNonPleinAvant($jour->getDate());
        if ($blockingDay === null) {
            return;
        }

        throw new JourLivraisonNonPleinException(sprintf(
            'La journée du %s doit être complète avant de pouvoir choisir cette date.',
            $blockingDay->getDate()->format('d/m/Y'),
        ));
    }
}
