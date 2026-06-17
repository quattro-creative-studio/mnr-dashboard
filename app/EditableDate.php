<?php

namespace App;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;

/**
 * Class EditableDate
 * @package App
 *
 * @property string key
 * @property string label
 * @property Carbon value
 * @property Collection emails
 * @property Carbon created_at
 * @property Carbon updated_at
 *
 * @method static EditableDate create($data)
 */
class EditableDate extends Model {

    protected $fillable = ['key', 'label', 'description', 'value', 'sort_order'];

    protected $primaryKey = 'key';

    protected $keyType = 'string';

    protected $dates = ['value'];

    const TEACHER_INSCRIPTION_START = "TEACHER_INSCRIPTION_START";
    const TEACHER_INSCRIPTION_END = "TEACHER_INSCRIPTION_END";
    const NEWSLETTER_START = "NEWSLETTER_START";
    const NEWSLETTER_ENCOURAGEMENT = "NEWSLETTER_ENCOURAGEMENT";
    const NEWSLETTER_1 = "NEWSLETTER_1";
    const NEWSLETTER_2 = "NEWSLETTER_2";
    const FOLLOW_UP_1 = "FOLLOW_UP_1";
    const FOLLOW_UP_1_REMINDER = "FOLLOW_UP_1_REMINDER";
    const FOLLOW_UP_2 = "FOLLOW_UP_2";
    const FOLLOW_UP_2_REMINDER = "FOLLOW_UP_2_REMINDER";
    const FOLLOW_UP_3 = "FOLLOW_UP_3";
    const FOLLOW_UP_3_REMINDER = "FOLLOW_UP_3_REMINDER";
    const INVITE_PARTY_REMINDER = "PARTY_REMINDER";
    const PARTY_GROUP_REMINDER = "PARTY_GROUP_REMINDER";
    const NEW_EDUCATIONAL_TOOL = "NEW_EDUCATIONAL_TOOL";
    const FINAL_MAIL = "FINAL_MAIL";
    const FINAL_MAIL_CERTIFICATE = "FINAL_MAIL_CERTIFICATE";
    const FINAL_INVITATION_PARTY = "FINAL_INVITATION_PARTY";
    const FINAL_INVITATION_PARTY_INFORMATIONS = "FINAL_INVITATION_PARTY_INFORMATIONS";
    const FINAL_INVITATION_PARTY_REMINDER = "FINAL_INVITATION_PARTY_REMINDER";
    const FINAL_INVITATION_PARTY_REMINDER_SECOND = "FINAL_INVITATION_PARTY_REMINDER_SECOND";
    const FINAL_INVITATION_PARTY_J_2 = "FINAL_INVITATION_PARTY_J_2";
    const END_YEAR_COMMUNICATION_EMAIL = "END_YEAR_COMMUNICATION_EMAIL";

    /**
     * Finds a date value by a key. Keys are constants defined in this class.
     * @param $key
     * @return Carbon|null
     */
    public static function find($key) {
        return optional(static::query()->where('key', $key)->first())->value;
    }

    public function emails(): BelongsToMany {
        return $this->belongsToMany(EditableEmail::class);
    }

    /**
     * Returns array with sub-arrays each containing default values for the fields: key, label, value
     * @return array
     */
    public static function getDefaultValues() {
        return [
            [
                'key' => static::TEACHER_INSCRIPTION_START,
                'label' => 'Début inscriptions',
                'value' => '2029-09-20',
            ],
            [
                'key' => static::TEACHER_INSCRIPTION_END,
                'label' => 'Fin inscriptions',
                'value' => '2029-10-20',
            ],
            [
                'key' => static::FOLLOW_UP_1,
                'label' => 'Suivi janvier',
                'value' => '2030-01-06',
            ],
            [
                'key' => static::FOLLOW_UP_1_REMINDER,
                'label' => 'Suivi janvier rappel',
                'value' => '2030-01-13',
            ],
            [
                'key' => static::FOLLOW_UP_2,
                'label' => 'Suivi mars',
                'value' => '2030-03-02',
            ],
            [
                'key' => static::FOLLOW_UP_2_REMINDER,
                'label' => 'Suivi mars rappel',
                'value' => '2030-03-09',
            ],
            [
                'key' => static::FOLLOW_UP_3,
                'label' => 'Suivi mai',
                'value' => '2030-05-04',
            ],
            [
                'key' => static::FOLLOW_UP_3_REMINDER,
                'label' => 'Suivi mai rappel',
                'value' => '2030-05-12',
            ],
            [
                'key' => static::INVITE_PARTY_REMINDER,
                'label' => 'Suivi mai rappel fête clôture',
                'value' => '2030-05-12',
            ],
            [
                'key' => static::PARTY_GROUP_REMINDER,
                'label' => 'Rappel inscription fête pour oui invite fête',
                'value' => '2030-05-15',
            ],
            [
                'key' => static::FINAL_MAIL,
                'label' => 'E-mail final',
                'value' => '2030-06-10',
            ],
            [
                'key' => static::NEWSLETTER_START,
                'label' => 'Newsletter début concours',
                'value' => '2029-11-04',
            ],
            [
                'key' => static::NEWSLETTER_ENCOURAGEMENT,
                'label' => 'Newsletter encouragement',
                'value' => '2030-02-03',
            ],
            [
                'key' => static::NEWSLETTER_1,
                'label' => 'Mail 1',
                'value' => '2030-11-01',
            ],
            [
                'key' => static::NEWSLETTER_2,
                'label' => 'Mail 2',
                'value' => '2030-11-02',
            ],
        ];
    }

    public static function updateOrAdd() {
        foreach(EditableDate::getDefaultValues() as $entry) {
            /** @var EditableDate $found */
            $found = EditableDate::query()->where('key', $entry['key'])->first();
            if($found != null) {
                $found->update($entry);
            } else {
                EditableDate::create($entry);
            }
        }
    }

}
