<?php

namespace App;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Class EditableEmail
 * @package App
 *
 * @property int key
 * @property string title
 * @property string text
 * @property string subject
 * @property Collection dates
 * @property Collection sentEmails
 * @property Carbon created_at
 * @property Carbon updated_at
 *
 * @method static EditableEmail create(array $map)
 */
class EditableEmail extends Model {

    protected $fillable = ['key', 'title', 'text', 'subject', 'sort_order'];

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
                return $date->value->toDateString() . ' (' . $date->label . ')';
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
