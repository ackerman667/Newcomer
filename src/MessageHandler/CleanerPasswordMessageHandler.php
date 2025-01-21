<?php

// src/MessageHandler/CleanerPasswordMessageHandler.php
namespace App\MessageHandler;

use App\Message\CleanerPasswordMessage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

#[AsMessageHandler]
 /**
     * Exécute le processus de nettoyage de la table PasswordResetToken.
     *
     * @param CleanerPasswordMessage $message Le message reçu pour déclencher l'opération de nettoyage.
     *
     * @throws ProcessFailedException Si le processus échoue.
     */
final class CleanerPasswordMessageHandler
{
    public function __invoke(CleanerPasswordMessage $message)
    {
        // Crée un processus pour exécuter la commande de nettoyage.
        $process = new Process(['php', 'bin/console', 'cleanerpassword']);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        echo $process->getOutput();
    }
}
