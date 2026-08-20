<?php

namespace Tests\Feature;

use App\QuizResponse;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsDomainFixtures;
use Tests\TestCase;

/**
 * Tripwire: quiz scoring arrives from outside.
 *
 * quiz-maker.com posts results to /api/webhook/quiz-maker. Everything about
 * eligibility -- party invitations, certificates -- is downstream of the rows
 * this endpoint writes, and it is unauthenticated and fire-and-forget: the
 * remote end always gets an empty 200 and never learns that anything failed.
 * If this silently stops recording, nothing anywhere raises an alarm until
 * classes start missing from the party list months later.
 */
class QuizMakerWebhookTest extends TestCase
{
    use RefreshDatabase, BuildsDomainFixtures;

    private function payload(string $quizMakerId, array $responses): array
    {
        return ['json' => json_encode([
            'quiz' => ['id' => $quizMakerId],
            'responses' => $responses,
        ])];
    }

    private function response(int $id, string $code, int $score, int $endMs): array
    {
        return [
            'id' => $id,
            'score' => $score,
            'unique_code' => ['code' => $code],
            'times' => ['end' => $endMs],
        ];
    }

    public function testAValidPayloadRecordsTheScore()
    {
        Storage::fake();
        $class = $this->makeClass();
        $quizCode = $this->makeQuizCode($class, 'QM-100', 'CODE-A');
        $endMs = Carbon::parse('2026-03-04 10:20:30')->getTimestamp() * 1000;

        $this->post(route('api.webhook.quizmaker'), $this->payload('QM-100', [
            $this->response(555, 'CODE-A', 8, $endMs),
        ]))->assertStatus(200);

        $recorded = QuizResponse::query()->first();

        $this->assertNotNull($recorded, 'The webhook did not record a response.');
        $this->assertSame(8, $recorded->score);
        $this->assertSame(555, $recorded->quizmaker_response_id);
        $this->assertSame($quizCode->quiz_assignment_id, $recorded->quiz_assignment_id);
        $this->assertSame(
            '2026-03-04 10:20:30',
            Carbon::parse($recorded->responded_at)->format('Y-m-d H:i:s'),
            'responded_at is built from times.end, which arrives in milliseconds.'
        );
    }

    public function testTheRawPayloadIsArchived()
    {
        Storage::fake();
        $class = $this->makeClass();
        $this->makeQuizCode($class, 'QM-100', 'CODE-A');

        $this->post(route('api.webhook.quizmaker'), $this->payload('QM-100', [
            $this->response(555, 'CODE-A', 8, 1772619630000),
        ]))->assertStatus(200);

        $archived = collect(Storage::allFiles('quiz-maker-hooks'));

        $this->assertCount(1, $archived, 'Every payload should be archived for dispute resolution.');
    }

    public function testAMissingJsonParameterIsIgnored()
    {
        Storage::fake();

        $this->post(route('api.webhook.quizmaker'), [])->assertStatus(200);

        $this->assertSame(0, QuizResponse::query()->count());
        $this->assertCount(0, Storage::allFiles('quiz-maker-hooks'));
    }

    public function testAnUnknownQuizIsIgnoredButStillArchived()
    {
        Storage::fake();

        $this->post(route('api.webhook.quizmaker'), $this->payload('QM-DOES-NOT-EXIST', [
            $this->response(1, 'CODE-A', 5, 1772619630000),
        ]))->assertStatus(200);

        $this->assertSame(0, QuizResponse::query()->count());
        $this->assertCount(1, Storage::allFiles('quiz-maker-hooks'));
    }

    public function testAnUnknownCodeIsSkippedButTheBatchContinues()
    {
        Storage::fake();
        $class = $this->makeClass();
        $this->makeQuizCode($class, 'QM-100', 'CODE-A');

        $this->post(route('api.webhook.quizmaker'), $this->payload('QM-100', [
            $this->response(1, 'CODE-UNKNOWN', 3, 1772619630000),
            $this->response(2, 'CODE-A', 7, 1772619630000),
        ]))->assertStatus(200);

        $this->assertSame(1, QuizResponse::query()->count());
        $this->assertSame(7, QuizResponse::query()->first()->score);
    }

    /**
     * Documented oddity, not a recommendation.
     *
     * On a code whose assignment already has a response the controller does
     * `return`, not `continue` -- so one already-seen code abandons every
     * remaining response in the same payload. A retry from quiz-maker that
     * replays earlier results therefore drops the new ones.
     *
     * Pinned deliberately: if this is ever changed to `continue`, that should
     * be a decision someone makes, not a side effect of a framework upgrade.
     */
    public function testADuplicateCodeAbandonsTheRestOfTheBatch()
    {
        Storage::fake();
        $class = $this->makeClass();
        $this->makeQuizCode($class, 'QM-100', 'CODE-A');
        $second = $this->makeQuizCode($class, 'QM-100', 'CODE-B');

        // First delivery records CODE-A.
        $this->post(route('api.webhook.quizmaker'), $this->payload('QM-100', [
            $this->response(1, 'CODE-A', 4, 1772619630000),
        ]))->assertStatus(200);

        $this->assertSame(1, QuizResponse::query()->count());

        // Redelivery replays CODE-A and adds CODE-B. CODE-B is lost.
        $this->post(route('api.webhook.quizmaker'), $this->payload('QM-100', [
            $this->response(1, 'CODE-A', 4, 1772619630000),
            $this->response(2, 'CODE-B', 9, 1772619630000),
        ]))->assertStatus(200);

        $this->assertSame(
            1,
            QuizResponse::query()->count(),
            'CODE-B was dropped because the duplicate CODE-A returned out of the loop.'
        );
    }
}
