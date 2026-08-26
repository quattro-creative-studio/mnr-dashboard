<?php

namespace Tests\Feature;

use App\EditableEmail;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\BuildsDomainFixtures;
use Tests\TestCase;

/**
 * The admin pages must render against a freshly migrated database.
 *
 * That is not a hypothetical state: it is what every new server starts from,
 * and it is where /admin/emails returned a 500 on staging. The view read
 * `$email->dates->first()->label` while a fresh migrate seeds 11 of the 23
 * date keys, leaving 9 of the 19 mails with no date at all -- eight of them
 * the follow-up mails deliberately kept from the previous contest rules.
 *
 * RefreshDatabase migrates exactly the same way, so these tests reproduce the
 * failing condition for free. The suite simply never opened these pages: only
 * 8 of 67 GET routes were covered, and the whole view layer had crossed eight
 * major versions unexercised.
 */
class AdminPagesRenderTest extends TestCase
{
    use RefreshDatabase, BuildsDomainFixtures;

    private function admin(): User
    {
        // teacher_id must stay null, or RedirectIfAuthenticated diverts to the
        // teacher section before the admin routes are ever reached.
        return User::factory()->create([
            'type' => User::TYPE_ADMIN,
            'teacher_id' => null,
        ]);
    }

    public static function adminPages(): array
    {
        return [
            'classes' => ['admin.classes'],
            'emails' => ['admin.emails'],
            'dates' => ['admin.dates'],
            'quiz' => ['admin.quiz'],
            'schools' => ['admin.schools'],
            'teachers' => ['admin.teachers'],
            'documents' => ['admin.documents'],
            'certificates' => ['admin.certificates'],
            'users' => ['admin.users'],
            'settings' => ['admin.settings'],
        ];
    }

    // PHPUnit 12 no longer reads the @dataProvider annotation; the attribute
    // is the only form it recognises.
    #[DataProvider('adminPages')]
    public function testEachAdminPageRendersOnAFreshDatabase(string $routeName)
    {
        $this->actingAs($this->admin())
            ->get(route($routeName))
            ->assertOk();
    }

    /**
     * The exact regression, stated on its own so a failure names the cause
     * rather than just pointing at a page.
     */
    public function testTheEmailsPageSurvivesAMailWithNoSendDate()
    {
        $orphans = EditableEmail::doesntHave('dates')->get();

        $this->assertNotEmpty(
            $orphans,
            'A fresh migrate used to leave mails without a date; if that is no '
            .'longer true this test no longer reproduces the 500 it was written for.'
        );

        $response = $this->actingAs($this->admin())->get(route('admin.emails'));

        $response->assertOk();

        // The row falls back to the mail's own title rather than dying on a
        // null date.
        $response->assertSee($orphans->first()->title, false);
    }
}
