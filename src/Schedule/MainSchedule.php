<?php

// src/Schedule/MainSchedule.php
namespace App\Schedule;

use App\Message\CleanerTdMessage;
use App\Message\CleanerPasswordMessage;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

#[AsSchedule('default')]
/**
 * Planificateur principal des tâches récurrentes.
 * Définit les tâches à exécuter périodiquement à l'aide de messages.
 */
final class MainSchedule implements ScheduleProviderInterface
{
    public function getSchedule(): Schedule
    {
        return (new Schedule())
        ->add(RecurringMessage::cron('0 0 * * *', new CleanerTdMessage())) 
        ->add(RecurringMessage::cron('0 0 * * *', new CleanerPasswordMessage())); 
}
}
