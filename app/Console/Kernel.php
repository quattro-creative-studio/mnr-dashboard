<?php

namespace App\Console;

use App\Console\Commands\SendFollowUpEmails;
use App\Console\Commands\SendNewsletter;
use App\Console\Commands\QuizUpdateCommand;
use App\Console\Commands\SendFinalMailCommand;
use App\Console\Commands\SendFinalMailsCommand;
use App\Console\Commands\SendNewEducationalToolCommand;
use App\Console\Commands\SendPartyInformationsCommand;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel {
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        //
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule) {
        // $schedule->command(SendFollowUpEmails::class)->dailyAt('10:03')->withoutOverlapping();
        $schedule->command(SendNewsletter::class)->everyTenMinutes()->withoutOverlapping();
        // Backups are the host's responsibility: Hetzner snapshots with 7 day
        // retention. spatie/laravel-backup was removed during the upgrade -- its
        // schedule had already been commented out for this same reason.
        $schedule->command(SendNewEducationalToolCommand::class)->dailyAt('10:01')->withoutOverlapping();
        $schedule->command(SendFinalMailsCommand::class)->dailyAt('10:03')->withoutOverlapping();
        $schedule->command(SendPartyInformationsCommand::class)->dailyAt('10:05')->withoutOverlapping();
        $schedule->command(QuizUpdateCommand::class)->everyMinute();
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands() {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
