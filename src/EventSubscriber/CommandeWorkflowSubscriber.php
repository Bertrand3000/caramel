<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Workflow\Event\Event;

final class CommandeWorkflowSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'workflow.commande_lifecycle.transition.acter_retrait' => 'onTransition',
            'workflow.commande_lifecycle.transition.annuler_commande' => 'onTransition',
        ];
    }

    public function onTransition(Event $event): void
    {
        $this->logger->info('Transition effectuée');
    }
}
