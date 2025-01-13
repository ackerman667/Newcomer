<?php
// src/MessageHandler/CleanerTdMessageHandler.php
// src/MessageHandler/CleanerTdMessageHandler.php
namespace App\MessageHandler;

use App\Message\CleanerTdMessage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

#[AsMessageHandler]
final class CleanerTdMessageHandler
{
    public function __invoke(CleanerTdMessage $message)
    {
        // Exécution de la commande cleanertd via le composant Process
        $process = new Process(['php', 'bin/console', 'cleanertd']);
        $process->run();

        // Vérifiez si la commande a échoué
        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        // Affichez le résultat de la commande (facultatif, utile pour le débogage)
        echo $process->getOutput();
    }
}
