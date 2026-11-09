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
    protected $textOverride;
    protected $subjectOverride;

    /**
     * Create a new message instance.
     *
     * The overrides are used by the amendment mail. They are stored on the
     * mailable, so they travel in the queued job's payload: the quiz's own
     * email_text is only read in build(), when the worker runs, so changing
     * it right after clicking "send" would change mails still in the queue.
     *
     * @param QuizAssignment $assignment
     * @param bool $reminder
     * @param string|null $textOverride    body to send instead of the quiz's email_text
     * @param string|null $subjectOverride subject to send instead of the quiz name
     */
    public function __construct(QuizAssignment $assignment, bool $reminder = false, ?string $textOverride = null, ?string $subjectOverride = null)
    {
        $this->assignment = $assignment;
        $this->reminder = $reminder;
        $this->textOverride = $textOverride;
        $this->subjectOverride = $subjectOverride;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $class = $this->assignment->schoolClass;
        $text = PlaceHolder::replaceAll($this->textOverride ?? $this->assignment->quiz->email_text, $class->teacher, $class, $this->assignment);
        if($this->subjectOverride !== null) {
            $subj = $this->subjectOverride;
        } elseif($this->reminder) {
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
