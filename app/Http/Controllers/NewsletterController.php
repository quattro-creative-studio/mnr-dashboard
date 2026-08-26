<?php

namespace App\Http\Controllers;

use App\EditableDate;
use App\EditableEmail;
use App\Http\Repositories\SchoolClassRepository;
use App\Mail\CustomEmail;
use App\SchoolClass;
use App\Teacher;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class NewsletterController extends Controller {

    const sendingHour = 10;
    /**
     * @var SchoolClassRepository
     */
    private $classRepository;

    public function __construct(SchoolClassRepository $classRepository) {
        $this->classRepository = $classRepository;
    }

    public function sendNewsletters() {
        $teachers = Teacher::all();
        $this->send($teachers, EditableDate::NEWSLETTER_START, EditableEmail::$MAIL_NEWSLETTER_START);
        // $this->send($teachers, EditableDate::NEWSLETTER_1, EditableEmail::$MAIL_NEWSLETTER_1);
        // $this->send($teachers, EditableDate::NEWSLETTER_2, EditableEmail::$MAIL_NEWSLETTER_2);
    }

    private function send(Collection $teachers, string $dateIdentifier, array $mailIdentifier) {
        // Due today, and not switched off for this edition?
        $mail = EditableEmail::readyToSendToday($mailIdentifier, $dateIdentifier);
        if ($mail === null) {
            return;
        }

        if (Carbon::now()->hour < NewsletterController::sendingHour) {
            return;
        }
        \Log::info('Sending ' . $mailIdentifier[0]);

        $teachers->each(function (Teacher $teacher) use ($mail) {
            $shouldSend = !$mail->isSentToUser($teacher->user);
            // if ($shouldSend) {
            //     if ($teacher->classes()->exists() && $mail->key !== 'newsletter_1') {
            //         \Log::info("Sending to teacher $teacher->full_name with classes");
            //         foreach ($teacher->classes as $class) {
            //             /** @var SchoolClass $class */
            //             if($class->isStillParticipating()) {
            //                 \Log::info("Sending to class $class->name");
            //                 \Mail::to($teacher->user->email)->queue(new CustomEmail($mail, $teacher, $class));
            //             } else {
            //                 \Log::info("Skipping class $class->name because not participating anymore");
            //             }
            //         }
            //     } else {
            //         \Log::info("Sending to teacher $teacher->full_name without classes or newsletter_1");
            //         \Mail::to($teacher->user->email)->queue(new CustomEmail($mail, $teacher, null));
            //     }
            // }
            if ($shouldSend && $mail->key === 'newsletter_start') {
                \Log::info("Sending to teacher $teacher->full_name");
                \Mail::to($teacher->user->email)->queue(new CustomEmail($mail, $teacher, null));
            }
        });
    }

}
