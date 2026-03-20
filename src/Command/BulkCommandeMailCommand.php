<?php

declare(strict_types=1);

namespace App\Command;

use App\Enum\CommandeStatutEnum;
use App\Repository\CommandeRepository;
use App\Repository\JourLivraisonRepository;
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
    description: 'Envoie un email en masse aux commandes ciblées avec email renseigné.',
)]
final class BulkCommandeMailCommand extends Command
{
    public function __construct(
        private readonly CommandeRepository $commandeRepository,
        private readonly JourLivraisonRepository $jourLivraisonRepository,
        private readonly MailerInterface $mailer,
        private readonly string $fromEmail = 'noreply@caramel.local',
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('status', null, InputOption::VALUE_REQUIRED, 'Cible: en_attente ou validee')
            ->addOption('next-delivery-day', null, InputOption::VALUE_NONE, 'Cible le prochain jour de livraison actif')
            ->addOption('subject', null, InputOption::VALUE_REQUIRED, 'Sujet du mail')
            ->addOption('body-file', null, InputOption::VALUE_REQUIRED, 'Chemin du fichier contenant le corps du mail')
            ->addOption('test-email', null, InputOption::VALUE_REQUIRED, 'Mode test: adresse email unique destinataire')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Simulation sans envoi');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $statusOption = trim((string) $input->getOption('status'));
        $subject = trim((string) $input->getOption('subject'));
        $bodyFile = trim((string) $input->getOption('body-file'));
        $testEmail = trim((string) $input->getOption('test-email'));
        $dryRun = (bool) $input->getOption('dry-run');
        $nextDeliveryDay = (bool) $input->getOption('next-delivery-day');

        if ($subject === '' || $bodyFile === '') {
            $io->error('Options requises: --subject="..." --body-file=/chemin/fichier.txt');

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

        if ($testEmail !== '') {
            if (!filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
                $io->error(sprintf('Adresse email de test invalide: %s', $testEmail));

                return Command::INVALID;
            }

            $io->section('Préparation envoi (mode test-email)');
            $io->definitionList(
                ['Destinataire test' => $testEmail],
                ['Sujet' => $subject],
                ['Fichier contenu' => $bodyFile],
                ['Mode' => $dryRun ? 'DRY-RUN (sans envoi)' : 'ENVOI RÉEL'],
            );

            if (!$dryRun) {
                $this->mailer->send(
                    (new Email())
                        ->from($this->fromEmail)
                        ->to($testEmail)
                        ->subject($subject)
                        ->text($body),
                );
            }

            $io->success($dryRun
                ? sprintf('Simulation terminée: 1 email serait envoyé à %s.', $testEmail)
                : sprintf('Envoi terminé: 1 email envoyé à %s.', $testEmail),
            );

            return Command::SUCCESS;
        }

        $status = $this->resolveStatus($statusOption);
        if (!$nextDeliveryDay && $status === null) {
            $io->error('Mode bulk: --status=en_attente|validee est requis (ou utiliser --next-delivery-day, ou --test-email=adresse).');

            return Command::INVALID;
        }

        if ($nextDeliveryDay) {
            $jour = $this->jourLivraisonRepository->findNextActiveDeliveryDay();
            if ($jour === null) {
                $io->warning('Aucun prochain jour de livraison actif trouvé.');

                return Command::SUCCESS;
            }
            $commandes = $this->commandeRepository->findByJourLivraisonWithEmail($jour, $status);
            $targetDescription = sprintf(
                'jour=%s%s',
                $jour->getDate()->format('Y-m-d'),
                $status !== null ? sprintf(', statut=%s', $status->value) : ', statut!=annulee',
            );
        } else {
            $commandes = $this->commandeRepository->findByStatutWithEmail($status);
            $targetDescription = sprintf('statut=%s', $status->value);
        }

        if ($commandes === []) {
            $io->warning('Aucune commande éligible avec email renseigné pour la cible demandée.');

            return Command::SUCCESS;
        }

        $io->section('Préparation envoi');
        $io->definitionList(
            ['Cible' => $targetDescription],
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
