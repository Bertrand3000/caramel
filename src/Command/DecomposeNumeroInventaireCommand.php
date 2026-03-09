<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\ProduitRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:produit:decomposer-inventaire',
    description: 'Décompose les numéros d\'inventaire au format A.B.C.D vers gestion/annee/type/chrono.',
)]
final class DecomposeNumeroInventaireCommand extends Command
{
    private const BATCH_SIZE = 200;

    public function __construct(
        private readonly ProduitRepository $produitRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Affiche les changements sans les persister.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');
        $produits = $this->produitRepository->findAll();
        $scanned = 0;
        $parsed = 0;
        $updated = 0;

        foreach ($produits as $produit) {
            ++$scanned;
            $numero = $produit->getNumeroInventaire();
            if ($numero === null || $numero === '') {
                continue;
            }

            if (!preg_match('/^\s*(\d+)\.(\d+)\.(\d+)\.(\d+)\s*$/', $numero, $matches)) {
                continue;
            }

            ++$parsed;
            $gestion = (int) $matches[1];
            $annee = (int) $matches[2];
            $type = (int) $matches[3];
            $chrono = (int) $matches[4];

            if (
                $produit->getGestion() === $gestion
                && $produit->getAnnee() === $annee
                && $produit->getType() === $type
                && $produit->getChrono() === $chrono
            ) {
                continue;
            }

            ++$updated;
            $produit
                ->setGestion($gestion)
                ->setAnnee($annee)
                ->setType($type)
                ->setChrono($chrono);

            if (!$dryRun && $updated % self::BATCH_SIZE === 0) {
                $this->entityManager->flush();
            }
        }

        if (!$dryRun) {
            $this->entityManager->flush();
        }

        $io->definitionList(
            ['Produits scannés' => (string) $scanned],
            ['Numéros conformes (A.B.C.D)' => (string) $parsed],
            ['Produits mis à jour' => (string) $updated],
            ['Mode' => $dryRun ? 'dry-run (aucune écriture)' : 'écriture en base'],
        );
        $io->success('Traitement terminé.');

        return Command::SUCCESS;
    }
}
