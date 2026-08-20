<?php

namespace App\Mail;

use App\SchoolClass;
use App\Teacher;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class TeacherUpdatedClassMail extends Mailable {
    use Queueable, SerializesModels;
    /**
     * @var array
     */
    private $oldData;
    /**
     * @var array
     */
    private $newData;
    /**
     * @var Teacher
     */
    private $teacher;

    /**
     * Create a new message instance.
     *
     * @param Teacher $teacher
     * @param array $oldData
     * @param array $newData
     */
    public function __construct(Teacher $teacher, array $oldData, array $newData) {
        $this->oldData = $oldData;
        $this->newData = $newData;
        $this->teacher = $teacher;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build() {
        return $this
            ->subject('Enseignant a changé une classe')
            ->from(config('mail.from.address'), config('mail.from.name'))
            ->replyTo(config('mail.reply_to.address'))
            ->view('emails.teacher-updated-class')
            ->with([
                'teacher' => $this->teacher,
                'old' => $this->oldData,
                'new' => $this->newData,
            ]);
    }
}
