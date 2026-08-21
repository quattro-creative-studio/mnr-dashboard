<?php


namespace App\Http\Managers;


use App\EditableDate;
use App\EditableEmail;
use App\Http\Repositories\EmailRepository;
use App\Http\Services\NewCertificateService;
use App\Mail\CustomEmail;
use App\SchoolClass;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Log;
use Ramsey\Uuid\Uuid;
use Storage;

class SchoolClassManager
{

    /**
     * @var EmailRepository
     */
    private $emailRepository;

    /**
     * @var NewCertificateService
     */
    private $certificateService;

    public function __construct(EmailRepository $emailRepository, NewCertificateService  $certificateService)
    {
        $this->emailRepository = $emailRepository;
        $this->certificateService = $certificateService;
    }

    /**
     * @param SchoolClass $class
     * @param string      $token
     *
     * @return string
     * @throws \Exception
     */
    public function determineStatusByToken(SchoolClass $class, string $token): string
    {
        if ($class->january_token === $token)
            return SchoolClass::STATUS_JANUARY;
        if ($class->march_token === $token)
            return SchoolClass::STATUS_MARCH;
        if ($class->may_token === $token)
            return SchoolClass::STATUS_MAY;
        throw new \Exception('Class does not have specified token.');
    }

    /**
     * @param SchoolClass $class
     * @param bool        $status
     */
    public function handlePartyResponse(SchoolClass $class, bool $status)
    {
        $class->setPartyStatus($status);
        $class->clearPartyToken();

        if ($status === false) {
            $mail = $this->emailRepository->findPartyResponseNegative();
            \Mail::to($class->teacher->user->email)
                ->queue(new CustomEmail($mail, $class->teacher, $class));
        }
    }

    /**
     * Determines if sent_at is not set and that "now" is after the followUp date in env.
     *
     * @param SchoolClass $class
     * @param string      $whichStatus Which status to check, use constants: {@link STATUS_JANUARY}, {@link STATUS_MARCH}, {@link STATUS_MAY}
     *
     * @return bool
     * @throws \Exception
     */
    public function shouldSendFollowUp(SchoolClass $class, string $whichStatus): bool
    {
        $followupDate = $this->findFollowUpDateByStatus($whichStatus);

        $sentAtName = $whichStatus . '_sent_at';
        $statusValue = $class->__get('status_' . $whichStatus);
        /** @var Carbon $sentAt */
        $sentAt = $class->$sentAtName;
        return $class->arePreviousStatusesPositive($whichStatus)
            && $sentAt === null
            && $statusValue === null
            && Carbon::now()->gte($followupDate);
    }

    /**
     * Checks if the follow up for the given status is sent, and it was at least some time ago (configurable in env).
     *
     * @param SchoolClass $class
     * @param string      $whichStatus        Which status to check, use constants: {@link STATUS_JANUARY}, {@link STATUS_MARCH}, {@link STATUS_MAY}
     * @param bool        $checkIfAlreadySent Checks if the email reminder has already been sent.
     *
     * @return bool
     * @throws \Exception
     */
    public function shouldSendFollowUpReminder(SchoolClass $class, string $whichStatus, $checkIfAlreadySent = true): bool
    {
        $sentAtName = $whichStatus . '_sent_at';
        $reminderSentAtName = $whichStatus . '_reminder_sent_at';
        /** @var Carbon $sentAt */
        $sentAt = $class->$sentAtName;
        /** @var Carbon $reminderSentAt */
        $reminderSentAt = $class->$reminderSentAtName;
        if ($sentAt === null || !$class->arePreviousStatusesPositive($whichStatus))
            return false;

        $statusValue = $class->__get('status_' . $whichStatus);
        if ($statusValue !== null)
            return false;

        $followupReminderDate = $this->findReminderDateByStatus($whichStatus);
        if (!$checkIfAlreadySent) {
            $followupReminderDate = Carbon::create(2000, 1, 1, 0, 0, 0);
            $reminderSentAt = null;
        }
        return $reminderSentAt === null && Carbon::now()->gte($followupReminderDate);
    }

    public function shouldSendPartyReminder(SchoolClass $class): bool
    {
        if ($class->status_january != true || $class->status_march != true || $class->status_may != true)
            return false;

        if ($class->status_party !== null)
            return false;

        if ($class->party_reminder_sent_at !== null)
            return false;

        $reminderDate = EditableDate::find(EditableDate::INVITE_PARTY_REMINDER);
        return Carbon::now()->gte($reminderDate);
    }

    public function shouldSendPartyGroupReminder(SchoolClass $class)
    {
        if ($class->status_january != true || $class->status_march != true || $class->status_may != true)
            return false;

        if ($class->status_party != true)
            return false;

        if ($class->partyGroups()->exists())
            return false;

        if ($class->party_group_reminder_sent_at !== null)
            return false;

        $reminderDate = EditableDate::find(EditableDate::PARTY_GROUP_REMINDER);
        return Carbon::now()->gte($reminderDate);
    }

    /**
     * @param string $whichStatus
     *
     * @return Carbon|null
     * @throws \Exception
     */
    private function findFollowUpDateByStatus(string $whichStatus): ?Carbon
    {
        if ($whichStatus === SchoolClass::STATUS_JANUARY)
            return EditableDate::find(EditableDate::FOLLOW_UP_1);
        if ($whichStatus === SchoolClass::STATUS_MARCH)
            return EditableDate::find(EditableDate::FOLLOW_UP_2);
        if ($whichStatus === SchoolClass::STATUS_MAY)
            return EditableDate::find(EditableDate::FOLLOW_UP_3);
        throw new \Exception("Unknown status: $whichStatus");
    }

    /**
     * @param string $whichStatus
     *
     * @return Carbon|null
     * @throws \Exception
     */
    private function findReminderDateByStatus(string $whichStatus): ?Carbon
    {
        if ($whichStatus === SchoolClass::STATUS_JANUARY)
            return EditableDate::find(EditableDate::FOLLOW_UP_1_REMINDER);
        if ($whichStatus === SchoolClass::STATUS_MARCH)
            return EditableDate::find(EditableDate::FOLLOW_UP_2_REMINDER);
        if ($whichStatus === SchoolClass::STATUS_MAY)
            return EditableDate::find(EditableDate::FOLLOW_UP_3_REMINDER);
        throw new \Exception("Unknown status: $whichStatus");
    }

    /**
     * Find classes eligible for party but not having created party groups
     *
     * @return Collection
     */
    public function findClassesMissingPartyGroups(): Collection
    {
        return SchoolClass::all()->filter(function (SchoolClass $class) {
            return $this->isClassMissingPartyGroups($class);
        });
    }

    public function isClassMissingPartyGroups(SchoolClass $class)
    {
        return $class->isEligibleForParty() && $class->partyGroups()->doesntExist();
    }

    /**
     * Generates and uploads the certificate file, also creates the model and associates it
     *
     * @param SchoolClass $class
     *
     * @throws \Exception
     */
    public function generateCertificate(SchoolClass $class)
    {
        if (!$class->quizResponses()->whereNotNull('responded_at')->exists())
            return;

        Log::info("Generating certificate for {$class->id}: {$class->name} ");

        $pdfString = $this->certificateService->generateCertificate($class);
        $uuid = Uuid::uuid4()->toString();
        $certPath = "certificats/$uuid/certificat.pdf";
        Storage::put($certPath, $pdfString, 'public');

        if ($class->certificate()->exists()) {
            $class->certificate->deletePdf();
            $class->certificate()->update(['url' => $certPath]);
        } else {
            $class->certificate()->create([
                'url' => $certPath,
                'uid' => Uuid::uuid4()->toString(),
            ]);
        }

    }


}
