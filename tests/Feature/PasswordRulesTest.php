<?php

namespace Tests\Feature;

use App\Http\Controllers\Auth\ResetPasswordController;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    private function resetRules(): array
    {
        $method = new ReflectionMethod(ResetPasswordController::class, 'rules');
        $method->setAccessible(true);

        return $method->invoke(app(ResetPasswordController::class));
    }

    public function testTheFrameworkResetRuleMinimumIsEight()
    {
        $this->assertContains(
            'min:8',
            explode('|', $this->resetRules()['password']),
            'The framework reset rule changed again. Re-check the application rules match.'
        );
    }

    public function testTheApplicationsOwnRulesMatchTheFrameworkMinimum()
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

            $this->assertContains(
                'min:8',
                explode('|', $rules[$field]),
                "{$class} sets a different minimum for {$field} than the password reset "
                ."path does. The two must agree, or a teacher can register with a password "
                ."they cannot later reset to."
            );
        }
    }
}
