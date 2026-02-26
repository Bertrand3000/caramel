<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\Commande;
use App\Interface\MailerNotifierInterface;
use App\Service\PurgeServiceInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Workflow\Event\Event;

final class CommandeWorkflowSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly PurgeServiceInterface $purgeService,
        private readonly MailerNotifierInterface $mailerNotifier,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'workflow.commande_lifecycle.transition.acter_retrait' => 'onActerRetrait',
            'workflow.commande_lifecycle.transition.valider' => 'onValiderCommande',
            'workflow.commande_lifecycle.transition.annuler_commande' => 'onAnnulerCommande',
        ];
    }

    public function onValiderCommande(Event $event): void
    {
        $commande = $this->extractCommande($event);
        if ($commande === null) {
            return;
        }

        $this->mailerNotifier->sendValidationEmail($commande);
    }

    public function onActerRetrait(Event $event): void
    {
        $this->purgeContactData($event);
    }

    public function onAnnulerCommande(Event $event): void
    {
        $commande = $this->extractCommande($event);
        if ($commande !== null) {
            $this->mailerNotifier->sendRefusalOrCancellationEmail($commande);
        }

        $this->purgeContactData($event);
    }

    private function purgeContactData(Event $event): void
    {
        $subject = $this->extractCommande($event);
        if ($subject === null) {
            return;
        }

        $this->purgeService->anonymizeCommande($subject);
        $this->logger->info('Purge RGPD appliquée à la commande.', ['commande_id' => $subject->getId()]);
    }

    private function extractCommande(Event $event): ?Commande
    {
        $subject = $event->getSubject();

        return $subject instanceof Commande ? $subject : null;
    }
}
