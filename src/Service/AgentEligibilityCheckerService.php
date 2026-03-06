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
                    "Vous n'êtes pas présent(e) dans la base des télétravailleurs.",
                );
            }

            return;
        }

        if (!$this->agentEligibleRepository->existsByNumeroAgent($numeroAgent)) {
            throw new \RuntimeException(
                'Votre numéro d\'agent n\'est pas reconnu.',
            );
        }
    }
}
