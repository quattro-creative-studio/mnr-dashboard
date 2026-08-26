<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminUserCreateRequest;
use App\Mail\AdminInvitationMail;
use App\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;

class UserController extends Controller {

    public function users() {
        return view('admin.users')->with([
            'users' => User::query()
                ->where('type', User::TYPE_ADMIN)
                ->orderBy('email')
                ->get(),
        ]);
    }

    public function usersAdd() {
        return view('admin.users-add');
    }

    /**
     * Invite an administrator rather than setting a password on their behalf.
     *
     * The account is created with a random password nobody ever learns, and the
     * invitee chooses their own through the existing reset form. That removes
     * the step where one person types a password and sends it over a channel
     * they do not control.
     *
     * teacher_id stays null: RedirectIfAuthenticated checks for a teacher
     * record BEFORE it checks the admin type, so an administrator carrying one
     * would be diverted to the teacher section and never reach the admin area.
     */
    public function usersAddPost(AdminUserCreateRequest $request) {
        $data = $request->validated();

        $user = User::create([
            'email' => $data['email'],
            'password' => Hash::make(Str::random(64)),
            'type' => User::TYPE_ADMIN,
        ]);

        $this->sendInvitation($user);

        Log::info(Auth::user()->email.' invited a new admin: '.$user->email);
        Session::flash('message', 'Invitation envoyée à '.$user->email);

        return redirect()->route('admin.users');
    }

    /**
     * Re-send an invitation whose link has expired or gone astray.
     *
     * Without this, a lapsed invitation can only be fixed by deleting the
     * account and creating it again.
     */
    public function usersResend(User $user) {
        abort_unless($user->type === User::TYPE_ADMIN, 404);

        $this->sendInvitation($user);

        Log::info(Auth::user()->email.' re-sent an invitation to: '.$user->email);
        Session::flash('message', 'Invitation renvoyée à '.$user->email);

        return redirect()->route('admin.users');
    }

    /**
     * Remove an administrator.
     *
     * Two guards, and both matter. Deleting yourself is a mistake nobody
     * intends; deleting the last administrator locks everyone out of the
     * administration with no way back except the artisan command.
     */
    public function usersDelete(User $user) {
        abort_unless($user->type === User::TYPE_ADMIN, 404);

        if ($user->id === Auth::id()) {
            Session::flash('error', 'Vous ne pouvez pas supprimer votre propre compte.');

            return redirect()->route('admin.users');
        }

        if (User::query()->where('type', User::TYPE_ADMIN)->count() <= 1) {
            Session::flash('error', 'Impossible de supprimer le dernier administrateur.');

            return redirect()->route('admin.users');
        }

        $email = $user->email;
        $user->delete();

        Log::info(Auth::user()->email.' deleted admin: '.$email);
        Session::flash('message', 'Administrateur supprimé : '.$email);

        return redirect()->route('admin.users');
    }

    /**
     * Issue a token on the "invitations" broker, which shares the reset token
     * table but lives for seven days instead of sixty minutes.
     */
    private function sendInvitation(User $user): void {
        $token = Password::broker('invitations')->createToken($user);

        Mail::to($user->email)->queue(
            new AdminInvitationMail($token, $user->email, Auth::user()->email)
        );
    }
}
