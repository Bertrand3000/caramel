<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Commande;
use App\Interface\MailerNotifierInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;

final class MailerNotifier implements MailerNotifierInterface
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly string $fromEmail = 'noreply@caramel.local',
    ) {
    }

    public function notifyCommandeValidee(Commande $commande): void
    {
        $recipient = $this->resolveRecipient($commande);
        if ($recipient === null) {
            return;
        }

        $this->mailer->send(
            (new TemplatedEmail())
                ->from($this->fromEmail)
                ->to($recipient)
                ->subject('Votre commande CARAMEL a été validée')
                ->htmlTemplate('emails/commande_validee.html.twig')
                ->context(['commande' => $commande]),
        );
    }

    public function notifyCommandeRefusee(Commande $commande): void
    {
        $recipient = $this->resolveRecipient($commande);
        if ($recipient === null) {
            return;
        }

        $this->mailer->send(
            (new TemplatedEmail())
                ->from($this->fromEmail)
                ->to($recipient)
                ->subject('Votre commande CARAMEL n\'a pas pu être acceptée')
                ->htmlTemplate('emails/commande_refusee.html.twig')
                ->context(['commande' => $commande]),
        );
    }

    public function sendValidationEmail(Commande $commande): void
    {
        $this->notifyCommandeValidee($commande);
    }

    public function sendRefusalOrCancellationEmail(Commande $commande): void
    {
        $this->notifyCommandeRefusee($commande);
    }

    private function resolveRecipient(Commande $commande): ?string
    {
        $contact = $commande->getCommandeContactTmp();

        return $contact?->getEmail();
    }
}
