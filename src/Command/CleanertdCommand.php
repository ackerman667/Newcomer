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
    description: 'Supprimer les entrées (éxpiré) de la table temporary_data ',
)]


/**
 * @brief Commande Symfony pour nettoyer les entrées expirées de la table `temporary_data`.
 *
 * Cette commande supprime toutes les entrées expirées de la base de données en
 * interagissant avec le dépôt `TemporaryDataRepository`. dans le depot il y a une fonction qui permet de recuperer toute les entrées qui ont expirées
 *
 * @command cleanertd
 *
 * @details
 * - Utilise la méthode `deleteExpiredData` du dépôt pour effectuer la suppression.
 * - Fournit un retour d'information via la console avec le nombre d'entrées supprimées.
 * - Peut être planifiée via un gestionnaire de tâches .
 */

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
            ->setDescription('Supprimer les entrées (éxpiré) de la table temporary_data.')
            ->setHelp('Supprimer les entrées (éxpiré) de la table temporary_data');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Supprimer les données expirées
        $deletedCount = $this->repository->deleteExpiredData();
        $io->success("$deletedCount supprimée(s).");

        return Command::SUCCESS;
    }
}
