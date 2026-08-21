<?php

namespace Tests\Feature;

use App\PartyGroup;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsDomainFixtures;
use Tests\TestCase;

/**
 * Tripwire: the two admin spreadsheet exports must actually generate.
 *
 * PhpSpreadsheet 2.0 removed the entire *ByColumnAndRow() family, and this
 * application had nine of them -- all in ClassExportController. Nothing about
 * that is visible to a linter or to static analysis: the code parses fine and
 * fails only when the export is requested, with a fatal error, in production,
 * to an administrator who was trying to get their data out.
 *
 * These exports had no coverage at all, which is exactly why this file exists.
 * It drives both endpoints end to end with real data -- classes, teachers,
 * quizzes, responses and party groups -- so that every converted call site is
 * genuinely executed rather than merely compiled.
 */
class SpreadsheetExportTest extends TestCase
{
    use RefreshDatabase, BuildsDomainFixtures;

    private function actingAsAdmin(): User
    {
        $admin = User::factory()->create(['type' => User::TYPE_ADMIN]);
        $this->actingAs($admin);

        return $admin;
    }

    /**
     * Enough shape that the exports exercise their loops rather than an empty set.
     */
    private function seedContest(): void
    {
        foreach (range(1, 3) as $n) {
            $class = $this->makeClass();
            $this->giveQuizResponses($class, 2);

            PartyGroup::create([
                'name' => "Groupe {$n}",
                'students' => 5,
                'language' => 'fr',
                'school_class_id' => $class->id,
            ]);
        }
    }

    public function testTheClassExportGenerates()
    {
        Storage::fake();
        $this->actingAsAdmin();
        $this->seedContest();

        $response = $this->get(route('admin.classes.export'));

        $this->assertSame(
            200,
            $response->getStatusCode(),
            'The class export failed. This is the file that carried all nine '
            .'*ByColumnAndRow() calls removed in PhpSpreadsheet 2.0.'
        );
    }

    public function testThePartyExportGenerates()
    {
        Storage::fake();
        $this->actingAsAdmin();
        $this->seedContest();

        $response = $this->get(route('admin.party.export'));

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testTheExportsAreAdminOnly()
    {
        $this->actingAs($this->makeTeacher()->user);

        $this->get(route('admin.classes.export'))->assertStatus(302);
        $this->get(route('admin.party.export'))->assertStatus(302);
    }

    /**
     * Both exports run their header and border loops over an empty result set
     * too -- a zero-row export must not divide by zero or build an invalid range.
     */
    public function testTheExportsSurviveAnEmptyContest()
    {
        Storage::fake();
        $this->actingAsAdmin();

        $this->get(route('admin.classes.export'))->assertStatus(200);
        $this->get(route('admin.party.export'))->assertStatus(200);
    }
}
