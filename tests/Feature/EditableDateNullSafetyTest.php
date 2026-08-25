<?php

namespace Tests\Feature;

use App\EditableDate;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tripwire: a missing EditableDate must not take the application down.
 *
 * EditableDate::find() returns null for an absent key. Carbon 2 accepted null
 * in a comparison and quietly treated it as "now"; Carbon 3, mandatory from
 * Laravel 11, types the argument DateTimeInterface|string and raises a
 * TypeError instead.
 *
 * That is not a theoretical difference. Twelve of the twenty-three configured
 * date keys were absent from the development database, and thirteen call sites
 * passed find() straight into a comparison. The admin class list and the class
 * edit form both returned 500 on Laravel 13 -- found by loading the page, not
 * by any test.
 *
 * The one that would have hurt most is isRegistrationOpen(): it is called from
 * layouts/app-sidebar, the shell of EVERY authenticated page. Lose either
 * inscription date and the whole application 500s for everyone at once.
 */
class EditableDateNullSafetyTest extends TestCase
{
    use RefreshDatabase;

    private function forget(string $key): void
    {
        EditableDate::query()->where('key', $key)->delete();
    }

    /**
     * Migrations seed only 10 of the 23 declared keys, so a test cannot assume a
     * given date exists -- that gap is precisely what this file is about.
     */
    private function setDate(string $key, Carbon $when): void
    {
        EditableDate::query()->updateOrCreate(
            ['key' => $key],
            ['label' => $key, 'value' => $when]
        );
    }

    public function testAMissingDateReportsAsNotYetReached()
    {
        $this->forget(EditableDate::FOLLOW_UP_1);

        $this->assertNull(EditableDate::find(EditableDate::FOLLOW_UP_1));
        $this->assertFalse(
            EditableDate::hasPassed(EditableDate::FOLLOW_UP_1),
            'An unconfigured date must read as not reached. Carbon 2 answered true here '
            .'by accident, which is how an unstarted follow-up appeared as though it had begun.'
        );
    }

    public function testAPastDateReportsAsPassedAndAFutureOneDoesNot()
    {
        $this->setDate(EditableDate::FOLLOW_UP_1, Carbon::now()->subDay());
        $this->assertTrue(EditableDate::hasPassed(EditableDate::FOLLOW_UP_1));

        $this->setDate(EditableDate::FOLLOW_UP_1, Carbon::now()->addDay());
        $this->assertFalse(EditableDate::hasPassed(EditableDate::FOLLOW_UP_1));
    }

    public function testRegistrationIsClosedWhenEitherBoundIsMissing()
    {
        $this->forget(EditableDate::TEACHER_INSCRIPTION_START);
        $this->assertFalse(isRegistrationOpen(), 'Missing start date must close registration, not raise.');

        $this->refreshApplication();
        $this->forget(EditableDate::TEACHER_INSCRIPTION_END);
        $this->assertFalse(isRegistrationOpen(), 'Missing end date must close registration, not raise.');
    }

    public function testRegistrationIsOpenBetweenItsBounds()
    {
        $this->setDate(EditableDate::TEACHER_INSCRIPTION_START, Carbon::now()->subDay());
        $this->setDate(EditableDate::TEACHER_INSCRIPTION_END, Carbon::now()->addDay());

        $this->assertTrue(isRegistrationOpen());

        $this->setDate(EditableDate::TEACHER_INSCRIPTION_END, Carbon::now()->subHour());

        $this->assertFalse(isRegistrationOpen(), 'Registration must close once the end date passes.');
    }

    /**
     * The page that actually broke.
     */
    public function testTheAdminClassListRendersWithEveryFollowUpDateMissing()
    {
        foreach ([EditableDate::FOLLOW_UP_1, EditableDate::FOLLOW_UP_2, EditableDate::FOLLOW_UP_3] as $key) {
            $this->forget($key);
        }

        $admin = \App\User::factory()->create(['type' => \App\User::TYPE_ADMIN]);

        $this->actingAs($admin)->get('/admin/classes')->assertStatus(200);
    }
}
