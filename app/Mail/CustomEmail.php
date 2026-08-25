<?php

namespace App\Mail;

use App\EditableEmail;
use App\PlaceHolder;
use App\SchoolClass;
use App\Teacher;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class CustomEmail extends Mailable {
    use Queueable, SerializesModels;
    /**
     * @var EditableEmail
     */
    private $editableEmail;
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
     * @param EditableEmail $editableEmail
     * @param Teacher $teacher
     * @param SchoolClass $schoolClass
     */
    public function __construct(EditableEmail $editableEmail, $teacher, $schoolClass) {
        $this->editableEmail = $editableEmail;
        $this->teacher = $teacher;
        $this->schoolClass = $schoolClass;

        $this->editableEmail->setSent($this->teacher->user);
        if($this->schoolClass != null) {
            $this->editableEmail->setSentToClass($this->schoolClass);
        }
    }

    /**
     * Build the message.
     *
     * @return $this
     * @throws \Exception
     */
    public function build() {
        return $this
            ->subject($this->editableEmail->subject)
            ->from(config('mail.from.address'), config('mail.from.name'))
            ->replyTo(config('mail.reply_to.address'))
            ->view('emails.custom')
            ->with([
                'text' => PlaceHolder::replaceAll($this->editableEmail->text, $this->teacher, $this->schoolClass),
                'subject' => $this->editableEmail->subject,
            ]);
    }
}
