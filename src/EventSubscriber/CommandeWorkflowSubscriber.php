<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\Commande;
use App\Service\RgpdPurgeService;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Workflow\Event\Event;

final class CommandeWorkflowSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly RgpdPurgeService $purgeService,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'workflow.commande_lifecycle.transition.acter_retrait' => 'onActerRetrait',
            'workflow.commande_lifecycle.transition.annuler_commande' => 'onAnnulerCommande',
        ];
    }

    public function onActerRetrait(Event $event): void
    {
        $this->purgeContactData($event);
    }

    public function onAnnulerCommande(Event $event): void
    {
        $this->purgeContactData($event);
    }

    private function purgeContactData(Event $event): void
    {
        $subject = $event->getSubject();

        if (!($subject instanceof Commande)) {
            return;
        }

        $this->purgeService->anonymizeCommande($subject);
        $this->logger->info('Purge RGPD appliquée à la commande.', ['commande_id' => $subject->getId()]);
    }
}
