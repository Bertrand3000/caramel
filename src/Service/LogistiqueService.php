<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Commande;
use App\Entity\JourLivraison;
use App\Enum\CommandeStatutEnum;
use App\Interface\LogistiqueServiceInterface;
use App\Repository\CommandeRepository;
use App\Repository\JourLivraisonRepository;
use Doctrine\ORM\EntityManagerInterface;
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
        if ($commande->getStatut() !== CommandeStatutEnum::VALIDEE) {
            throw new \LogicException(sprintf(
                'Commande #%d : transition impossible depuis le statut « %s ».',
                $commande->getId(),
                $commande->getStatut()->value,
            ));
        }

        $workflow = $this->workflowRegistry->get($commande, 'commande_lifecycle');
        $workflow->apply($commande, 'demarrer_preparation');
        $this->entityManager->flush();
    }

    public function markAsPrete(Commande $commande): void
    {
        if ($commande->getStatut() !== CommandeStatutEnum::EN_PREPARATION) {
            throw new \LogicException(sprintf(
                'Commande #%d : transition impossible depuis le statut « %s ».',
                $commande->getId(),
                $commande->getStatut()->value,
            ));
        }

        $workflow = $this->workflowRegistry->get($commande, 'commande_lifecycle');
        $workflow->apply($commande, 'terminer_preparation');
        $this->entityManager->flush();
    }

    public function markAsValidee(Commande $commande): void
    {
        if ($commande->getStatut() !== CommandeStatutEnum::EN_PREPARATION) {
            throw new \LogicException(sprintf(
                'Commande #%d : transition impossible depuis le statut « %s ».',
                $commande->getId(),
                $commande->getStatut()->value,
            ));
        }

        $workflow = $this->workflowRegistry->get($commande, 'commande_lifecycle');
        $workflow->apply($commande, 'annuler_preparation');
        $this->entityManager->flush();
    }

    public function markRevenirEnPreparation(Commande $commande): void
    {
        if ($commande->getStatut() !== CommandeStatutEnum::PRETE) {
            throw new \LogicException(sprintf(
                'Commande #%d : transition impossible depuis le statut « %s ».',
                $commande->getId(),
                $commande->getStatut()->value,
            ));
        }

        $workflow = $this->workflowRegistry->get($commande, 'commande_lifecycle');
        $workflow->apply($commande, 'revenir_en_preparation');
        $this->entityManager->flush();
    }
}
