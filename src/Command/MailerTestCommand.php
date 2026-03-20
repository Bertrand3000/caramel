<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Commande;
use App\Entity\CommandeContactTmp;
use App\Entity\Creneau;
use App\Entity\Utilisateur;
use App\Enum\CommandeProfilEnum;
use App\Enum\CommandeStatutEnum;
use App\Interface\MailerNotifierInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:mailer:test',
    description: 'Envoie un email de test (validation ou refus) à une adresse donnée.',
)]
final class MailerTestCommand extends Command
{
    public function __construct(
        private readonly MailerNotifierInterface $mailerNotifier,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'Adresse email du destinataire')
            ->addOption('type', 't', InputOption::VALUE_REQUIRED, 'Type d\'email : validation ou refus', 'validation')
            ->addOption('no-send', null, InputOption::VALUE_NONE, 'Ne pas envoyer (dry-run)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = (string) $input->getArgument('email');
        $type = (string) $input->getOption('type');
        $noSend = $input->getOption('no-send');

        if (!in_array($type, ['validation', 'refus'], true)) {
            $io->error('Le type doit être "validation" ou "refus".');

            return Command::FAILURE;
        }

        if ($noSend) {
            $io->note('Mode dry-run : aucun email ne sera envoyé.');
        }

        $commande = $this->createTestCommande($email);

        $io->section('Détails de la commande test');
        $io->definitionList(
            ['Email destinataire' => $email],
            ['Type d\'email' => $type],
            ['Nom' => $commande->getNom()],
            ['Prénom' => $commande->getPrenom()],
            ['Numéro agent' => $commande->getNumeroAgent() ?? '—'],
            ['Statut' => $commande->getStatut()->value],
        );

        try {
            if ($noSend) {
                $io->success('Dry-run terminé: aucun email envoyé, aucune donnée persistée.');

                return Command::SUCCESS;
            }

            if ($type === 'validation') {
                $this->mailerNotifier->notifyCommandeValidee($commande);
                $io->success('Email de validation envoyé avec succès !');
            } else {
                $this->mailerNotifier->notifyCommandeRefusee($commande);
                $io->success('Email de refus envoyé avec succès !');
            }

            return Command::SUCCESS;
        } catch (\Throwable $exception) {
            $io->error(sprintf('Erreur lors de l\'envoi : %s', $exception->getMessage()));

            return Command::FAILURE;
        }
    }

    private function createTestCommande(string $email): Commande
    {
        $utilisateur = (new Utilisateur())
            ->setLogin('test-mailer@caramel.local')
            ->setPassword('dummy')
            ->setRoles(['ROLE_AGENT']);

        $creneau = (new Creneau())
            ->setDateHeure(new \DateTimeImmutable('tomorrow 10:00:00'))
            ->setHeureDebut(new \DateTime('10:00:00'))
            ->setHeureFin(new \DateTime('10:30:00'))
            ->setCapaciteMax(10);

        $contact = (new CommandeContactTmp())
            ->setNomGrh('TEST')
            ->setPrenomGrh('Mail')
            ->setEmail($email)
            ->setTelephone('0123456789')
            ->setImportBatchId('mailer_test_command')
            ->setImportedAt(new \DateTimeImmutable());

        $sessionId = 'mailer_test_'.bin2hex(random_bytes(4));
        $commande = (new Commande())
            ->setUtilisateur($utilisateur)
            ->setSessionId($sessionId)
            ->setNumeroAgent('99999')
            ->setNom('TEST')
            ->setPrenom('Mail')
            ->setCreneau($creneau)
            ->setStatut(CommandeStatutEnum::EN_ATTENTE_VALIDATION)
            ->setProfilCommande(CommandeProfilEnum::AGENT)
            ->setDateValidation(new \DateTime())
            ->setCommandeContactTmp($contact);

        $contact->setCommande($commande);

        return $commande;
    }
}
