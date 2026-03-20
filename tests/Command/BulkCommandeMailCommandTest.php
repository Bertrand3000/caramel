<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\BulkCommandeMailCommand;
use App\Entity\Commande;
use App\Entity\CommandeContactTmp;
use App\Entity\JourLivraison;
use App\Enum\CommandeStatutEnum;
use App\Repository\CommandeRepository;
use App\Repository\JourLivraisonRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;

final class BulkCommandeMailCommandTest extends TestCase
{
    public function testExecuteRequiresStatusOrNextDeliveryDayOrTestEmail(): void
    {
        $command = new BulkCommandeMailCommand(
            $this->createMock(CommandeRepository::class),
            $this->createMock(JourLivraisonRepository::class),
            $this->createMock(MailerInterface::class),
        );

        $bodyFile = $this->createBodyFile('contenu');
        $tester = new CommandTester($command);
        $exitCode = $tester->execute([
            '--subject' => 'Sujet test',
            '--body-file' => $bodyFile,
        ]);

        self::assertSame(Command::INVALID, $exitCode);
        self::assertStringContainsString('--status=en_attente|validee', $tester->getDisplay());
    }

    public function testExecuteNextDeliveryDayWarnsWhenNoDay(): void
    {
        $commandeRepository = $this->createMock(CommandeRepository::class);
        $commandeRepository->expects(self::never())->method('findByJourLivraisonWithEmail');

        $jourLivraisonRepository = $this->createMock(JourLivraisonRepository::class);
        $jourLivraisonRepository->expects(self::once())
            ->method('findNextActiveDeliveryDay')
            ->willReturn(null);

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::never())->method('send');

        $command = new BulkCommandeMailCommand($commandeRepository, $jourLivraisonRepository, $mailer);
        $bodyFile = $this->createBodyFile('contenu');
        $tester = new CommandTester($command);
        $exitCode = $tester->execute([
            '--next-delivery-day' => true,
            '--subject' => 'Sujet test',
            '--body-file' => $bodyFile,
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('Aucun prochain jour de livraison actif trouvé.', $tester->getDisplay());
    }

    public function testExecuteNextDeliveryDaySendsEmailToEligibleOrders(): void
    {
        $jour = (new JourLivraison())->setDate(new \DateTimeImmutable('2026-03-21'));

        $commande = new Commande();
        $contact = (new CommandeContactTmp())
            ->setCommande($commande)
            ->setEmail('user@example.test');
        $commande->setCommandeContactTmp($contact);

        $commandeRepository = $this->createMock(CommandeRepository::class);
        $commandeRepository->expects(self::once())
            ->method('findByJourLivraisonWithEmail')
            ->with($jour, CommandeStatutEnum::VALIDEE)
            ->willReturn([$commande]);

        $jourLivraisonRepository = $this->createMock(JourLivraisonRepository::class);
        $jourLivraisonRepository->expects(self::once())
            ->method('findNextActiveDeliveryDay')
            ->willReturn($jour);

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::once())
            ->method('send')
            ->with(self::callback(function (RawMessage $message): bool {
                if (!$message instanceof Email) {
                    return false;
                }

                return $message->getTo()[0]->getAddress() === 'user@example.test';
            }));

        $command = new BulkCommandeMailCommand($commandeRepository, $jourLivraisonRepository, $mailer);
        $bodyFile = $this->createBodyFile("Bonjour,\nMessage.");
        $tester = new CommandTester($command);
        $exitCode = $tester->execute([
            '--next-delivery-day' => true,
            '--status' => 'validee',
            '--subject' => 'Sujet test',
            '--body-file' => $bodyFile,
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('Nb commandes éligibles', $tester->getDisplay());
    }

    private function createBodyFile(string $content): string
    {
        $path = tempnam(sys_get_temp_dir(), 'bulk_mail_');
        self::assertNotFalse($path);
        file_put_contents($path, $content);

        return $path;
    }
}
