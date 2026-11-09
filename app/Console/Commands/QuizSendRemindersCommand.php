<?php

namespace App\Console\Commands;

use App\Quiz;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class QuizSendRemindersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'quiz:send-reminders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sends the scheduled reminder of running quizzes whose reminder time has been reached.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        // now(), not CURRENT_TIMESTAMP -- see QuizUpdateCommand: reminder_at is
        // written by PHP in the application timezone.
        $due = Quiz::query()
            ->where('state', '=', Quiz::STATE_RUNNING)
            ->whereNotNull('reminder_at')
            ->whereNull('reminder_sent_at')
            ->where('reminder_at', '<=', now())
            ->where('closes_at', '>', now())
            ->get();

        foreach ($due as $quiz) {
            // Claimed with a conditional update before anything is queued, so
            // two overlapping runs cannot both send the same reminder.
            $claimed = Quiz::query()
                ->whereKey($quiz->id)
                ->whereNull('reminder_sent_at')
                ->update(['reminder_sent_at' => now()]);

            if (!$claimed) {
                continue;
            }

            $count = $quiz->sendReminders();

            $message = sprintf('Quiz #%d "%s": rappel automatique envoyé à %d classes', $quiz->id, $quiz->name, $count);
            Log::info($message);
            $this->info($message);
        }

        return 0;
    }
}
