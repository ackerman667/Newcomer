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

/**
 * @brief Commande Symfony pour nettoyer les entrées expirées de la table `PasswordResetToken`.
 *
 * Cette commande supprime toutes les entrées expirées de la table `PasswordResetToken`
 * en utilisant le dépôt `PasswordResetTokenRepository`.
 *
 * @command cleanerpassword
 *
 * @details
 * - Supprime les tokens de réinitialisation de mot de passe expirés.
 * - Fournit un retour d'information via la console avec le nombre d'entrées supprimées.
 * - Peut être planifiée pour une exécution régulière à l'aide d'un gestionnaire de tâches.
 */

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
