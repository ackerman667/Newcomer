<?php

namespace App\Command;

use App\Repository\PasswordResetTokenRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'cleanerpassword',
    description: 'Supprimer les entrées (éxpiré) de la table PasswordResetToken ',
)]
class CleanPasswordTokenCommand extends Command
{
    
    private PasswordResetTokenRepository $repository;

    public function __construct(PasswordResetTokenRepository $repository)
    {
        parent::__construct();
        $this->repository = $repository;
    }

    protected function configure(): void
    {
        $this
        ->setDescription('Supprimer les entrées (éxpiré) de la table PasswordResetToken.')
        ->setHelp('Supprimer les entrées (éxpiré) de la table PasswordResetToken');
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $deletedCount = $this->repository->deleteExpiredData();
        $io->success("$deletedCount supprimée(s).");

        return Command::SUCCESS;
    }
}
