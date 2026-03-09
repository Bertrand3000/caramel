<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\ProduitRepository;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:produit:import-vnc-xlsx',
    description: 'Met à jour la VNC des produits depuis un XLSX (A=gestion, B=annee, C=type, D=chrono, F=code VNC).',
)]
final class ImportVncFromXlsxCommand extends Command
{
    public function __construct(
        private readonly ProduitRepository $produitRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('fichier', InputArgument::REQUIRED, 'Chemin du fichier XLSX à importer')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Simule les changements sans écrire en base');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $filePath = (string) $input->getArgument('fichier');
        $dryRun = (bool) $input->getOption('dry-run');
        if (!is_file($filePath)) {
            $io->error(sprintf('Fichier introuvable : %s', $filePath));

            return Command::FAILURE;
        }

        $sheet = IOFactory::load($filePath)->getActiveSheet();
        $highestRow = $sheet->getHighestDataRow();
        $read = 0;
        $matched = 0;
        $updated = 0;
        $skipped = 0;

        for ($row = 1; $row <= $highestRow; ++$row) {
            ++$read;
            $gestion = $this->extractIntCell((string) $sheet->getCell('A'.$row)->getFormattedValue());
            $annee = $this->extractIntCell((string) $sheet->getCell('B'.$row)->getFormattedValue());
            $type = $this->extractIntCell((string) $sheet->getCell('C'.$row)->getFormattedValue());
            $chrono = $this->extractIntCell((string) $sheet->getCell('D'.$row)->getFormattedValue());
            $fValue = trim((string) $sheet->getCell('F'.$row)->getFormattedValue());

            $vnc = match ($fValue) {
                '1' => '=0',
                '2' => '>0',
                default => null,
            };

            if ($gestion === null || $annee === null || $type === null || $chrono === null || $vnc === null) {
                ++$skipped;
                continue;
            }

            $produit = $this->produitRepository->findOneBy([
                'gestion' => $gestion,
                'annee' => $annee,
                'type' => $type,
                'chrono' => $chrono,
            ]);
            if ($produit === null) {
                ++$skipped;
                continue;
            }

            ++$matched;
            if ($produit->getVnc() === $vnc) {
                continue;
            }

            ++$updated;
            $produit->setVnc($vnc);
        }

        if (!$dryRun) {
            $this->entityManager->flush();
        }

        $io->definitionList(
            ['Lignes lues' => (string) $read],
            ['Produits trouvés' => (string) $matched],
            ['Produits mis à jour' => (string) $updated],
            ['Lignes ignorées' => (string) $skipped],
            ['Mode' => $dryRun ? 'dry-run (aucune écriture)' : 'écriture en base'],
        );
        $io->success('Import VNC terminé.');

        return Command::SUCCESS;
    }

    private function extractIntCell(string $raw): ?int
    {
        $value = trim($raw);
        if (!preg_match('/^\d+$/', $value)) {
            return null;
        }

        return (int) $value;
    }
}
