<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\CommandeRepository;
use App\Service\PurgeServiceInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:rgpd:purge-orphans', description: 'Purge les contacts temporaires restants sur commandes retirées/annulées.')]
final class RgpdPurgeCommand extends Command
{
    public function __construct(
        private readonly CommandeRepository $commandeRepository,
        private readonly PurgeServiceInterface $purgeService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $commandes = $this->commandeRepository->findRetireeOuAnnuleeWithContactTmp();

        foreach ($commandes as $commande) {
            $this->purgeService->anonymizeCommande($commande);
        }

        $io->success(sprintf('%d commande(s) purgée(s).', count($commandes)));

        return Command::SUCCESS;
    }
}
