<?php

namespace App\Mail;

use App\PlaceHolder;
use App\QuizAssignment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class QuizMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @var QuizAssignment
     */
    protected $assignment;
    protected $reminder;

    /**
     * Create a new message instance.
     *
     * @param QuizAssignment $assignment
     * @param bool $reminder
     */
    public function __construct(QuizAssignment $assignment, bool $reminder = false)
    {
        $this->assignment = $assignment;
        $this->reminder = $reminder;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $class = $this->assignment->schoolClass;
        $text = PlaceHolder::replaceAll($this->assignment->quiz->email_text, $class->teacher, $class, $this->assignment);
        if($this->reminder) {
            $subj = "Rappel − " . $this->assignment->quiz->name;
        } else {
            $subj = $this->assignment->quiz->name;
        }
        return $this
            ->subject($subj)
            ->from(config('mail.from.address'), config('mail.from.name'))
            ->replyTo(config('mail.reply_to.address'))
            ->view('emails.custom', compact('text'));
    }
}
