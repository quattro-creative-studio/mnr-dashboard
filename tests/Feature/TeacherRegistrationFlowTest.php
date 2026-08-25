<?php

namespace Tests\Feature;

use App\EditableDate;
use App\Mail\CustomEmail;
use App\School;
use App\SchoolClass;
use App\Teacher;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\BuildsDomainFixtures;
use Tests\TestCase;

/**
 * The four routes the contest cannot run without.
 *
 * Until now the suite exercised no write route at all: 26 of them, zero
 * coverage, while the whole request-validation-persistence path crossed eight
 * major versions. These four are the ones where a regression ends the edition
 * rather than inconveniencing an administrator -- a teacher who cannot
 * register, or cannot enter a class, has no way around it.
 */
class TeacherRegistrationFlowTest extends TestCase
{
    use RefreshDatabase, BuildsDomainFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        // Registration is gated on two EditableDates. A fresh migrate seeds only
        // 10 of the 23 keys and TEACHER_INSCRIPTION_END is not among them, so
        // the window has to be opened explicitly or every write 302s away.
        $this->openRegistrationWindow();

        Mail::fake();
    }

    private function openRegistrationWindow(): void
    {
        foreach ([EditableDate::TEACHER_INSCRIPTION_START => now()->subMonth(),
                  EditableDate::TEACHER_INSCRIPTION_END => now()->addMonth()] as $key => $value) {
            // label is NOT NULL with no default, and a fresh migrate seeds only
            // 10 of the 23 declared keys, so these rows may have to be created
            // outright rather than updated.
            EditableDate::updateOrCreate(['key' => $key], ['value' => $value, 'label' => $key]);
        }
    }

    private function registrationPayload(array $overrides = []): array
    {
        return array_merge([
            'teacher_salutation' => 1,
            'teacher_first_name' => 'Camille',
            'teacher_last_name' => 'Weber',
            'teacher_email' => 'camille.weber@lycee.lu',
            'teacher_password' => 'motdepasse-solide',
            'teacher_password_confirmation' => 'motdepasse-solide',
            'teacher_phone' => '+352 621 000 000',
            'data_protection' => '1',
        ], $overrides);
    }

    public function testATeacherCanRegisterAndIsLoggedIn()
    {
        $response = $this->post(route('teacher.registerPost'), $this->registrationPayload());

        $response->assertRedirect(route('teacher.classes'));

        $user = User::where('email', 'camille.weber@lycee.lu')->first();

        $this->assertNotNull($user, 'The registration form accepted the request but stored no user.');
        $this->assertSame(User::TYPE_TEACHER, $user->type);
        $this->assertNotNull($user->teacher, 'A user was created without its Teacher record.');
        $this->assertSame('Camille', $user->teacher->first_name);
        $this->assertAuthenticatedAs($user);
    }

    /**
     * The password must be hashed, never stored as typed. Asserted explicitly
     * because Laravel moved the hashing default twice across this upgrade.
     */
    public function testTheRegistrationPasswordIsHashed()
    {
        $this->post(route('teacher.registerPost'), $this->registrationPayload());

        $user = User::where('email', 'camille.weber@lycee.lu')->firstOrFail();

        $this->assertNotSame('motdepasse-solide', $user->password);
        $this->assertTrue(password_verify('motdepasse-solide', $user->password));
    }

    public function testRegistrationQueuesTheConfirmationEmail()
    {
        $this->post(route('teacher.registerPost'), $this->registrationPayload());

        Mail::assertQueued(CustomEmail::class);
    }

    public function testRegistrationRejectsAnAlreadyUsedEmail()
    {
        $existing = $this->makeTeacher(['email' => 'camille.weber@lycee.lu']);

        $this->from(route('teacher.register'))
            ->post(route('teacher.registerPost'), $this->registrationPayload())
            ->assertSessionHasErrors('teacher_email');

        $this->assertSame(1, User::where('email', 'camille.weber@lycee.lu')->count());
        $this->assertGuest();
    }

    public function testRegistrationRejectsAnUnconfirmedPassword()
    {
        $this->from(route('teacher.register'))
            ->post(route('teacher.registerPost'), $this->registrationPayload([
                'teacher_password_confirmation' => 'autre-chose',
            ]))
            ->assertSessionHasErrors('teacher_password');

        $this->assertGuest();
    }

    /**
     * The data protection checkbox is a legal requirement, not decoration.
     */
    public function testRegistrationRejectsAMissingDataProtectionConsent()
    {
        $payload = $this->registrationPayload();
        unset($payload['data_protection']);

        $this->from(route('teacher.register'))
            ->post(route('teacher.registerPost'), $payload)
            ->assertSessionHasErrors('data_protection');

        $this->assertSame(0, User::where('email', 'camille.weber@lycee.lu')->count());
    }

    public function testATeacherCanAddAClass()
    {
        $teacher = $this->makeTeacher();
        $school = School::first();

        $this->actingAs($teacher->user)
            ->post(route('teacher.classes.add.post'), [
                'class_name' => '5e B',
                'class_students' => 22,
                'class_school' => $school->id,
            ])
            ->assertRedirect(route('teacher.classes'));

        $class = SchoolClass::where('name', '5e B')->first();

        $this->assertNotNull($class, 'The class form was accepted but nothing was stored.');
        $this->assertSame($teacher->id, $class->teacher_id);
        $this->assertSame(22, $class->students);
        $this->assertSame($school->id, $class->school_id);
    }

    /**
     * The window is the whole point of the date-driven design: outside it, no
     * class may be created. classesAddPost redirects rather than erroring, so
     * the only observable difference is whether a row appeared.
     */
    public function testAClassCannotBeAddedOutsideTheRegistrationWindow()
    {
        EditableDate::updateOrCreate(
            ['key' => EditableDate::TEACHER_INSCRIPTION_END],
            ['value' => now()->subDay()]
        );

        $teacher = $this->makeTeacher();

        $this->actingAs($teacher->user)
            ->post(route('teacher.classes.add.post'), [
                'class_name' => 'Classe hors délai',
                'class_students' => 20,
                'class_school' => School::first()->id,
            ])
            ->assertRedirect(route('teacher.classes'));

        $this->assertSame(0, SchoolClass::where('name', 'Classe hors délai')->count());
    }

    public function testAClassRejectsAnImpossibleStudentCount()
    {
        $teacher = $this->makeTeacher();

        $this->actingAs($teacher->user)
            ->from(route('teacher.classes'))
            ->post(route('teacher.classes.add.post'), [
                'class_name' => 'Classe trop grande',
                'class_students' => 100,
                'class_school' => School::first()->id,
            ])
            ->assertSessionHasErrors('class_students');

        $this->assertSame(0, SchoolClass::where('name', 'Classe trop grande')->count());
    }

    public function testATeacherCanEditTheirOwnClass()
    {
        $teacher = $this->makeTeacher();
        $class = $this->makeClass($teacher, ['name' => 'Avant', 'students' => 18]);

        $this->actingAs($teacher->user)
            ->post(route('teacher.classes.edit.post', [$class]), [
                // The edit request names its fields 'name'/'students' where the
                // add request uses 'class_name'/'class_students'.
                'name' => 'Après',
                'students' => 21,
            ])
            ->assertRedirect(route('teacher.classes'));

        $class->refresh();

        $this->assertSame('Après', $class->name);
        $this->assertSame(21, $class->students);
    }

    /**
     * Ownership is checked inline in the controller rather than by a policy, so
     * it is exactly the kind of guard an upgrade can drop without noticing.
     */
    public function testATeacherCannotEditSomeoneElsesClass()
    {
        $owner = $this->makeTeacher(['email' => 'proprietaire@lycee.lu']);
        $intruder = $this->makeTeacher(['email' => 'intrus@lycee.lu']);
        $class = $this->makeClass($owner, ['name' => 'Classe du collègue', 'students' => 18]);

        $this->actingAs($intruder->user)
            ->post(route('teacher.classes.edit.post', [$class]), [
                'name' => 'Détournée',
                'students' => 30,
            ])
            ->assertForbidden();

        $class->refresh();

        $this->assertSame('Classe du collègue', $class->name);
        $this->assertSame(18, $class->students);
    }
}
