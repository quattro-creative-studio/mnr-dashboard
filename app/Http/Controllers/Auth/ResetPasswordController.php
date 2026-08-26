<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\ResetsPasswords;

class ResetPasswordController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Password Reset Controller
    |--------------------------------------------------------------------------
    |
    | This controller is responsible for handling password reset requests
    | and uses a simple trait to include this behavior. You're free to
    | explore this trait and override any methods you wish to tweak.
    |
    */

    // Aliased because the override below must call the trait's version.
    // A trait is flattened into the class, so parent:: would look in
    // Controller -- which has no resetPassword() -- rather than in the trait.
    use ResetsPasswords {
        resetPassword as traitResetPassword;
    }

    /**
     * Where to redirect users after resetting their password.
     *
     * @var string
     */
    protected $redirectTo = '/login';

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest');
    }

    /**
     * Stamp password_set_at alongside the new password.
     *
     * This is the moment an invited administrator becomes a real one: until
     * they arrive here, their password is the random string the invitation
     * generated. The users list reads this column to tell a pending invitation
     * from an active account.
     *
     * @param  \Illuminate\Contracts\Auth\CanResetPassword|\App\User  $user
     * @param  string  $password
     * @return void
     */
    protected function resetPassword($user, $password)
    {
        $user->forceFill(['password_set_at' => now()])->save();

        $this->traitResetPassword($user, $password);
    }
}
