<?php

namespace Tests\Feature;

use App\User;
use Illuminate\Auth\Events\Lockout;
use App\Mail\ResetPasswordMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Tests\Concerns\BuildsDomainFixtures;
use Tests\TestCase;

/**
 * The front door, which nothing else in the suite actually opens.
 *
 * Every other test authenticates with actingAs(), and that is precisely the
 * problem: actingAs() sets the user on the guard directly and never runs
 * LoginController, the AuthenticatesUsers trait, the throttle, the session
 * regeneration or the type-based redirect. The whole login path could be
 * broken and the suite would stay green while nobody -- teacher or
 * administrator -- could get in.
 *
 * It is also the path most disturbed by the upgrade: Laravel 7 moved these
 * traits out of the framework and into laravel/ui, and this application
 * carries a custom redirect that branches on User::type.
 */
class AuthenticationTest extends TestCase
{
    use RefreshDatabase, BuildsDomainFixtures;

    private function userWithPassword(string $password, array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'password' => Hash::make($password),
        ], $attributes));
    }

    public function testATeacherCanLogIn()
    {
        $teacher = $this->makeTeacher();
        $teacher->user->update(['password' => Hash::make('motdepasse-solide')]);

        $this->post(route('login.post'), [
            'email' => $teacher->user->email,
            'password' => 'motdepasse-solide',
        ])->assertRedirect(route('login.redirect'));

        $this->assertAuthenticatedAs($teacher->user->fresh());
    }

    public function testTheWrongPasswordIsRejected()
    {
        $teacher = $this->makeTeacher();
        $teacher->user->update(['password' => Hash::make('motdepasse-solide')]);

        $this->from(route('login'))
            ->post(route('login.post'), [
                'email' => $teacher->user->email,
                'password' => 'mauvais-mot-de-passe',
            ])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function testAnUnknownAddressIsRejected()
    {
        $this->from(route('login'))
            ->post(route('login.post'), [
                'email' => 'inconnu@lycee.lu',
                'password' => 'peu-importe',
            ])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    /**
     * The redirect branches on User::type, so a broken branch sends an
     * administrator to the teacher area or bounces them back to the login
     * form -- logged in, but with nowhere to go.
     */
    public function testATeacherLandsInTheTeacherSection()
    {
        $teacher = $this->makeTeacher();

        $this->actingAs($teacher->user)
            ->get(route('login.redirect'))
            ->assertRedirect(route('teacher.classes'));
    }

    public function testAnAdministratorLandsInTheAdminSection()
    {
        // teacher_id must be null. The User factory attaches a Teacher to every
        // user it makes, and RedirectIfAuthenticated tests `teacher !== null`
        // BEFORE it tests the admin type -- so a user carrying both would be
        // sent to the teacher section whatever its type says. Real
        // administrators have no teacher record; the factory default does not
        // model one.
        $admin = $this->userWithPassword('peu-importe', [
            'type' => User::TYPE_ADMIN,
            'teacher_id' => null,
        ]);

        $this->actingAs($admin)
            ->get(route('login.redirect'))
            ->assertRedirect(route('admin.classes'));
    }

    /**
     * Session fixation: Laravel regenerates the session id on login. Asserted
     * because it is invisible when it stops happening.
     */
    public function testTheSessionIdIsRegeneratedOnLogin()
    {
        $teacher = $this->makeTeacher();
        $teacher->user->update(['password' => Hash::make('motdepasse-solide')]);

        $this->get(route('login'));
        $before = session()->getId();

        $this->post(route('login.post'), [
            'email' => $teacher->user->email,
            'password' => 'motdepasse-solide',
        ]);

        $this->assertNotSame($before, session()->getId());
    }

    /**
     * The only brute-force protection on the contest's front door.
     */
    public function testRepeatedFailuresAreThrottled()
    {
        Event::fake([Lockout::class]);

        $teacher = $this->makeTeacher();

        for ($attempt = 0; $attempt < 6; $attempt++) {
            $this->post(route('login.post'), [
                'email' => $teacher->user->email,
                'password' => 'mauvais-mot-de-passe',
            ]);
        }

        Event::assertDispatched(Lockout::class);
        $this->assertGuest();
    }

    public function testAnAuthenticatedUserCanLogOut()
    {
        $teacher = $this->makeTeacher();

        $this->actingAs($teacher->user)
            // A GET route in this application, not the POST Laravel scaffolds.
            ->get(route('logout'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    /**
     * Password recovery crossed the same laravel/ui move as login, and it is
     * the only way back in for a teacher who has forgotten theirs -- mid
     * contest, with a class already registered.
     */
    public function testAResetLinkIsSentForAKnownAddress()
    {
        Mail::fake();

        $teacher = $this->makeTeacher();

        $this->post(route('login.password.recover.post'), ['email' => $teacher->user->email])
            ->assertSessionHas('status');

        // User::sendPasswordResetNotification() queues a Mailable of its own
        // instead of sending Laravel's ResetPassword notification, so this is a
        // Mail assertion, not a Notification one.
        Mail::assertQueued(ResetPasswordMail::class);
    }

    public function testNoResetLinkIsSentForAnUnknownAddress()
    {
        Mail::fake();

        $this->from(route('login.password.recover'))
            ->post(route('login.password.recover.post'), ['email' => 'inconnu@lycee.lu'])
            ->assertSessionHasErrors('email');

        Mail::assertNothingQueued();
    }

    public function testAPasswordCanActuallyBeReset()
    {
        $teacher = $this->makeTeacher();
        $user = $teacher->user;
        $token = Password::broker()->createToken($user);

        $this->post(route('login.password.reset.post'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'nouveau-mot-de-passe',
            'password_confirmation' => 'nouveau-mot-de-passe',
        ])->assertRedirect();

        $this->assertTrue(
            Hash::check('nouveau-mot-de-passe', $user->fresh()->password),
            'The reset form reported success but the password never changed.'
        );
    }

    public function testAnExpiredOrForgedTokenCannotResetAPassword()
    {
        $teacher = $this->makeTeacher();
        $user = $teacher->user;
        $before = $user->password;

        $this->from(route('login.password.reset', ['token' => 'jeton-invalide']))
            ->post(route('login.password.reset.post'), [
                'token' => 'jeton-invalide',
                'email' => $user->email,
                'password' => 'nouveau-mot-de-passe',
                'password_confirmation' => 'nouveau-mot-de-passe',
            ])
            ->assertSessionHasErrors();

        $this->assertSame($before, $user->fresh()->password);
    }
}
