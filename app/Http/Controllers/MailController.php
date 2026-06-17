<?php

namespace App\Http\Controllers;

use App\EditableDate;
use App\EditableEmail;
use App\Http\Repositories\SchoolClassRepository;
use App\Mail\CustomEmail;
use App\Teacher;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class MailController extends Controller
{
    private $schoolClassRepository;

    public function __construct(SchoolClassRepository $schoolClassRepository)
    {
        $this->schoolClassRepository = $schoolClassRepository;
    }

    public function sendFinalMails()
    {
        // Send final mail to classes not eligible to
        // participate to the party
        $this->sendFinalMail();

        // Send party invitation mail to classes eligible to
        // participate to the party
        $this->sendPartyInvite();

        // Send party invitation reminder mail to classes eligible to
        // participate to the party that haven't registered yet
        $this->sendPartyInviteReminder();

        // Send second party invitation reminder mail to classes eligible to
        // participate to the party that haven't registered yet
        $this->sendPartyInviteReminderSecond();

        // Send party invitation reminder mail to classes eligible to
        // participate to the party that haven't registered yet
        $this->sendPartyInviteJ2();

        // Send end of the year email extra communication to all teachers
        $this->sendEndYearCommunicationEmail();
    }

    public function sendFinalMail()
    {
        $date = EditableDate::find(EditableDate::FINAL_MAIL);
        $mail = EditableEmail::find(EditableEmail::$MAIL_FINAL);
        // is start date today?
        if (!$date->isCurrentDay()) {
            return;
        }

        Log::info('Sending ' . EditableEmail::$MAIL_FINAL[0]);

        $classes = $this->schoolClassRepository->findNotEligibleForFinalParty();
        foreach ($classes as $class) {
            if($mail->isSentToClass($class)) {
                Log::info("Mail already sent to {$class->name} ({$class->id}), skipping...");
                continue;
            }
            Log::info("Sending mail to {$class->name} ({$class->id})");
            Mail::to($class->teacher->user->email)->queue(new CustomEmail($mail, $class->teacher, $class));
        }
    }

    public function sendPartyInvite()
    {
        $date = EditableDate::find(EditableDate::FINAL_INVITATION_PARTY);
        $mail = EditableEmail::find(EditableEmail::$MAIL_INVITE_PARTY);
        // is start date today?
        if (!$date->isCurrentDay()) {
            return;
        }

        Log::info('Sending ' . EditableEmail::$MAIL_INVITE_PARTY[0]);

        $classes = $this->schoolClassRepository->findEligibleForFinalParty();
        foreach ($classes as $class) {
            if($mail->isSentToClass($class)) {
                Log::info("Mail already sent to {$class->name} ({$class->id}), skipping...");
                continue;
            }
            Log::info("Sending mail to {$class->name} ({$class->id})");
            Mail::to($class->teacher->user->email)->queue(new CustomEmail($mail, $class->teacher, $class));
        }
    }

    public function sendPartyInviteReminder()
    {
        $date = EditableDate::find(EditableDate::FINAL_INVITATION_PARTY_REMINDER);
        $mail = EditableEmail::find(EditableEmail::$MAIL_INVITE_PARTY_REMINDER);
        // is start date today?
        if (!$date->isCurrentDay()) {
            return;
        }

        Log::info('Sending ' . EditableEmail::$MAIL_INVITE_PARTY_REMINDER[0]);

        $classes = $this->schoolClassRepository->findEligibleForFinalPartyReminder();
        foreach ($classes as $class) {
            if($mail->isSentToClass($class)) {
                Log::info("Mail already sent to {$class->name} ({$class->id}), skipping...");
                continue;
            }
            Log::info("Sending mail to {$class->name} ({$class->id})");
            Mail::to($class->teacher->user->email)->queue(new CustomEmail($mail, $class->teacher, $class));
        }
    }

    public function sendPartyInviteReminderSecond()
    {
        $date = EditableDate::find(EditableDate::FINAL_INVITATION_PARTY_REMINDER_SECOND);
        $mail = EditableEmail::find(EditableEmail::$MAIL_INVITE_PARTY_REMINDER_SECOND);
        // is start date today?
        if (!$date->isCurrentDay()) {
            return;
        }

        Log::info('Sending ' . EditableEmail::$MAIL_INVITE_PARTY_REMINDER_SECOND[0]);

        $classes = $this->schoolClassRepository->findEligibleForFinalPartyReminder();
        foreach ($classes as $class) {
            if($mail->isSentToClass($class)) {
                Log::info("Mail already sent to {$class->name} ({$class->id}), skipping...");
                continue;
            }
            Log::info("Sending mail to {$class->name} ({$class->id})");
            Mail::to($class->teacher->user->email)->queue(new CustomEmail($mail, $class->teacher, $class));
        }
    }

    public function sendPartyInviteJ2()
    {
        $date = EditableDate::find(EditableDate::FINAL_INVITATION_PARTY_J_2);
        $mail = EditableEmail::find(EditableEmail::$MAIL_INVITE_PARTY_J_2);
        // is start date today?
        if (!$date->isCurrentDay()) {
            return;
        }

        Log::info('Sending ' . EditableEmail::$MAIL_INVITE_PARTY_J_2[0]);

        $classes = $this->schoolClassRepository->findEligibleForFinalPartyReminder();
        foreach ($classes as $class) {
            if($mail->isSentToClass($class)) {
                Log::info("Mail already sent to {$class->name} ({$class->id}), skipping...");
                continue;
            }
            Log::info("Sending mail to {$class->name} ({$class->id})");
            Mail::to($class->teacher->user->email)->queue(new CustomEmail($mail, $class->teacher, $class));
        }
    }

    public function sendPartyInformations()
    {
        $date = EditableDate::find(EditableDate::FINAL_INVITATION_PARTY_INFORMATIONS);
        $mail = EditableEmail::find(EditableEmail::$MAIL_INVITATION_PARTY_INFORMATIONS);
        // is start date today?
        if (!$date->isCurrentDay()) {
            return;
        }

        Log::info('Sending ' . EditableEmail::$MAIL_INVITATION_PARTY_INFORMATIONS[0]);

        $classes = $this->schoolClassRepository->findEligibleForFinalPartyInformations();
        foreach ($classes as $class) {
            if($mail->isSentToClass($class)) {
                Log::info("Mail already sent to {$class->name} ({$class->id}), skipping...");
                continue;
            }
            Log::info("Sending mail to {$class->name} ({$class->id})");
            Mail::to($class->teacher->user->email)->queue(new CustomEmail($mail, $class->teacher, $class));
        }
    }

    public function sendNewEducationalTool()
    {
        $date = EditableDate::find(EditableDate::NEW_EDUCATIONAL_TOOL);
        $mail = EditableEmail::find(EditableEmail::$MAIL_NEW_EDUCATIONAL_TOOL);
        // is start date today?
        if (!$date->isCurrentDay()) {
            return;
        }

        Log::info('Sending ' . EditableEmail::$MAIL_NEW_EDUCATIONAL_TOOL[0]);

        $teachers = Teacher::all();
        foreach ($teachers as $teacher) {
            if($mail->isSentToUser($teacher->user)) {
                Log::info("Mail already sent to {$teacher->first_name} ({$teacher->id}), skipping...");
                continue;
            }
            Log::info("Sending mail to {$teacher->first_name} ({$teacher->id})");
            Mail::to($teacher->user->email)->queue(new CustomEmail($mail, $teacher, null));
        }
    }

    public function sendEndYearCommunicationEmail()
    {
        $date = EditableDate::find(EditableDate::END_YEAR_COMMUNICATION_EMAIL);
        $mail = EditableEmail::find(EditableEmail::$MAIL_END_YEAR_COMMUNICATION);
        // is start date today?
        if (!$date->isCurrentDay()) {
            return;
        }

        Log::info('Sending ' . EditableEmail::$MAIL_END_YEAR_COMMUNICATION[0]);

        $teachers = Teacher::all();
        foreach ($teachers as $teacher) {
            if($mail->isSentToUser($teacher->user)) {
                Log::info("Mail already sent to {$teacher->first_name} ({$teacher->id}), skipping...");
                continue;
            }
            Log::info("Sending mail to {$teacher->first_name} ({$teacher->id})");
            Mail::to($teacher->user->email)->queue(new CustomEmail($mail, $teacher, null));
        }
    }
}
