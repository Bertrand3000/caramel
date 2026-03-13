<?php

declare(strict_types=1);

namespace App\Command;

use App\Enum\CommandeStatutEnum;
use App\Repository\CommandeRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

#[AsCommand(
    name: 'app:commandes:mail-bulk',
    description: 'Envoie un email en masse aux commandes en attente ou validées avec email renseigné.',
)]
final class BulkCommandeMailCommand extends Command
{
    public function __construct(
        private readonly CommandeRepository $commandeRepository,
        private readonly MailerInterface $mailer,
        private readonly string $fromEmail = 'noreply@caramel.local',
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('status', null, InputOption::VALUE_REQUIRED, 'Cible: en_attente ou validee')
            ->addOption('subject', null, InputOption::VALUE_REQUIRED, 'Sujet du mail')
            ->addOption('body-file', null, InputOption::VALUE_REQUIRED, 'Chemin du fichier contenant le corps du mail')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Simulation sans envoi');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $statusOption = trim((string) $input->getOption('status'));
        $subject = trim((string) $input->getOption('subject'));
        $bodyFile = trim((string) $input->getOption('body-file'));
        $dryRun = (bool) $input->getOption('dry-run');

        $status = $this->resolveStatus($statusOption);
        if ($status === null || $subject === '' || $bodyFile === '') {
            $io->error('Options requises: --status=en_attente|validee --subject="..." --body-file=/chemin/fichier.txt');

            return Command::INVALID;
        }

        if (!is_file($bodyFile) || !is_readable($bodyFile)) {
            $io->error(sprintf('Fichier introuvable ou illisible: %s', $bodyFile));

            return Command::FAILURE;
        }

        $body = file_get_contents($bodyFile);
        if ($body === false || trim($body) === '') {
            $io->error('Le fichier de contenu est vide ou illisible.');

            return Command::FAILURE;
        }

        $commandes = $this->commandeRepository->findByStatutWithEmail($status);
        if ($commandes === []) {
            $io->warning('Aucune commande éligible avec email renseigné pour ce statut.');

            return Command::SUCCESS;
        }

        $io->section('Préparation envoi');
        $io->definitionList(
            ['Statut ciblé' => $status->value],
            ['Sujet' => $subject],
            ['Fichier contenu' => $bodyFile],
            ['Mode' => $dryRun ? 'DRY-RUN (sans envoi)' : 'ENVOI RÉEL'],
            ['Nb commandes éligibles' => (string) count($commandes)],
        );

        $sent = 0;
        foreach ($commandes as $commande) {
            $email = $commande->getCommandeContactTmp()?->getEmail();
            if ($email === null || trim($email) === '') {
                continue;
            }

            if (!$dryRun) {
                $this->mailer->send(
                    (new Email())
                        ->from($this->fromEmail)
                        ->to($email)
                        ->subject($subject)
                        ->text($body),
                );
            }

            ++$sent;
        }

        $io->success(sprintf(
            $dryRun ? 'Simulation terminée: %d email(s) seraient envoyés.' : 'Envoi terminé: %d email(s) envoyés.',
            $sent,
        ));

        return Command::SUCCESS;
    }

    private function resolveStatus(string $value): ?CommandeStatutEnum
    {
        return match ($value) {
            'en_attente', 'en_attente_validation' => CommandeStatutEnum::EN_ATTENTE_VALIDATION,
            'validee' => CommandeStatutEnum::VALIDEE,
            default => null,
        };
    }
}
