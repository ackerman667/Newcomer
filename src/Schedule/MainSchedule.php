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
final class MainSchedule implements ScheduleProviderInterface
{
    public function getSchedule(): Schedule
    {
        return (new Schedule())
        ->add(RecurringMessage::cron('0 0 * * *', new CleanerTdMessage())) // Tous les jours à minuit
        ->add(RecurringMessage::cron('0 0 * * *', new CleanerPasswordMessage())); 
}
}
