<?php

namespace App;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Class EditableEmail
 * @package App
 *
 * @property int key
 * @property string title
 * @property string text
 * @property string subject
 * @property bool enabled
 * @property Collection dates
 * @property Collection sentEmails
 * @property Carbon created_at
 * @property Carbon updated_at
 *
 * @method static EditableEmail create(array $map)
 */
class EditableEmail extends Model {

    protected $fillable = ['key', 'title', 'text', 'subject', 'enabled', 'sort_order'];

    protected $casts = ['enabled' => 'boolean'];

    protected $appends = ['dates_string'];

    public $incrementing = false;

    protected $primaryKey = "key";
    protected $keyType = 'string';

    public static $MAIL_TEACHER_CONFIRMATION = ["teacher_confirmation", "Email de confirmation dès que l'enseignant s'inscris"];

    public static $MAIL_FOLLOW_UP_1 = ["follow_up_1", "Message de suivi janvier"];
    public static $MAIL_FOLLOW_UP_1_YES = ["follow_up_1_yes", "Réponse positive du suivi janvier"];
    public static $MAIL_FOLLOW_UP_1_NO = ["follow_up_1_no", "Réponse négative du suivi janvier"];
    public static $MAIL_FOLLOW_UP_1_REMINDER = ["follow_up_1_reminder", "Message de suivi janvier rappel"];

    public static $MAIL_FOLLOW_UP_2 = ["follow_up_2", "Message de suivi mars"];
    public static $MAIL_FOLLOW_UP_2_YES = ["follow_up_2_yes", "Réponse positive du suivi mars"];
    public static $MAIL_FOLLOW_UP_2_NO = ["follow_up_2_no", "Réponse négative du suivi mars"];
    public static $MAIL_FOLLOW_UP_2_REMINDER = ["follow_up_2_reminder", "Message de suivi mars rappel"];

    public static $MAIL_FOLLOW_UP_3 = ["follow_up_3", "Message de suivi mai"];
    public static $MAIL_FOLLOW_UP_3_NO = ["follow_up_3_no", "Réponse négative du suivi mai"];
    public static $MAIL_FOLLOW_UP_3_REMINDER = ["follow_up_3_reminder", "Message de suivi mai rappel"];

    public static $MAIL_NEW_EDUCATIONAL_TOOL = ["new_educational_tool", "Nouvel outil pédagogique"];

    public static $MAIL_INVITE_PARTY = ["invite_party", "Invitation à la fête de clôture"];
    public static $MAIL_INVITE_PARTY_NO = ["party_confirmation_no", "Réponse négative participation à la fête de clôture"];
    public static $MAIL_INVITE_PARTY_REMINDER = ["invite_party_reminder", "Invitation à la fête de clôture rappel"];
    public static $MAIL_INVITE_PARTY_REMINDER_SECOND = ["invite_party_reminder_second", "Invitation à la fête de clôture rappel"];
    public static $MAIL_INVITE_PARTY_J_2 = ["invite_party_j_2", "Invitation à la fête de clôture | J - 2"];
    public static $MAIL_INVITATION_PARTY_INFORMATIONS = ["invite_party_informations", "Informations pour la fête de clôture"];

    public static $MAIL_PARTY_GROUP_REMINDER = ["party_group_reminder", "Rappel inscription des groupes à la fête"];

    public static $MAIL_END_YEAR_COMMUNICATION = ["end_year_communication_email", "Message de fin d'année"];

    public static $MAIL_FINAL = ["final", "Mail final"];
    public static $MAIL_FINAL_CERTIFICAT = ["final_certificat", "Mail final avec certificat"];
    public static $MAIL_NEWSLETTER_START = ["newsletter_start", "Début du concours Mission Nichtrauchen"];
    public static $MAIL_NEWSLETTER_ENCOURAGEMENT = ["newsletter_encouragement", "Bravo – plus que 13 semaines... !"];
    public static $MAIL_NEWSLETTER_1 = ["newsletter_1", "Mail 1"];
    public static $MAIL_NEWSLETTER_2 = ["newsletter_2", "Mail 2"];

    public static function getEmails() {
        return collect([
            static::$MAIL_TEACHER_CONFIRMATION,
            static::$MAIL_FINAL,
            static::$MAIL_FINAL_CERTIFICAT,
            static::$MAIL_NEWSLETTER_START,
            static::$MAIL_NEWSLETTER_ENCOURAGEMENT,
            static::$MAIL_FOLLOW_UP_1,
            static::$MAIL_FOLLOW_UP_1_YES,
            static::$MAIL_FOLLOW_UP_1_NO,
            static::$MAIL_FOLLOW_UP_1_REMINDER,
            static::$MAIL_FOLLOW_UP_2,
            static::$MAIL_FOLLOW_UP_2_YES,
            static::$MAIL_FOLLOW_UP_2_NO,
            static::$MAIL_FOLLOW_UP_2_REMINDER,
            static::$MAIL_FOLLOW_UP_3,
            static::$MAIL_NEW_EDUCATIONAL_TOOL,
            static::$MAIL_INVITE_PARTY,
            static::$MAIL_FOLLOW_UP_3_NO,
            static::$MAIL_INVITATION_PARTY_INFORMATIONS,
            static::$MAIL_INVITE_PARTY_REMINDER,
            static::$MAIL_INVITE_PARTY_REMINDER_SECOND,
            static::$MAIL_INVITE_PARTY_J_2,
            static::$MAIL_PARTY_GROUP_REMINDER,
            static::$MAIL_FOLLOW_UP_3_REMINDER,
            static::$MAIL_INVITE_PARTY_NO,
            static::$MAIL_NEWSLETTER_1,
            static::$MAIL_NEWSLETTER_2,
        ]);
    }

    /**
     * How a mail leaves the application. Every mail is exactly one of these,
     * and the admin list draws its "État" column from it.
     */
    const MODE_SCHEDULED = 'scheduled';
    const MODE_TRANSACTIONAL = 'transactional';
    const MODE_DORMANT = 'dormant';

    /**
     * Mails the calendar never sends: a teacher's or an administrator's action
     * triggers them, one recipient at a time.
     *
     *   teacher_confirmation      TeacherRegisterController, on registration
     *   follow_up_*_yes / _no     EmailRepository, on a follow-up answer
     *   party_confirmation_no     EmailRepository, on a party refusal
     *   party_group_reminder      PartyController, per class from the admin
     *   final_certificat          sent with the certificate, not on a date
     *
     * The pivot is no help in telling these apart -- teacher_confirmation is
     * linked to "Début inscriptions" purely so the admin list has a heading --
     * so the list is explicit.
     */
    public static $TRANSACTIONAL_KEYS = [
        'teacher_confirmation',
        'follow_up_1_yes',
        'follow_up_1_no',
        'follow_up_2_yes',
        'follow_up_2_no',
        'follow_up_3_no',
        'party_confirmation_no',
        'party_group_reminder',
        'final_certificat',
    ];

    /**
     * Mails no code path currently sends.
     *
     * The January/March/May follow-up mechanism is deliberately switched off
     * but kept -- send:followup is unscheduled and SendFollowUpEmails is not
     * wired to anything -- because it gets toggled back on between contest
     * years. The encouragement and numbered newsletters are in the same state:
     * their sendNewsletters() calls are commented out.
     *
     * They are listed rather than hidden: an administrator who edits their text
     * needs to know it will not go anywhere this year. Offering an on/off switch
     * on them would be a lie -- there is no sender to obey it.
     */
    public static $DORMANT_KEYS = [
        'follow_up_1',
        'follow_up_1_reminder',
        'follow_up_2',
        'follow_up_2_reminder',
        'follow_up_3',
        'follow_up_3_reminder',
        'newsletter_encouragement',
        'newsletter_1',
        'newsletter_2',
    ];

    public function sendingMode(): string {
        if (in_array($this->key, static::$TRANSACTIONAL_KEYS, true)) {
            return static::MODE_TRANSACTIONAL;
        }

        if (in_array($this->key, static::$DORMANT_KEYS, true)) {
            return static::MODE_DORMANT;
        }

        return static::MODE_SCHEDULED;
    }

    /**
     * Is this mail sent by the calendar, and therefore switchable?
     */
    public function isScheduled(): bool {
        return $this->sendingMode() === static::MODE_SCHEDULED;
    }

    public function isTransactional(): bool {
        return $this->sendingMode() === static::MODE_TRANSACTIONAL;
    }

    public function isDormant(): bool {
        return $this->sendingMode() === static::MODE_DORMANT;
    }

    /**
     * The date this mail is scheduled on, or null when it has none.
     * A mail may be linked to several dates in the pivot; the list has always
     * shown the first, and every mail in practice has exactly one.
     */
    public function scheduleDate(): ?EditableDate {
        return $this->dates->first();
    }

    /**
     * The one gate every scheduled sender passes through.
     *
     * Returns the mail when it is due to go out right now, and null otherwise:
     * the row is missing, its date is not configured, today is not the day, or
     * an administrator switched it off for this edition.
     *
     * Note the null-date case. EditableDate::find() returns null for an absent
     * key and Carbon 3 raises a TypeError on null, so the old
     * $date->isCurrentDay() was a fatal waiting for a half-seeded database.
     * An unconfigured date means "not scheduled", never "send now" -- the same
     * reading EditableDate::hasPassed() already takes.
     *
     * This gate covers the calendar only. Sending a mail by hand from the admin
     * (PartyController, send:party-invite) stays possible while it is off:
     * that is a deliberate one-off, not the schedule firing.
     *
     * @param array $mailKey One of the $MAIL_* constants on this class.
     * @param string $dateKey One of the constants on EditableDate.
     */
    public static function readyToSendToday(array $mailKey, string $dateKey): ?EditableEmail {
        $mail = static::find($mailKey);

        if ($mail === null) {
            Log::warning("Scheduled mail '{$mailKey[0]}' has no row in editable_emails, skipping.");

            return null;
        }

        if (!$mail->enabled) {
            Log::info("Scheduled mail '{$mailKey[0]}' is disabled, skipping.");

            return null;
        }

        $date = EditableDate::find($dateKey);

        if ($date === null || !$date->isCurrentDay()) {
            return null;
        }

        return $mail;
    }

    public function sentEmails() {
        return $this->hasMany(SentEmail::class);
    }

    public function dates(): BelongsToMany {
        return $this->belongsToMany(EditableDate::class);
    }

    public function getDatesStringAttribute() {
        return $this
            ->dates()
            ->get()
            ->map(function (EditableDate $date) {
                // value is nullable in the schema; a date row without one would
                // otherwise take down every screen that prints this accessor.
                $when = optional($date->value)->toDateString() ?? 'non configurée';

                return $when . ' (' . $date->label . ')';
            })
            ->implode(', ');
    }

    /**
     * Returns all users that have received this email
     * @return Collection Collection of user objects
     */
    public function sentUsers(): Collection {
        return $this->sentEmails->map(function (SentEmail $sentEmail) {
            return $sentEmail->user;
        });
    }

    /**
     * Saves the information that the email is sent to the given user
     * @param User $user
     */
    public function setSent(User $user) {
        SentEmail::create([
            'editable_email_key' => $this->key,
            'user_id' => $user->id,
        ]);
    }

    public function setSentToClass(SchoolClass $class) {
        SentEmail::create([
            'editable_email_key' => $this->key,
            'school_class_id' => $class->id,
        ]);
    }

    /**
     * Checks if this email has already been sent to the given user
     * @param User $user
     * @return bool
     */
    public function isSentToUser(User $user): bool {
        return $this->sentEmails->pluck('user_id')->containsStrict($user->id);
    }

    public function isSentToClass(SchoolClass $class): bool {
        return $this->sentEmails->pluck('school_class_id')->containsStrict($class->id);
    }

    /**
     * Finds an editable email by the key.
     * @param array $key One of the constants declared in {@link EditableEmail} class.
     * @return EditableEmail
     */
    public static function find(array $key) {
        return static::query()->where('key', $key[0])->first();
    }

    /**
     * Finds a EditableEmail object by the key in string format.
     * @param string $key
     * @return EditableEmail|null
     */
    public static function findByKey(string $key) {
        return static::query()->where('key', $key)->first();
    }

    public static function addEmailsToDb() {
        foreach (static::getEmails() as $mail) {
            $key = $mail[0];
            $title = $mail[1];
            if (static::query()->where('key', $key)->exists())
                continue;

            static::create([
                'key' => $key,
                'title' => $title,
                'text' => '',
            ]);
        }

    }

    public static function updateEmails() {
        foreach (static::getEmails() as $mail) {
            $key = $mail[0];
            $title = $mail[1];
            if (static::query()->where('key', $key)->exists()) {
                static::query()->where('key', $key)->update([
                    'title' => $title,
                ]);
            } else {
                static::create([
                    'key' => $key,
                    'title' => $title,
                    'subject' => $title,
                    'text' => '',
                ]);
            }
        }

    }

}
