<?php 
namespace App\Command;

use App\Repository\TemporaryDataRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'cleanertd',
    description: 'Deletes expired rows from the temporary_data table',
)]
class CleanertdCommand extends Command
{
    private TemporaryDataRepository $repository;

    public function __construct(TemporaryDataRepository $repository)
    {
        parent::__construct();
        $this->repository = $repository;
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Deletes expired rows from the temporary_data table.')
            ->setHelp('This command allows you to delete rows from the temporary_data table that have expired.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Supprimer les données expirées
        $deletedCount = $this->repository->deleteExpiredData();
        $io->success("$deletedCount expired rows have been deleted.");

        return Command::SUCCESS;
    }
}
