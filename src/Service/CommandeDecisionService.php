<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Commande;
use App\Enum\CommandeStatutEnum;
use App\Interface\CheckoutServiceInterface;
use App\Interface\CommandeDecisionServiceInterface;
use App\Interface\MailerNotifierInterface;
use Doctrine\ORM\EntityManagerInterface;

final class CommandeDecisionService implements CommandeDecisionServiceInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly MailerNotifierInterface $mailerNotifier,
        private readonly CheckoutServiceInterface $checkoutService,
    ) {
    }

    public function apply(Commande $commande, CommandeStatutEnum $status): bool
    {
        // Vérifier l'email AVANT toute opération (la purge RGPD le supprimera)
        $contact = $commande->getCommandeContactTmp();
        $hasEmail = $contact?->getEmail() !== null && $contact?->getEmail() !== '';

        if ($status === CommandeStatutEnum::ANNULEE) {
            $this->checkoutService->annulerCommande($commande);
            // L'email de refus est envoyé par le workflow (CommandeWorkflowSubscriber)
            // La purge RGPD est aussi appliquée par le workflow
            // On retourne true si un email était disponible pour l'envoi
            return $hasEmail;
        }

        $commande->setStatut(CommandeStatutEnum::VALIDEE);
        $this->entityManager->flush();

        if (!$hasEmail) {
            return false;
        }

        $this->mailerNotifier->notifyCommandeValidee($commande);

        // Vider l'email après envoi (données GRH jetables)
        $contact?->setEmail(null);
        $this->entityManager->flush();

        return true;
    }
}
