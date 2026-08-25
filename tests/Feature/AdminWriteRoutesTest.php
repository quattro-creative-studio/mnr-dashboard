<?php

namespace Tests\Feature;

use App\EditableDate;
use App\EditableEmail;
use App\Quiz;
use App\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsDomainFixtures;
use Tests\TestCase;

/**
 * The admin write routes that steer the whole contest.
 *
 * Editable emails and editable dates are the core of this application: the
 * code only ever references stable keys, and everything an administrator can
 * change about what gets sent, and when, passes through these two forms. The
 * quiz routes are next, because a quiz with the wrong closing time silently
 * locks teachers out.
 *
 * None of these had any coverage. They also carry the two conversions this
 * migration had to get right -- Carbon 3 timezones and Laravel's changed
 * validation defaults -- so a regression here is quiet and expensive.
 */
class AdminWriteRoutesTest extends TestCase
{
    use RefreshDatabase, BuildsDomainFixtures;

    private function admin(): User
    {
        return User::factory()->create(['type' => User::TYPE_ADMIN]);
    }

    public function testAnAdministratorCanRewriteAnEmailBody()
    {
        $email = EditableEmail::find(EditableEmail::$MAIL_NEWSLETTER_START);

        $this->actingAs($this->admin())
            ->post(route('admin.emails.edit.post', [$email->key]), [
                'subject' => 'Nouveau sujet',
                'text' => 'Bonjour %PROF%, la classe %NOM_CLASSE% est inscrite.',
            ])
            ->assertRedirect(route('admin.emails'));

        $email->refresh();

        $this->assertSame('Nouveau sujet', $email->subject);
        $this->assertStringContainsString('%PROF%', $email->text);
    }

    public function testAnEmailCannotBeSavedEmpty()
    {
        $email = EditableEmail::find(EditableEmail::$MAIL_NEWSLETTER_START);
        $before = $email->text;

        $this->actingAs($this->admin())
            ->from(route('admin.emails'))
            ->post(route('admin.emails.edit.post', [$email->key]), [
                'subject' => '',
                'text' => '',
            ])
            ->assertSessionHasErrors(['subject', 'text']);

        $this->assertSame($before, $email->fresh()->text);
    }

    /**
     * A teacher reaching an admin form would be able to rewrite every automated
     * mail the contest sends. The middleware is the only thing stopping it.
     */
    public function testATeacherCannotRewriteAnEmailBody()
    {
        $email = EditableEmail::find(EditableEmail::$MAIL_NEWSLETTER_START);
        $before = $email->subject;
        $teacher = $this->makeTeacher();

        $this->actingAs($teacher->user)
            ->post(route('admin.emails.edit.post', [$email->key]), [
                'subject' => 'Détourné',
                'text' => 'Texte détourné',
            ]);

        $this->assertSame($before, $email->fresh()->subject);
    }

    public function testAnAdministratorCanMoveTheContestDates()
    {
        $key = EditableDate::TEACHER_INSCRIPTION_START;
        EditableDate::updateOrCreate(['key' => $key], ['value' => now()->subYear()]);

        $this->actingAs($this->admin())
            ->post(route('admin.dates.post'), [
                'dates' => [['key' => $key, 'value' => '2027-09-15 08:00:00']],
            ]);

        $stored = EditableDate::find($key);

        $this->assertNotNull($stored, 'EditableDate::find() returns the Carbon value, not the model.');

        // Day granularity is deliberate: the column is DATE, the form submits a
        // date alone, and the scheduled senders gate on isCurrentDay(). A time
        // of day submitted here is dropped, which is why none is offered.
        $this->assertSame('2027-09-15', $stored->format('Y-m-d'));
        $this->assertSame('00:00:00', $stored->format('H:i:s'));
    }

    public function testAnUnknownDateKeyIsRejected()
    {
        $this->actingAs($this->admin())
            ->from(route('admin.dates'))
            ->post(route('admin.dates.post'), [
                'dates' => [['key' => 'cle_inexistante', 'value' => '2027-09-15 08:00:00']],
            ])
            ->assertSessionHasErrors('dates.0.key');
    }

    private function quizPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Quiz de rentrée',
            'max_score' => 10,
            'closes_at_date' => now()->addMonth()->format('Y-m-d'),
            'closes_at_time' => '18:00',
            'email_text' => 'Texte du mail',
            'classes' => [],
            'quiz_url' => [
                'fr' => 'https://www.quiz-maker.com/QABC123',
                'de' => 'https://www.quiz-maker.com/QDEF456',
            ],
        ], $overrides);
    }

    /**
     * The regression this migration nearly shipped: Carbon 3 defaults
     * createFromTimestamp() to UTC where Carbon 2 used the application
     * timezone, so 18:00 typed by an administrator became 17:00 stored, or
     * 16:00 in summer. The quiz then closes early and teachers lose access
     * with nothing reporting it.
     */
    public function testTheClosingTimeIsStoredAsTheAdministratorTypedIt()
    {
        $closesOn = now()->addMonth()->format('Y-m-d');

        $this->actingAs($this->admin())
            ->post(route('admin.quiz.create.post'), $this->quizPayload([
                'closes_at_date' => $closesOn,
                'closes_at_time' => '18:00',
            ]));

        $quiz = Quiz::where('name', 'Quiz de rentrée')->first();

        $this->assertNotNull($quiz, 'The quiz form was accepted but nothing was stored.');
        $this->assertSame(
            $closesOn.' 18:00:00',
            $quiz->closes_at->format('Y-m-d H:i:s'),
            'The wall-clock closing time drifted between the form and the database.'
        );
    }

    public function testCreatingAQuizStoresOneRecordPerLanguage()
    {
        $this->actingAs($this->admin())
            ->post(route('admin.quiz.create.post'), $this->quizPayload());

        $quiz = Quiz::where('name', 'Quiz de rentrée')->firstOrFail();

        $this->assertSame(Quiz::STATE_NEW, $quiz->state);
        $this->assertEqualsCanonicalizing(
            ['fr', 'de'],
            $quiz->quizInLanguage->pluck('language')->all()
        );
        $this->assertSame(
            'ABC123',
            $quiz->quizInLanguage->firstWhere('language', 'fr')->quiz_maker_id
        );
    }

    /**
     * The URL is where the quiz-maker id comes from. Anything that is not a
     * quiz-maker link yields no id, and the webhook can then never match a
     * response back to this quiz.
     */
    public function testAQuizUrlFromAnotherServiceIsRejected()
    {
        $this->actingAs($this->admin())
            ->from(route('admin.quiz.create'))
            ->post(route('admin.quiz.create.post'), $this->quizPayload([
                'quiz_url' => [
                    'fr' => 'https://example.com/pas-un-quiz',
                    'de' => 'https://www.quiz-maker.com/QDEF456',
                ],
            ]))
            ->assertSessionHasErrors('quiz_url.fr');

        $this->assertSame(0, Quiz::where('name', 'Quiz de rentrée')->count());
    }

    public function testAQuizClosingInThePastIsRejected()
    {
        $this->actingAs($this->admin())
            ->from(route('admin.quiz.create'))
            ->post(route('admin.quiz.create.post'), $this->quizPayload([
                'closes_at_date' => now()->subDay()->format('Y-m-d'),
            ]))
            ->assertSessionHasErrors('closes_at');
    }

    public function testAClosedQuizCannotBeEdited()
    {
        $quiz = Quiz::create([
            'name' => 'Quiz clos',
            'email_text' => 'x',
            'max_score' => 10,
            'closes_at' => now()->subDay(),
            'state' => Quiz::STATE_CLOSED,
        ]);

        $this->actingAs($this->admin())
            ->post(route('admin.quiz.edit.post', [$quiz]), [
                'name' => 'Renommé après clôture',
                'max_score' => 20,
                'closes_at_date' => now()->addMonth()->format('Y-m-d'),
                'closes_at_time' => '18:00',
            ])
            ->assertForbidden();

        $this->assertSame('Quiz clos', $quiz->fresh()->name);
    }

    public function testCodesCannotBeUploadedOnceAQuizIsRunning()
    {
        $quiz = Quiz::create([
            'name' => 'Quiz en cours',
            'email_text' => 'x',
            'max_score' => 10,
            'closes_at' => now()->addMonth(),
            'state' => Quiz::STATE_RUNNING,
        ]);

        $this->actingAs($this->admin())
            ->post(route('admin.quiz.show.codes.post', [$quiz]), [])
            ->assertForbidden();
    }
}
