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
