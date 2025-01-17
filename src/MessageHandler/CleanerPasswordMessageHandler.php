<?php

// src/MessageHandler/CleanerPasswordMessageHandler.php
namespace App\MessageHandler;

use App\Message\CleanerPasswordMessage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

#[AsMessageHandler]
final class CleanerPasswordMessageHandler
{
    public function __invoke(CleanerPasswordMessage $message)
    {
        // Exécution d'une commande spécifique pour CleanerPassword
        $process = new Process(['php', 'bin/console', 'cleanerpassword']);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        echo $process->getOutput();
    }
}
