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
 * This test is expected to go red at 5.8. When it does, decide deliberately:
 * either align the explicit rules up to 8 (better) or override the trait back
 * down to 6 (worse, but consistent). Do not just update the number here.
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

    public function testTheFrameworkResetRuleMinimumIsSix()
    {
        $this->assertContains(
            'min:6',
            explode('|', $this->resetRules()['password']),
            'Laravel 5.8 raises this default to min:8. If this failed, read the docblock.'
        );
    }

    public function testTheApplicationsOwnRulesStillAllowSix()
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
                'min:6',
                explode('|', $rules[$field]),
                "{$class} no longer requires min:6 for {$field}."
            );
        }
    }
}
