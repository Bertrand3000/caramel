<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\TeletravailleurListe;
use App\Repository\TeletravailleurListeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:teletravailleur:add', description: 'Ajoute un numéro d\'agent à la liste des télétravailleurs.')]
final class AddTeletravailleurCommand extends Command
{
    public function __construct(
        private readonly TeletravailleurListeRepository $teletravailleurRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('numero', InputArgument::REQUIRED, 'Numéro d\'agent (5 chiffres)')
            ->addOption('nom', null, InputOption::VALUE_OPTIONAL, 'Nom de l\'agent')
            ->addOption('prenom', null, InputOption::VALUE_OPTIONAL, 'Prénom de l\'agent');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $numero = $input->getArgument('numero');

        if (!preg_match('/^\d{5}$/', $numero)) {
            $io->error('Le numéro d\'agent doit contenir exactement 5 chiffres.');

            return Command::FAILURE;
        }

        $existing = $this->teletravailleurRepository->findOneBy(['numeroAgent' => $numero]);
        if ($existing !== null) {
            $io->error(sprintf('Le numéro d\'agent %s existe déjà dans la liste.', $numero));

            return Command::FAILURE;
        }

        $teletravailleur = new TeletravailleurListe();
        $teletravailleur->setNumeroAgent($numero);

        $nom = $input->getOption('nom');
        $prenom = $input->getOption('prenom');

        if ($nom !== null) {
            $teletravailleur->setNom($nom);
        }
        if ($prenom !== null) {
            $teletravailleur->setPrenom($prenom);
        }

        $this->entityManager->persist($teletravailleur);
        $this->entityManager->flush();

        $io->success(sprintf('Le numéro d\'agent %s a été ajouté à la liste des télétravailleurs.', $numero));

        return Command::SUCCESS;
    }
}
