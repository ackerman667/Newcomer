<?php
// src/MessageHandler/CleanerTdMessageHandler.php
// src/MessageHandler/CleanerTdMessageHandler.php
namespace App\MessageHandler;

use App\Message\CleanerTdMessage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

#[AsMessageHandler]
  /**
     * Exécute le processus de nettoyage de la table temporary_data.
     *
     * @param CleanerTdMessage $message Le message reçu pour déclencher l'opération de nettoyage.
     *
     * @throws ProcessFailedException Si le processus échoue.
     */
final class CleanerTdMessageHandler
{
    public function __invoke(CleanerTdMessage $message)
    {
     // Crée un processus pour exécuter la commande de nettoyage.
        $process = new Process(['php', 'bin/console', 'cleanertd']);
        $process->run();

       
        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        
        echo $process->getOutput();
    }
}
