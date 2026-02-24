<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Commande;
use App\Entity\Creneau;
use App\Enum\CommandeStatutEnum;
use App\Enum\ProfilUtilisateur;
use App\Interface\CartManagerInterface;
use App\Interface\CheckoutServiceInterface;
use App\Interface\SlotManagerInterface;
use Doctrine\ORM\EntityManagerInterface;

class CheckoutService implements CheckoutServiceInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CartManagerInterface $cartManager,
        private readonly QuotaCheckerService $quotaChecker,
        private readonly SlotManagerInterface $creneauManager,
    ) {
    }

    public function confirmCommande(
        string $sessionId,
        Creneau $creneau,
        ProfilUtilisateur $profil,
        ?string $numeroAgent = null,
    ): Commande {
        return $this->em->wrapInTransaction(function () use ($sessionId, $creneau, $profil, $numeroAgent): Commande {
            $panier = $this->cartManager->getContents($sessionId);
            $totalQuantite = array_sum(array_column($panier, 'quantite'));
            if (!$this->quotaChecker->check($sessionId, $profil, $totalQuantite, $numeroAgent)) {
                throw new \RuntimeException('Quota d articles dépassé');
            }

            $commande = $this->cartManager->validateCart($sessionId);
            $this->creneauManager->reserverCreneau($creneau, $commande);
            if ($numeroAgent !== null && trim($numeroAgent) !== '') {
                $commande->setNumeroAgent(trim($numeroAgent));
            }
            $commande->setStatut(CommandeStatutEnum::EN_ATTENTE_VALIDATION);

            return $commande;
        });
    }

    public function checkQuota(string $sessionId, ProfilUtilisateur $profil, ?string $numeroAgent = null): bool
    {
        $panier = $this->cartManager->getContents($sessionId);

        return $this->quotaChecker->check(
            $sessionId,
            $profil,
            (int) array_sum(array_column($panier, 'quantite')),
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
            foreach ($commande->getLignesCommande() as $ligne) {
                $produit = $ligne->getProduit();
                $produit->setQuantite($produit->getQuantite() + $ligne->getQuantite());
            }
            if ($commande->getCreneau() !== null) {
                $this->creneauManager->libererCreneau($commande->getCreneau(), $commande);
            }
            $commande->setStatut(CommandeStatutEnum::ANNULEE);
            $this->em->flush();
        });
    }
}
