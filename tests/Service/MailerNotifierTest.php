<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Commande;
use App\Entity\CommandeContactTmp;
use App\Service\MailerNotifier;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

final class MailerNotifierTest extends TestCase
{
    public function testSendValidationEmailSendsMessageWhenRecipientExists(): void
    {
        $commande = $this->buildCommandeWithEmail('agent@example.test');

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::once())
            ->method('send')
            ->with(self::callback(static function (Email $email): bool {
                return $email->getSubject() === 'Commande #0 validée'
                    && $email->getTo()[0]->getAddress() === 'agent@example.test';
            }));

        $service = new MailerNotifier($mailer);
        $service->sendValidationEmail($commande);
    }

    public function testSendRefusalOrCancellationEmailSkipsWhenNoRecipient(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::never())->method('send');

        $service = new MailerNotifier($mailer);
        $service->sendRefusalOrCancellationEmail(new Commande());
    }

    private function buildCommandeWithEmail(string $email): Commande
    {
        $commande = new Commande();
        $contact = (new CommandeContactTmp())
            ->setCommande($commande)
            ->setEmail($email)
            ->setTelephone('0600000000');

        return $commande->setCommandeContactTmp($contact);
    }
}
