<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Commande;
use App\Entity\JourLivraison;
use App\Interface\LogistiqueServiceInterface;
use App\Repository\CommandeRepository;
use App\Repository\JourLivraisonRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Workflow\Exception\NotEnabledTransitionException;
use Symfony\Component\Workflow\Registry;

final class LogistiqueService implements LogistiqueServiceInterface
{
    public function __construct(
        private readonly CommandeRepository $commandeRepository,
        private readonly JourLivraisonRepository $jourLivraisonRepository,
        private readonly Registry $workflowRegistry,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function findTodayReadyOrders(): array
    {
        return $this->commandeRepository->findPretesForTodayOrderedBySlot();
    }

    public function validateRetrait(Commande $commande): void
    {
        $workflow = $this->workflowRegistry->get($commande, 'commande_lifecycle');

        if (!$workflow->can($commande, 'acter_retrait')) {
            return;
        }

        $workflow->apply($commande, 'acter_retrait');
        $this->entityManager->flush();
    }

    public function findNextDeliveryDay(): ?JourLivraison
    {
        return $this->jourLivraisonRepository->findNextActiveDeliveryDay();
    }

    /** @return list<Commande> */
    public function findOrdersForPreparation(JourLivraison $jour): array
    {
        return $this->commandeRepository->findForPreparation($jour);
    }

    /** @return list<Commande> */
    public function findAllOrdersForLogistique(JourLivraison $jour): array
    {
        return $this->commandeRepository->findAllForLogistique($jour);
    }

    /** @return list<Commande> */
    public function findAgentOrdersForLogistique(JourLivraison $jour): array
    {
        return $this->commandeRepository->findAgentsForLogistique($jour);
    }

    /** @return list<Commande> */
    public function findFilteredOrdersForLogistique(JourLivraison $jour, ?int $filtreId = null, ?string $filtreNumeroAgent = null, ?int $filtreCreneauId = null): array
    {
        return $this->commandeRepository->findFilteredForLogistique($jour, $filtreId, $filtreNumeroAgent, $filtreCreneauId);
    }

    /** @return list<\App\Entity\Creneau> */
    public function findCreneauxForLogistique(JourLivraison $jour): array
    {
        return $this->commandeRepository->findCreneauxForJourLivraison($jour);
    }

    public function findRecapMateriel(JourLivraison $jour): array
    {
        $lignes = $this->commandeRepository->findLignesForRecapMateriel($jour);

        // Groupement par étage puis par porte
        $grouped = [];
        foreach ($lignes as $item) {
            $produit = $item['produit'];
            $commande = $item['commande'];
            $ligne = $item['ligne'];

            $etage = $produit->getEtage();
            $porte = $produit->getPorte();

            $agent = trim(sprintf('%s %s', $commande->getPrenom() ?? '', $commande->getNom() ?? ''));
            if ($commande->getNumeroAgent()) {
                $agent .= sprintf(' (#%s)', $commande->getNumeroAgent());
            }
            if ($agent === '') {
                $agent = sprintf('Commande #%d', $commande->getId());
            }

            $grouped[$etage][$porte][] = [
                'produit' => $produit,
                'quantite' => $ligne->getQuantite(),
                'commandeId' => $commande->getId(),
                'agent' => $agent,
            ];
        }

        // Tri naturel des étages (1, 2, 10 au lieu de 1, 10, 2)
        $this->natKsort($grouped);

        // Tri naturel des portes dans chaque étage
        foreach ($grouped as $etage => $portes) {
            $this->natKsort($grouped[$etage]);
        }

        return $grouped;
    }

    /**
     * Tri naturel des clés d'un tableau par référence.
     * Utilise natsort pour un tri correct des valeurs numériques (1, 2, 10).
     */
    private function natKsort(array &$array): void
    {
        if (empty($array)) {
            return;
        }

        $keys = array_keys($array);
        natsort($keys);
        $keys = array_flip(array_values($keys));

        $sorted = [];
        foreach ($keys as $key => $value) {
            $sorted[$key] = $array[$key];
        }

        $array = $sorted;
    }

    public function markEnPreparation(Commande $commande): void
    {
        $workflow = $this->workflowRegistry->get($commande, 'commande_lifecycle');

        try {
            $workflow->apply($commande, 'demarrer_preparation');
            $this->entityManager->flush();
        } catch (NotEnabledTransitionException $e) {
            throw new \LogicException(sprintf(
                'Commande #%d : transition impossible (%s).',
                $commande->getId(),
                $e->getTransitionName(),
            ));
        }
    }

    public function markAsPrete(Commande $commande): void
    {
        $workflow = $this->workflowRegistry->get($commande, 'commande_lifecycle');

        try {
            $workflow->apply($commande, 'terminer_preparation');
            $this->entityManager->flush();
        } catch (NotEnabledTransitionException $e) {
            throw new \LogicException(sprintf(
                'Commande #%d : transition impossible (%s).',
                $commande->getId(),
                $e->getTransitionName(),
            ));
        }
    }

    public function markAsValidee(Commande $commande): void
    {
        $workflow = $this->workflowRegistry->get($commande, 'commande_lifecycle');

        try {
            $workflow->apply($commande, 'annuler_preparation');
            $this->entityManager->flush();
        } catch (NotEnabledTransitionException $e) {
            throw new \LogicException(sprintf(
                'Commande #%d : transition impossible (%s).',
                $commande->getId(),
                $e->getTransitionName(),
            ));
        }
    }

    public function markAsEnAttenteValidation(Commande $commande): void
    {
        $workflow = $this->workflowRegistry->get($commande, 'commande_lifecycle');

        try {
            $workflow->apply($commande, 'retour_en_attente_validation');
            $this->entityManager->flush();
        } catch (NotEnabledTransitionException $e) {
            throw new \LogicException(sprintf(
                'Commande #%d : transition impossible (%s).',
                $commande->getId(),
                $e->getTransitionName(),
            ));
        }
    }

    public function markRevenirEnPreparation(Commande $commande): void
    {
        $workflow = $this->workflowRegistry->get($commande, 'commande_lifecycle');

        try {
            $workflow->apply($commande, 'revenir_en_preparation');
            $this->entityManager->flush();
        } catch (NotEnabledTransitionException $e) {
            throw new \LogicException(sprintf(
                'Commande #%d : transition impossible (%s).',
                $commande->getId(),
                $e->getTransitionName(),
            ));
        }
    }

    public function markRevenirPrete(Commande $commande): void
    {
        $workflow = $this->workflowRegistry->get($commande, 'commande_lifecycle');

        try {
            $workflow->apply($commande, 'annuler_retrait');
            $this->entityManager->flush();
        } catch (NotEnabledTransitionException $e) {
            throw new \LogicException(sprintf(
                'Commande #%d : transition impossible (%s).',
                $commande->getId(),
                $e->getTransitionName(),
            ));
        }
    }
}
