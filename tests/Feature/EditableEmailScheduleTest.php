<?php

namespace Tests\Feature;

use App\EditableDate;
use App\EditableEmail;
use App\Http\Controllers\MailController;
use App\Mail\CustomEmail;
use App\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\BuildsDomainFixtures;
use Tests\TestCase;

/**
 * The on/off switch on a scheduled mail, and the single gate it lives in.
 *
 * Before this existed the only way to silence a mail was to move its date out
 * of the way. That worked by accident -- the gate is an exact-day match, so a
 * past date never comes round again -- and it was indistinguishable from a
 * date somebody simply forgot to update at the yearly rollover.
 *
 * Every scheduled sender now passes through EditableEmail::readyToSendToday(),
 * so these tests stand in front of the whole calendar: MailController's eight
 * senders and NewsletterController's.
 */
class EditableEmailScheduleTest extends TestCase
{
    use RefreshDatabase, BuildsDomainFixtures;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function admin(): User
    {
        return User::factory()->create(['type' => User::TYPE_ADMIN]);
    }

    private function scheduleFor(string $key, Carbon $when): void
    {
        EditableDate::query()->where('key', $key)->update(['value' => $when]);
    }

    /**
     * Put the educational-tool mail exactly on today, the state in which it
     * would go out, so the only remaining variable is the enabled flag.
     */
    private function dueToday(): EditableEmail
    {
        $this->scheduleFor(EditableDate::NEW_EDUCATIONAL_TOOL, Carbon::parse('2026-05-10'));
        Carbon::setTestNow(Carbon::parse('2026-05-10 10:01:00'));

        return EditableEmail::find(EditableEmail::$MAIL_NEW_EDUCATIONAL_TOOL);
    }

    public function testAMailIsEnabledUntilSomebodySwitchesItOff()
    {
        $this->assertTrue(
            EditableEmail::find(EditableEmail::$MAIL_NEW_EDUCATIONAL_TOOL)->enabled,
            'The column defaults to true so that importing production data changes nothing.'
        );
    }

    public function testADisabledMailDoesNotGoOutOnItsOwnDay()
    {
        Mail::fake();
        $this->makeTeacher();

        $this->dueToday()->update(['enabled' => false]);

        app(MailController::class)->sendNewEducationalTool();

        Mail::assertNothingQueued();
    }

    public function testTheSameMailGoesOutOnceItIsSwitchedBackOn()
    {
        Mail::fake();
        $this->makeTeacher();

        $mail = $this->dueToday();
        $mail->update(['enabled' => false]);
        app(MailController::class)->sendNewEducationalTool();
        Mail::assertNothingQueued();

        $mail->update(['enabled' => true]);
        app(MailController::class)->sendNewEducationalTool();

        Mail::assertQueued(CustomEmail::class, 1);
    }

    /**
     * The point of a flag rather than a blanked-out date: the date the next
     * edition will want back has to survive being switched off.
     */
    public function testSwitchingAMailOffLeavesItsDateAlone()
    {
        $this->scheduleFor(EditableDate::NEW_EDUCATIONAL_TOOL, Carbon::parse('2026-05-10'));

        $this->actingAs($this->admin())
            ->post(route('admin.emails.toggle', [EditableEmail::$MAIL_NEW_EDUCATIONAL_TOOL[0]]))
            ->assertRedirect(route('admin.emails'));

        $this->assertFalse(EditableEmail::find(EditableEmail::$MAIL_NEW_EDUCATIONAL_TOOL)->enabled);
        $this->assertSame(
            '2026-05-10',
            EditableDate::find(EditableDate::NEW_EDUCATIONAL_TOOL)->toDateString()
        );
    }

    public function testTheToggleFlipsBothWays()
    {
        $key = EditableEmail::$MAIL_NEW_EDUCATIONAL_TOOL[0];
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.emails.toggle', [$key]));
        $this->assertFalse(EditableEmail::findByKey($key)->enabled);

        $this->actingAs($admin)->post(route('admin.emails.toggle', [$key]));
        $this->assertTrue(EditableEmail::findByKey($key)->enabled);
    }

    /**
     * A transactional mail has no schedule, so the flag would silently do
     * nothing. Refusing is the honest answer; storing it would let an
     * administrator believe registrations had stopped confirming.
     */
    public function testATransactionalMailCannotBeSwitchedOff()
    {
        $key = EditableEmail::$MAIL_TEACHER_CONFIRMATION[0];

        $this->actingAs($this->admin())
            ->post(route('admin.emails.toggle', [$key]))
            ->assertRedirect(route('admin.emails'))
            ->assertSessionHas('error');

        $this->assertTrue(EditableEmail::findByKey($key)->enabled);
    }

    public function testOnlyAnAdministratorCanSwitchAMailOff()
    {
        $key = EditableEmail::$MAIL_NEW_EDUCATIONAL_TOOL[0];

        $this->actingAs($this->makeTeacher()->user)
            ->post(route('admin.emails.toggle', [$key]))
            ->assertRedirect();

        $this->assertTrue(EditableEmail::findByKey($key)->enabled);
    }

    /**
     * The gate used to be $date->isCurrentDay() on the result of
     * EditableDate::find(), which returns null for an absent key -- and Carbon 3
     * raises a TypeError on null. A database seeded with only part of the
     * calendar would have taken the scheduler down rather than skip the mail.
     */
    public function testAMailWithNoConfiguredDateIsSkippedRatherThanFatal()
    {
        Mail::fake();
        $this->makeTeacher();

        EditableDate::query()->where('key', EditableDate::NEW_EDUCATIONAL_TOOL)->delete();

        app(MailController::class)->sendNewEducationalTool();

        Mail::assertNothingQueued();
    }

    /**
     * Guards the classification the admin list is drawn from. Anything not
     * declared transactional or dormant is treated as sent by the calendar, and
     * a calendar mail with no date would be shown with an on/off switch that no
     * sender consults -- the switch would simply not work.
     */
    public function testEveryScheduledMailHasADate()
    {
        foreach (EditableEmail::with('dates')->get() as $mail) {
            if (!$mail->isScheduled()) {
                continue;
            }

            $this->assertNotNull(
                $mail->scheduleDate(),
                "'{$mail->key}' is treated as sent by the calendar but has no date. "
                .'Link it to an EditableDate, or declare it in EditableEmail::$TRANSACTIONAL_KEYS '
                .'(an action sends it) or $DORMANT_KEYS (nothing sends it this year).'
            );
        }
    }

    public function testEveryMailFallsInExactlyOneMode()
    {
        $overlap = array_intersect(EditableEmail::$TRANSACTIONAL_KEYS, EditableEmail::$DORMANT_KEYS);

        $this->assertSame([], $overlap, 'A mail cannot be both transactional and dormant.');
    }

    /**
     * The follow-up family is deliberately kept and deliberately unwired. An
     * administrator editing its text has to see that nothing will send it.
     */
    public function testADormantMailCannotBeSwitchedOff()
    {
        $key = EditableEmail::$MAIL_FOLLOW_UP_1[0];

        $this->actingAs($this->admin())
            ->post(route('admin.emails.toggle', [$key]))
            ->assertRedirect(route('admin.emails'))
            ->assertSessionHas('error');

        $this->assertTrue(EditableEmail::findByKey($key)->enabled);
    }

    /**
     * Merging the two tables put the send dates inside the mail rows, and a row
     * only renders an input when the calendar is what sends it. Miss a date and
     * it silently stops being editable anywhere -- which for the registration
     * dates would mean the public form could no longer be opened or closed.
     */
    public function testEveryDateStaysEditableOnThePage()
    {
        $response = $this->actingAs($this->admin())->get(route('admin.emails'));

        foreach (EditableDate::all() as $date) {
            $response->assertSee('value="'.$date->key.'"', false);
        }
    }

    public function testTheListShowsEveryMailWithItsMode()
    {
        $response = $this->actingAs($this->admin())->get(route('admin.emails'));

        $response->assertOk()
            ->assertSee('Transactionnel')
            ->assertSee('Actif')
            ->assertSee('non utilis', false);

        // One row per mail, and no separate e-mail/date pair of tables to
        // reconcile. The Libellé column shows the linked date's label, so the
        // edit link is what identifies a row unambiguously.
        foreach (EditableEmail::all() as $mail) {
            $response->assertSee(route('admin.emails.edit', [$mail->key]), false);
        }
    }

    /**
     * sort_order was added in 2023 with a default of 0 and never populated by a
     * migration -- production's ordering was typed into the database by hand.
     * A fresh install therefore listed the calendar in an arbitrary order, and
     * staging showed exactly that. The migration is what closes the gap, so the
     * order is asserted from a freshly migrated database.
     */
    public function testTheCalendarIsListedInTheOrderItGoesOut()
    {
        $expected = [
            'teacher_confirmation',
            'newsletter_start',
            'new_educational_tool',
            'invite_party',
            'final',
            'invite_party_reminder',
            'invite_party_informations',
            'invite_party_reminder_second',
            'invite_party_j_2',
            'end_year_communication_email',
        ];

        $listed = EditableEmail::query()
            ->orderBy('sort_order')
            ->get()
            ->reject->isDormant()
            ->pluck('key')
            ->values()
            ->all();

        $this->assertSame($expected, $listed);
    }

    /**
     * Production deleted the retired follow-up mails outright. Keeping them is
     * the deliberate difference -- the mechanism is toggled back on between
     * editions -- so they must stay reachable while staying out of the way.
     */
    public function testTheRetiredMailsAreFoldedAwayButStillEditable()
    {
        $response = $this->actingAs($this->admin())->get(route('admin.emails'));

        $dormant = EditableEmail::all()->filter->isDormant();

        $this->assertGreaterThan(0, $dormant->count());

        foreach ($dormant as $mail) {
            $response->assertSee(route('admin.emails.edit', [$mail->key]), false);
        }

        // Out of the running order: nothing dormant sits between two live mails.
        $ranks = EditableEmail::all()->groupBy(fn ($mail) => $mail->isDormant() ? 'off' : 'on');

        $this->assertGreaterThan(
            $ranks['on']->max('sort_order'),
            $ranks['off']->min('sort_order'),
            'A dormant mail is ranked among the mails that actually go out.'
        );
    }
}
