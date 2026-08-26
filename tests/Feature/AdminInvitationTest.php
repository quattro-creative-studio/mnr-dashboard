<?php

namespace Tests\Feature;

use App\Mail\AdminInvitationMail;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Tests\Concerns\BuildsDomainFixtures;
use Tests\TestCase;

/**
 * Inviting an administrator instead of typing a password for them.
 *
 * The account is created with a random password nobody ever learns and the
 * invitee chooses their own through the reset form that already exists. That
 * removes the step where one person types a password and sends it over a
 * channel they do not control.
 */
class AdminInvitationTest extends TestCase
{
    use RefreshDatabase, BuildsDomainFixtures;

    private function admin(string $email = 'admin@missionnichtrauchen.lu'): User
    {
        // teacher_id null, or RedirectIfAuthenticated diverts to the teacher
        // section before the admin routes are reached.
        return User::factory()->create([
            'email' => $email,
            'type' => User::TYPE_ADMIN,
            'teacher_id' => null,
        ]);
    }

    public function testInvitingCreatesTheAccountAndQueuesTheMail()
    {
        Mail::fake();

        $this->actingAs($this->admin())
            ->post(route('admin.users.add.post'), ['email' => 'nouveau@missionnichtrauchen.lu'])
            ->assertRedirect(route('admin.users'));

        $invited = User::where('email', 'nouveau@missionnichtrauchen.lu')->first();

        $this->assertNotNull($invited);
        $this->assertSame(User::TYPE_ADMIN, $invited->type);
        $this->assertNull($invited->teacher_id, 'An admin with a teacher record never reaches the admin area.');

        Mail::assertQueued(AdminInvitationMail::class);
    }

    /**
     * The point of the whole change: no password crosses a channel the sender
     * does not control, and the account is unusable until the invitee acts.
     */
    public function testTheInvitedAccountCannotBeLoggedIntoBeforeItIsActivated()
    {
        Mail::fake();

        $this->actingAs($this->admin())
            ->post(route('admin.users.add.post'), ['email' => 'nouveau@missionnichtrauchen.lu']);

        $invited = User::where('email', 'nouveau@missionnichtrauchen.lu')->firstOrFail();

        foreach (['', 'password', 'motdepasse-solide'] as $guess) {
            $this->assertFalse(
                Hash::check($guess, $invited->password),
                'The generated password is guessable.'
            );
        }
    }

    public function testTheInvitationTokenSetsThePassword()
    {
        Mail::fake();

        $this->actingAs($this->admin())
            ->post(route('admin.users.add.post'), ['email' => 'nouveau@missionnichtrauchen.lu']);

        $invited = User::where('email', 'nouveau@missionnichtrauchen.lu')->firstOrFail();
        $token = Password::broker('invitations')->createToken($invited);

        // The reset form is behind the "guest" middleware, and the session is
        // still the inviting administrator's. Without logging out the request
        // is redirected away and the test would assert on a redirect that never
        // touched the reset logic.
        Auth::logout();
        $this->flushSession();

        $this->post(route('login.password.reset.post'), [
            'token' => $token,
            'email' => $invited->email,
            'password' => 'mon-mot-de-passe',
            'password_confirmation' => 'mon-mot-de-passe',
        ])->assertRedirect();

        $this->assertTrue(Hash::check('mon-mot-de-passe', $invited->fresh()->password));
    }

    /**
     * Seven days rather than the sixty minutes a recovery gets: an invitation
     * arrives during a meeting and is opened the next morning.
     */
    public function testTheInvitationBrokerOutlivesAPasswordRecovery()
    {
        $this->assertSame(60 * 24 * 7, config('auth.passwords.invitations.expire'));
        $this->assertSame(60, config('auth.passwords.users.expire'));
    }

    public function testAnInvitationCanBeResent()
    {
        Mail::fake();

        $invited = $this->admin('invite@missionnichtrauchen.lu');

        $this->actingAs($this->admin())
            ->post(route('admin.users.resend', [$invited]))
            ->assertRedirect(route('admin.users'));

        Mail::assertQueued(AdminInvitationMail::class);
    }

    public function testATeacherAccountCannotBeInvitedOrDeletedThroughTheAdminRoutes()
    {
        $teacher = $this->makeTeacher();

        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('admin.users.resend', [$teacher->user]))
            ->assertNotFound();

        $this->actingAs($admin)
            ->delete(route('admin.users.delete', [$teacher->user]))
            ->assertNotFound();

        $this->assertNotNull(User::find($teacher->user->id));
    }

    public function testAnAdministratorCanBeDeleted()
    {
        $me = $this->admin();
        $other = $this->admin('autre@missionnichtrauchen.lu');

        $this->actingAs($me)
            ->delete(route('admin.users.delete', [$other]))
            ->assertRedirect(route('admin.users'));

        $this->assertNull(User::find($other->id));
    }

    /**
     * Two mistakes nobody intends, refused in the controller rather than only
     * hidden in the view.
     */
    public function testYouCannotDeleteYourOwnAccount()
    {
        $me = $this->admin();
        $this->admin('autre@missionnichtrauchen.lu');

        $this->actingAs($me)
            ->delete(route('admin.users.delete', [$me]))
            ->assertSessionHas('error');

        $this->assertNotNull(User::find($me->id));
    }

    public function testTheLastAdministratorCannotBeDeleted()
    {
        $me = $this->admin();
        $other = $this->admin('autre@missionnichtrauchen.lu');

        // Down to one, then try to remove that one from its own session.
        $other->delete();

        $this->actingAs($me)
            ->delete(route('admin.users.delete', [$me]))
            ->assertSessionHas('error');

        $this->assertSame(1, User::where('type', User::TYPE_ADMIN)->count());
    }

    /**
     * The users list has to distinguish an account still waiting for its
     * invitation from an active one. It cannot read the password column: an
     * invited account already has one, a random string nobody knows.
     */
    public function testAnInvitedAccountIsMarkedPendingUntilItsPasswordIsChosen()
    {
        Mail::fake();

        $this->actingAs($this->admin())
            ->post(route('admin.users.add.post'), ['email' => 'nouveau@missionnichtrauchen.lu']);

        $invited = User::where('email', 'nouveau@missionnichtrauchen.lu')->firstOrFail();

        $this->assertNull($invited->password_set_at, 'An unactivated account looks active.');

        $token = Password::broker('invitations')->createToken($invited);
        Auth::logout();
        $this->flushSession();

        $this->post(route('login.password.reset.post'), [
            'token' => $token,
            'email' => $invited->email,
            'password' => 'mon-mot-de-passe',
            'password_confirmation' => 'mon-mot-de-passe',
        ]);

        $this->assertNotNull(
            $invited->fresh()->password_set_at,
            'Choosing a password did not mark the account active.'
        );
    }

    public function testATeacherRegisteringIsActiveImmediately()
    {
        // A teacher types their own password at registration, so they are never
        // in the pending state an invitation creates.
        $teacher = $this->makeTeacher();

        $this->assertNotNull($teacher->user->fresh()->password_set_at);
    }

    /**
     * Resending is never blocked. For a pending account it repeats the
     * invitation; for an active one it sends a fresh set-password link, which
     * takes nothing away -- the current password keeps working until the link
     * is used.
     */
    public function testResendingToAnActiveAccountLeavesItsPasswordWorking()
    {
        Mail::fake();

        $active = $this->admin('actif@missionnichtrauchen.lu');
        $active->forceFill([
            'password' => Hash::make('mot-de-passe-actuel'),
            'password_set_at' => now(),
        ])->save();

        $this->actingAs($this->admin())
            ->post(route('admin.users.resend', [$active]))
            ->assertRedirect(route('admin.users'));

        Mail::assertQueued(AdminInvitationMail::class);

        $this->assertTrue(
            Hash::check('mot-de-passe-actuel', $active->fresh()->password),
            'Re-sending a link invalidated the existing password.'
        );
    }
}
