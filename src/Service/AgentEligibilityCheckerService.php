<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Utilisateur;
use App\Interface\AgentEligibilityCheckerInterface;
use App\Repository\AgentEligibleRepository;
use App\Repository\TeletravailleurListeRepository;

final class AgentEligibilityCheckerService implements AgentEligibilityCheckerInterface
{
    public function __construct(
        private readonly TeletravailleurListeRepository $teletravailleurListeRepository,
        private readonly AgentEligibleRepository $agentEligibleRepository,
    ) {
    }

    public function assertAllowed(Utilisateur $utilisateur, string $numeroAgent): void
    {
        if (in_array('ROLE_TELETRAVAILLEUR', $utilisateur->getRoles(), true)) {
            if (!$this->teletravailleurListeRepository->existsByNumeroAgent($numeroAgent)) {
                throw new \RuntimeException(
                    'Votre numero d agent est absent de la liste teletravailleurs autorisee pour ce compte.',
                );
            }

            return;
        }

        if (!$this->agentEligibleRepository->existsByNumeroAgent($numeroAgent)) {
            throw new \RuntimeException(
                'Votre numero d agent est absent de la liste des agents autorises pour ce compte.',
            );
        }
    }
}
