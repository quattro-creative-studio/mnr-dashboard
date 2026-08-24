<?php

namespace Tests\Feature;

use App\Quiz;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tripwire: a quiz must close at the time the administrator entered.
 *
 * The admin form collects a local date and time, Admin\QuizController converts
 * it, and quiz:update runs EVERY MINUTE comparing closes_at against MySQL's
 * CURRENT_TIMESTAMP. If the stored wall-clock time drifts, the quiz closes
 * early and teachers silently lose access -- no error, no log, nothing.
 *
 * That is not hypothetical. Carbon 3, mandatory from Laravel 11, changed
 * createFromTimestamp() to default to UTC where Carbon 2 used the application
 * timezone. An administrator entering 18:00 would have got 17:00 stored, and
 * 16:00 during summer time. The webhook's responded_at drifted the same way and
 * QuizMakerWebhookTest caught that one; this path had no coverage at all.
 *
 * The margin below is deliberately half an hour: a one-hour drift flips the
 * result, so the test actually detects the failure it exists for.
 */
class QuizClosingTimeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Exactly what Admin\QuizController does with the submitted form values.
     */
    private function convertAsTheControllerDoes(string $enteredByAdmin): Carbon
    {
        return Carbon::createFromTimestamp(strtotime($enteredByAdmin), config('app.timezone'));
    }

    private function makeQuiz(Carbon $closesAt, string $state = Quiz::STATE_RUNNING): Quiz
    {
        return Quiz::create([
            'name' => 'Quiz horaire',
            'email_text' => 'x',
            'max_score' => 10,
            'closes_at' => $closesAt,
            'state' => $state,
        ]);
    }

    public function testTheStoredClosingTimeIsTheOneTheAdministratorTyped()
    {
        $quiz = $this->makeQuiz($this->convertAsTheControllerDoes('2026-03-04 18:00'));

        $this->assertSame(
            '2026-03-04 18:00:00',
            $quiz->fresh()->closes_at->format('Y-m-d H:i:s'),
            'The wall-clock time drifted between the form and the database. Carbon 3 '
            .'defaults createFromTimestamp() to UTC; the application timezone must be '
            .'passed explicitly.'
        );
    }

    public function testAQuizClosingSoonIsNotClosedYet()
    {
        $quiz = $this->makeQuiz(Carbon::now()->addMinutes(30));

        $this->artisan('quiz:update')->assertExitCode(0);

        $this->assertSame(
            Quiz::STATE_RUNNING,
            $quiz->fresh()->state,
            'A quiz due to close in 30 minutes was closed now. A one hour timezone '
            .'drift produces exactly this.'
        );
    }

    public function testAQuizPastItsClosingTimeIsClosed()
    {
        $quiz = $this->makeQuiz(Carbon::now()->subMinutes(30));

        $this->artisan('quiz:update')->assertExitCode(0);

        $this->assertSame(Quiz::STATE_CLOSED, $quiz->fresh()->state);
    }

    /**
     * Reproduces the deployed condition: MySQL's clock disagrees with PHP's.
     *
     * config/database.php sets no session timezone, so MySQL inherits SYSTEM.
     * On a Forge box that is UTC while this application runs
     * Europe/Luxembourg -- an hour apart in winter, two in summer. The offset
     * below is deliberately larger than any real one, and is set before the
     * quiz is written so the whole scenario runs in a single, consistent
     * frame, exactly as a deployed request does.
     *
     * quiz:update used to compare closes_at against CURRENT_TIMESTAMP, which
     * asks the database's clock a question about a value PHP's clock wrote.
     * Under this offset that comparison is wrong, and wrong in the silent
     * direction: a quiz closing at the wrong hour looks just like a quiz
     * closing.
     */
    public function testClosingDoesNotDependOnTheDatabaseServersClock()
    {
        \DB::statement("SET time_zone = '+05:00'");

        try {
            $overdue = $this->makeQuiz(Carbon::now()->subMinutes(30));
            $upcoming = $this->makeQuiz(Carbon::now()->addMinutes(30));

            $this->artisan('quiz:update')->assertExitCode(0);

            $this->assertSame(
                Quiz::STATE_CLOSED,
                $overdue->fresh()->state,
                'A quiz 30 minutes overdue stayed open because the database '
                .'server keeps a different clock from the application.'
            );
            $this->assertSame(
                Quiz::STATE_RUNNING,
                $upcoming->fresh()->state,
                'A quiz due in 30 minutes was closed early by the database '
                .'server\'s clock.'
            );
        } finally {
            \DB::statement("SET time_zone = SYSTEM");
        }
    }

    public function testOnlyRunningQuizzesAreClosed()
    {
        $new = $this->makeQuiz(Carbon::now()->subMinutes(30), Quiz::STATE_NEW);

        $this->artisan('quiz:update')->assertExitCode(0);

        $this->assertSame(
            Quiz::STATE_NEW,
            $new->fresh()->state,
            'A quiz that was never sent must not jump straight to closed.'
        );
    }
}
