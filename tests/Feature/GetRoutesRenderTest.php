<?php

namespace Tests\Feature;

use App\Certificate;
use App\Document;
use App\EditableDate;
use App\EditableEmail;
use App\PartyGroup;
use App\School;
use App\SchoolClass;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\BuildsDomainFixtures;
use Tests\TestCase;

/**
 * Every readable GET route, opened with real data behind it.
 *
 * The suite covered 8 of 67 GET routes, and the whole view layer had crossed
 * eight major versions unexercised. That is how /admin/emails reached staging
 * returning a 500: the page died on a mail with no send date, a state a fresh
 * migrate produces and a developer database never does.
 *
 * The pages that only ever failed on an empty database are covered by
 * AdminPagesRenderTest. This one is the opposite half -- pages opened WITH a
 * class, a quiz, a document, a certificate and a party group in place, since
 * several of them are perfectly happy while the tables are empty and die on
 * the first row.
 *
 * Destructive GET routes are deliberately excluded; see
 * testDestructiveActionsAreReachableByGet for why they are worth knowing about.
 */
class GetRoutesRenderTest extends TestCase
{
    use RefreshDatabase, BuildsDomainFixtures;

    private function admin(): User
    {
        return User::factory()->create(['type' => User::TYPE_ADMIN, 'teacher_id' => null]);
    }

    /**
     * Several teacher pages are gated on EditableDate values and redirect when
     * the window is shut. A fresh migrate seeds 11 of the 23 keys, so most
     * windows are shut and the pages would 302 without ever rendering -- the
     * view would go untested while the test still passed a weaker assertion.
     *
     * Start dates go into the past and end dates into the future, which is the
     * mid-contest state these pages are written for.
     */
    private function openEveryWindow(): void
    {
        $reflection = new \ReflectionClass(EditableDate::class);

        foreach ($reflection->getConstants() as $name => $key) {
            if (! is_string($key)) {
                continue;
            }

            $value = str_contains($name, 'END') ? now()->addMonth() : now()->subMonth();

            EditableDate::updateOrCreate(
                ['key' => $key],
                ['value' => $value, 'label' => $key]
            );
        }
    }

    /**
     * One fixture set shared by every case: a class with a quiz, a document, a
     * certificate and a party group, so pages fail on their content rather
     * than on emptiness.
     */
    private function seedContest(): array
    {
        Mail::fake();
        Queue::fake();
        Storage::fake('local');

        $this->openEveryWindow();

        $teacher = $this->makeTeacher();
        $class = $this->makeClass($teacher);
        $assignment = $this->makeQuizAssignment($class);

        $class->update([
            'party_token' => 'jeton-fete',
            'may_token' => 'jeton-mai',
        ]);

        $certificate = Certificate::create([
            'school_class_id' => $class->id,
            'url' => 'certificats/'.$class->id.'/certificat.pdf',
            'uid' => 'uid-certificat',
        ]);

        Storage::put($certificate->url, '%PDF-1.4 factice');

        $document = Document::create([
            'title' => 'Document',
            'description' => 'Description',
            'filename' => 'document.pdf',
            'visible' => 1,
            'visible_party' => 1,
            'notification' => 0,
            'sort' => 1,
        ]);

        Storage::put('documents/'.$document->filename, 'contenu');

        PartyGroup::create([
            'name' => 'Groupe A',
            'students' => 10,
            'language' => 'fr',
            'school_class_id' => $class->id,
        ]);

        return [
            'teacher' => $teacher,
            'class' => $class->fresh(),
            'quiz' => $assignment->quiz,
            'assignment' => $assignment,
            'certificate' => $certificate,
            'document' => $document,
        ];
    }

    public static function adminRoutes(): array
    {
        return [
            'liste des quiz' => ['admin.quiz', []],
            'détail quiz' => ['admin.quiz.show', ['quiz']],
            'édition quiz' => ['admin.quiz.edit', ['quiz']],
            'relecture quiz' => ['admin.quiz.review', ['quiz']],
            'aperçu du mail quiz' => ['admin.quiz.review-mail', ['quiz']],
            'codes du quiz' => ['admin.quiz.show.codes', ['quiz']],
            'édition classe' => ['admin.classes.edit', ['class']],
            'édition mail' => ['admin.emails.edit', ['email']],
            'édition lycée' => ['admin.schools.edit', ['school']],
            'édition enseignant' => ['admin.teachers.edit', ['teacher']],
            'édition document' => ['admin.documents.edit', ['document']],
            'fête' => ['admin.party', []],
            'fête par classe' => ['admin.party.class', ['class']],
            'certificats' => ['admin.certificates', []],
            'ajout utilisateur' => ['admin.users.add', []],
        ];
    }

    #[DataProvider('adminRoutes')]
    public function testAdminPagesRenderWithContestData(string $name, array $needs)
    {
        $fixtures = $this->seedContest();

        $this->actingAs($this->admin())
            ->get(route($name, $this->argumentsFor($needs, $fixtures)))
            ->assertOk();
    }

    public static function teacherRoutes(): array
    {
        return [
            'ajout de classe' => ['teacher.classes.add', []],
            'édition de classe' => ['teacher.classes.edit', ['class']],
            'documents' => ['teacher.documents', []],
            'fête' => ['teacher.party', []],
            'fête par classe' => ['teacher.party.class', ['class']],
            'quiz' => ['teacher.quizzes', []],
            'profil' => ['teacher.profile', []],
        ];
    }

    #[DataProvider('teacherRoutes')]
    public function testTeacherPagesRenderWithContestData(string $name, array $needs)
    {
        $fixtures = $this->seedContest();

        $this->actingAs($fixtures['teacher']->user)
            ->get(route($name, $this->argumentsFor($needs, $fixtures)))
            ->assertOk();
    }

    /**
     * The unauthenticated pages, reached by an unguessable token in the URL.
     * They are the ones a teacher actually clicks from an email, and the only
     * ones a stranger can reach at all.
     */
    public function testThePublicTokenPagesRender()
    {
        $fixtures = $this->seedContest();

        $this->get(route('certificate.page', ['certificate_uid' => 'uid-certificat']))->assertOk();
        $this->get(route('external.quiz.show', ['uuid' => $fixtures['assignment']->uuid]))->assertOk();
        $this->get(route('external.classes'))->assertOk();
        $this->get(route('party-response', ['token' => 'jeton-fete', 'status' => 'true']))
            ->assertRedirect();
    }

    /**
     * Guard: nothing that changes state may be reachable by GET.
     *
     * Twelve routes used to be: deleting a class, a quiz, a document or a
     * certificate, and sending mail to every eligible class. A browser
     * prefetch, a crawler or a mistyped URL performs a GET, and GET carries no
     * CSRF token because it is exempt by design. A GET that mails every
     * teacher is worse than a GET that deletes one row.
     *
     * They are now POST (actions) and DELETE (removals). This asserts the
     * property rather than a list, so a new one added tomorrow is caught.
     */
    public function testNothingThatChangesStateIsReachableByGet()
    {
        $offenders = [];

        foreach (app('router')->getRoutes() as $route) {
            $name = (string) $route->getName();

            if (! in_array('GET', $route->methods(), true)) {
                continue;
            }

            // Downloads and exports read; they are safe to repeat and safe to
            // prefetch, so the verb is right.
            if (preg_match('/\.(delete|send|resend|generate)/', $name)
                && ! str_contains($name, 'download')
                && ! str_contains($name, 'export')) {
                $offenders[] = $name.' ['.$route->uri().']';
            }
        }

        sort($offenders);

        $this->assertSame(
            [],
            $offenders,
            "These routes change state or send mail but answer a GET:\n  "
            .implode("\n  ", $offenders)
            ."\nUse POST for actions and DELETE for removals, and render the "
            .'link with <x-action-button> so it carries a CSRF token.'
        );
    }

    /**
     * The verb change is only real if the old one stops working.
     */
    public function testTheOldGetUrlsNoLongerPerformTheAction()
    {
        $fixtures = $this->seedContest();
        $class = $fixtures['class'];

        $this->actingAs($this->admin())
            ->get('/admin/classes/'.$class->id.'/delete')
            ->assertStatus(405);

        $this->assertNotNull(
            SchoolClass::find($class->id),
            'A GET to the old delete URL still removed the class.'
        );
    }

    public function testAClassIsStillDeletableByTheProperVerb()
    {
        $fixtures = $this->seedContest();
        $class = $fixtures['class'];

        $this->actingAs($this->admin())
            ->delete(route('admin.classes.delete', [$class]))
            ->assertRedirect();

        $this->assertNull(SchoolClass::find($class->id));
    }

    private function argumentsFor(array $needs, array $fixtures): array
    {
        $map = [
            'class' => fn () => $fixtures['class']->id,
            'quiz' => fn () => $fixtures['quiz']->id,
            'email' => fn () => EditableEmail::first()->key,
            'school' => fn () => School::first()->id,
            'teacher' => fn () => $fixtures['teacher']->id,
            'document' => fn () => $fixtures['document']->id,
        ];

        $arguments = [];

        foreach ($needs as $need) {
            $arguments[$need] = $map[$need]();
        }

        return $arguments;
    }
}
