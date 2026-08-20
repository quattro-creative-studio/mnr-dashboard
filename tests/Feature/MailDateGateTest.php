<?php

namespace Tests\Feature;

use App\EditableDate;
use App\EditableEmail;
use App\Http\Controllers\MailController;
use App\Mail\CustomEmail;
use App\SentEmail;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\BuildsDomainFixtures;
use Tests\TestCase;

/**
 * Tripwire: scheduled mail fires on exactly one day, exactly once.
 *
 * The whole email calendar rests on two mechanisms working together:
 *
 *   1. EditableDate::find(KEY)->isCurrentDay() -- a mail only goes out on the
 *      configured day. This is Carbon's comparison, so a timezone or Carbon
 *      version change can move which day "today" is. Carbon 2 arrives at
 *      Laravel 6 and Carbon 3 becomes mandatory at Laravel 12.
 *   2. The sent_emails ledger -- within that day the scheduler runs every
 *      minute, and only the ledger stops each tick resending.
 *
 * Break either one and the failure is loud in the worst way: teachers get the
 * same mail every minute, or they get nothing at all and no one finds out for
 * months. Neither has any other alarm attached to it.
 */
class MailDateGateTest extends TestCase
{
    use RefreshDatabase, BuildsDomainFixtures;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function scheduleFor(string $key, Carbon $when): void
    {
        EditableDate::query()->where('key', $key)->update(['value' => $when]);
    }

    private function controller(): MailController
    {
        return app(MailController::class);
    }

    public function testNothingIsSentOnADayThatIsNotTheConfiguredDay()
    {
        Mail::fake();
        $this->makeTeacher();

        $this->scheduleFor(EditableDate::NEW_EDUCATIONAL_TOOL, Carbon::parse('2026-05-10'));
        Carbon::setTestNow(Carbon::parse('2026-05-09 23:59:59'));

        $this->controller()->sendNewEducationalTool();

        Mail::assertNothingQueued();
        $this->assertSame(0, SentEmail::query()->count());
    }

    public function testTheMailGoesOutOnTheConfiguredDay()
    {
        Mail::fake();
        $this->makeTeacher();
        $this->makeTeacher();

        $this->scheduleFor(EditableDate::NEW_EDUCATIONAL_TOOL, Carbon::parse('2026-05-10'));
        Carbon::setTestNow(Carbon::parse('2026-05-10 10:01:00'));

        $this->controller()->sendNewEducationalTool();

        Mail::assertQueued(CustomEmail::class, 2);
    }

    /**
     * The scheduler runs send:new-educational-tool daily, but quiz:update runs
     * every minute and other senders share the same shape. This is the property
     * that makes a same-day re-run harmless.
     */
    public function testASecondRunOnTheSameDaySendsNothingFurther()
    {
        Mail::fake();
        $this->makeTeacher();

        $this->scheduleFor(EditableDate::NEW_EDUCATIONAL_TOOL, Carbon::parse('2026-05-10'));
        Carbon::setTestNow(Carbon::parse('2026-05-10 10:01:00'));

        $this->controller()->sendNewEducationalTool();
        Mail::assertQueued(CustomEmail::class, 1);

        $this->controller()->sendNewEducationalTool();

        Mail::assertQueued(
            CustomEmail::class,
            1,
            'The ledger must stop the second run; anything else means one mail per scheduler tick.'
        );
    }

    public function testTheDayBoundaryIsWhatDecides()
    {
        Mail::fake();
        $this->makeTeacher();

        $this->scheduleFor(EditableDate::NEW_EDUCATIONAL_TOOL, Carbon::parse('2026-05-10 08:00:00'));

        // Same calendar day, well before the stored time of day.
        Carbon::setTestNow(Carbon::parse('2026-05-10 00:00:01'));
        $this->controller()->sendNewEducationalTool();

        Mail::assertQueued(
            CustomEmail::class,
            1,
            'isCurrentDay() compares calendar days, not the time of day stored on the date.'
        );
    }

    public function testTheLedgerRecordsTheMailKeyThatWasSent()
    {
        Mail::fake();
        $teacher = $this->makeTeacher();

        $this->scheduleFor(EditableDate::NEW_EDUCATIONAL_TOOL, Carbon::parse('2026-05-10'));
        Carbon::setTestNow(Carbon::parse('2026-05-10 10:01:00'));

        $this->controller()->sendNewEducationalTool();

        $row = SentEmail::query()->first();

        $this->assertSame(EditableEmail::$MAIL_NEW_EDUCATIONAL_TOOL[0], $row->editable_email_key);
        $this->assertSame($teacher->user->id, $row->user_id);
    }
}
