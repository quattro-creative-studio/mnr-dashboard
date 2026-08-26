<?php

namespace Tests\Feature;

use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\BuildsDomainFixtures;
use Tests\TestCase;

/**
 * admin:create is how a fresh server gets its first way in, so the account it
 * makes has to be one that can actually reach the admin area.
 */
class AdminCreateCommandTest extends TestCase
{
    use RefreshDatabase, BuildsDomainFixtures;

    public function testItCreatesAnAdministrator()
    {
        $this->artisan('admin:create', ['email' => 'admin@missionnichtrauchen.lu'])
            ->expectsQuestion('Mot de passe (masqué)', 'motdepasse-solide')
            ->expectsQuestion('Confirmer le mot de passe', 'motdepasse-solide')
            ->assertExitCode(0);

        $admin = User::where('email', 'admin@missionnichtrauchen.lu')->first();

        $this->assertNotNull($admin);
        $this->assertSame(User::TYPE_ADMIN, $admin->type);
        $this->assertTrue(Hash::check('motdepasse-solide', $admin->password));
    }

    /**
     * The subtlety that makes the difference between a working account and one
     * that logs in and lands in the wrong section: RedirectIfAuthenticated
     * checks for a teacher record before it checks the admin type.
     */
    public function testTheAdministratorHasNoTeacherRecordAndReachesTheAdminArea()
    {
        $this->artisan('admin:create', ['email' => 'admin@missionnichtrauchen.lu'])
            ->expectsQuestion('Mot de passe (masqué)', 'motdepasse-solide')
            ->expectsQuestion('Confirmer le mot de passe', 'motdepasse-solide')
            ->assertExitCode(0);

        $admin = User::where('email', 'admin@missionnichtrauchen.lu')->firstOrFail();

        $this->assertNull($admin->teacher_id);

        $this->actingAs($admin)
            ->get(route('login.redirect'))
            ->assertRedirect(route('admin.classes'));
    }

    public function testItRefusesAnAddressAlreadyInUse()
    {
        $teacher = $this->makeTeacher(['email' => 'occupe@lycee.lu']);

        $this->artisan('admin:create', ['email' => 'occupe@lycee.lu'])
            ->assertExitCode(1);

        $this->assertSame(1, User::where('email', 'occupe@lycee.lu')->count());
        $this->assertSame(User::TYPE_TEACHER, $teacher->user->fresh()->type);
    }

    public function testItRefusesMismatchedPasswords()
    {
        $this->artisan('admin:create', ['email' => 'admin@missionnichtrauchen.lu'])
            ->expectsQuestion('Mot de passe (masqué)', 'motdepasse-solide')
            ->expectsQuestion('Confirmer le mot de passe', 'autre-chose')
            ->assertExitCode(1);

        $this->assertSame(0, User::where('email', 'admin@missionnichtrauchen.lu')->count());
    }

    public function testItRefusesAShortPassword()
    {
        $this->artisan('admin:create', ['email' => 'admin@missionnichtrauchen.lu'])
            ->expectsQuestion('Mot de passe (masqué)', 'court')
            ->assertExitCode(1);

        $this->assertSame(0, User::where('email', 'admin@missionnichtrauchen.lu')->count());
    }
}
