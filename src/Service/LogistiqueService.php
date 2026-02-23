<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Commande;
use App\Interface\LogistiqueServiceInterface;
use App\Repository\CommandeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Workflow\Registry;

final class LogistiqueService implements LogistiqueServiceInterface
{
    public function __construct(
        private readonly CommandeRepository $commandeRepository,
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
}
