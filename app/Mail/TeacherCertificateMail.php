<?php

namespace App\Mail;

use App\PlaceHolder;
use App\SchoolClass;
use App\Teacher;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class TeacherCertificateMail extends Mailable {
    use Queueable, SerializesModels;

    /**
     * @var Teacher
     */
    private $teacher;
    /**
     * @var SchoolClass
     */
    private $schoolClass;

    /**
     * Create a new message instance.
     *
     * @param Teacher $teacher
     * @param SchoolClass $schoolClass
     */
    public function __construct( $teacher, $schoolClass) {
        $this->teacher = $teacher;
        $this->schoolClass = $schoolClass;

    }

    /**
     * Build the message.
     *
     * @return $this
     * @throws \Exception
     */
    public function build() {
        return $this
            ->subject("Mission Nichtrauchen | Fin de l'édition " . (date('Y') - 1) . "-" . date('Y'))
            ->from(config('mail.from.address'), config('mail.from.name'))
            ->replyTo(config('mail.reply_to.address'))
            ->view('emails.teacher-certificate')
            ->with([
                'certificate' => $this->schoolClass->certificate,
                'teacher' => $this->teacher
            ]);
    }
}
