<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Commande;
use App\Interface\MailerNotifierInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

final class MailerNotifier implements MailerNotifierInterface
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly string $fromEmail = 'noreply@caramel.local',
    ) {
    }

    public function sendValidationEmail(Commande $commande): void
    {
        $recipient = $this->resolveRecipient($commande);
        if ($recipient === null) {
            return;
        }

        $this->mailer->send(
            (new Email())
                ->from($this->fromEmail)
                ->to($recipient)
                ->subject(sprintf('Commande #%d validée', (int) $commande->getId()))
                ->text('Votre commande a été validée. Merci de vous présenter au créneau prévu.'),
        );
    }

    public function sendRefusalOrCancellationEmail(Commande $commande): void
    {
        $recipient = $this->resolveRecipient($commande);
        if ($recipient === null) {
            return;
        }

        $this->mailer->send(
            (new Email())
                ->from($this->fromEmail)
                ->to($recipient)
                ->subject(sprintf('Commande #%d refusée ou annulée', (int) $commande->getId()))
                ->text('Votre commande a été refusée ou annulée. Les produits ont été remis à disposition.'),
        );
    }

    private function resolveRecipient(Commande $commande): ?string
    {
        $contact = $commande->getCommandeContactTmp();

        return $contact?->getEmail();
    }
}
