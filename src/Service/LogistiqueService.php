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
