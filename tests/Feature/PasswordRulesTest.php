<?php

namespace Tests\Feature;

use App\Http\Controllers\Auth\ResetPasswordController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use ReflectionMethod;
use Tests\Concerns\BuildsDomainFixtures;
use Tests\TestCase;

/**
 * Characterisation: minimum password length.
 *
 * The application sets 'min:6' explicitly in its own form requests, but
 * ResetPasswordController uses the framework's ResetsPasswords trait with no
 * override, so its rule comes from Laravel itself. Laravel 5.8 raises that
 * default from 6 to 8.
 *
 * The consequence is user visible rather than fatal: a teacher resetting their
 * password can no longer choose a 6 or 7 character one, while registration and
 * profile update still accept them because those rules are explicit. The two
 * paths silently disagree after the hop.
 *
 * UPDATED at the 8.0 hop: Laravel 8 replaced the hardcoded 'min:8' string with
 * a Rules\Password::defaults() object, so this now asserts BEHAVIOUR -- seven
 * characters refused, eight accepted -- rather than rule syntax. That survives
 * whatever representation the framework picks next.
 *
 * DECIDED at the 5.8 hop: the application's own rules were aligned UP to
 * min:8 rather than forcing the framework back down to 6. Six characters is
 * weak, and the alternative would have kept a real defect -- registration
 * accepting a password that a later reset refuses.
 *
 * No teacher is locked out: validation applies when a password is set, never
 * when one is checked, so existing passwords keep working untouched.
 */
class PasswordRulesTest extends TestCase
{
    use RefreshDatabase, BuildsDomainFixtures;

    /**
     * Run a candidate password through a rule set and report whether it passes.
     * Asserting behaviour rather than rule syntax: Laravel has expressed this
     * minimum as 'min:6', then 'min:8', and from 8.0 as a Rules\Password object.
     * All three mean the same thing to a teacher, and only behaviour survives
     * the next change of representation.
     */
    private function passes(array $rules, string $field, string $password): bool
    {
        return Validator::make(
            [$field => $password, $field.'_confirmation' => $password],
            [$field => $rules[$field]]
        )->passes();
    }

    private function resetRules(): array
    {
        $method = new ReflectionMethod(ResetPasswordController::class, 'rules');
        $method->setAccessible(true);

        return $method->invoke(app(ResetPasswordController::class));
    }

    public function testPasswordResetRejectsSevenCharactersAndAcceptsEight()
    {
        $rules = $this->resetRules();

        $this->assertFalse($this->passes($rules, 'password', 'abcdefg'));
        $this->assertTrue($this->passes($rules, 'password', 'abcdefgh'));
    }

    public function testTheApplicationsOwnRulesAgreeWithTheResetPath()
    {
        // ProfileUpdateRequest::rules() builds a unique:users rule from the
        // authenticated user, so it needs a session to be readable at all.
        $this->actingAs($this->makeTeacher()->user);

        $explicit = [
            \App\Http\Requests\ProfileUpdateRequest::class => 'password',
            \App\Http\Requests\AdminUserCreateRequest::class => 'password',
            \App\Http\Requests\TeacherRegisterRequest::class => 'teacher_password',
        ];

        foreach ($explicit as $class => $field) {
            $rules = (new $class)->rules();

            $this->assertFalse(
                $this->passes($rules, $field, 'abcdefg'),
                "{$class} accepts a 7 character password that the reset path refuses. "
                ."A teacher could register with a password they cannot later reset to."
            );
            $this->assertTrue(
                $this->passes($rules, $field, 'abcdefgh'),
                "{$class} refuses 8 characters, which the reset path accepts."
            );
        }
    }
}
