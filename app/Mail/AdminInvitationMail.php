<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Modelled on ResetPasswordMail rather than on Laravel's notification, because
 * that is how this application already sends its reset links: User overrides
 * sendPasswordResetNotification() to queue a Mailable of its own.
 *
 * Deliberately not routed through the EditableEmail engine. That engine exists
 * so administrators can edit the contest's teacher-facing mail, and its
 * placeholders (%PROF%, %NOM_CLASSE%) have no meaning here. An invitation is
 * plumbing, not contest content.
 */
class AdminInvitationMail extends Mailable implements ShouldQueue {
    use Queueable, SerializesModels;

    /** @var string */
    public $token;

    /** @var string */
    public $email;

    /** @var string */
    public $invitedBy;

    public function __construct(string $token, string $email, string $invitedBy) {
        $this->token = $token;
        $this->email = $email;
        $this->invitedBy = $invitedBy;
    }

    public function build() {
        return $this
            ->subject('Votre accès à l\'administration «Mission Nichtrauchen»')
            ->from(config('mail.from.address'), config('mail.from.name'))
            ->replyTo(config('mail.reply_to.address'))
            ->view('emails.admin-invitation')
            ->with([
                'token' => $this->token,
                'email' => $this->email,
                'invitedBy' => $this->invitedBy,
            ]);
    }
}
