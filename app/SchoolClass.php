<?php

namespace App;

use Illuminate\Database\Eloquent\Factories\HasFactory;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;
use Ramsey\Uuid\Uuid;

/**
 * Class SchoolClass
 * @package App
 *
 * @property int id
 * @property string name
 * @property int students
 * @property int school_id
 * @property int teacher_id
 * @property boolean status_january
 * @property boolean status_march
 * @property boolean status_may
 * @property boolean status_party
 * @property string january_token
 * @property Carbon january_sent_at
 * @property Carbon january_reminder_sent_at
 * @property string march_token
 * @property Carbon march_sent_at
 * @property Carbon march_reminder_sent_at
 * @property string may_token
 * @property Carbon may_sent_at
 * @property Carbon may_reminder_sent_at
 * @property Carbon party_sent_at
 * @property Carbon party_reminder_sent_at
 * @property string party_token
 * @property Carbon party_group_reminder_sent_at
 * @property Carbon updated_at
 * @property Carbon created_at
 * @property School school
 * @property Certificate certificate
 * @property Teacher teacher
 * @property Collection partyGroups
 * @property QuizResponse[]|Collection quizResponses
 * @property QuizAssignment[]|Collection quizAssignments
 *
 * @method static SchoolClass create(array $values)
 * @mixin \Eloquent
 */
class SchoolClass extends Model {


    use HasFactory;
    protected $fillable = ['name', 'students', 'school_id', 'teacher_id', 'status_january', 'status_march',
        'status_may', 'status_party', 'january_token', 'january_sent_at', 'january_reminder_sent_at', 'march_token',
        'march_sent_at', 'march_reminder_sent_at', 'may_token', 'may_sent_at', 'may_reminder_sent_at', 'status_party',
        'party_token', 'party_sent_at', 'party_reminder_sent_at', 'party_group_reminder_sent_at'];

    protected $dates = ['january_sent_at', 'january_reminder_sent_at', 'march_sent_at', 'march_reminder_sent_at',
        'may_sent_at', 'may_reminder_sent_at', 'party_sent_at', 'party_reminder_sent_at', 'party_group_reminder_sent_at'];

    public const STATUS_JANUARY = "january";
    public const STATUS_MARCH = "march";
    public const STATUS_MAY = "may";

    public function school(): BelongsTo {
        return $this->belongsTo(School::class);
    }

    public function teacher(): BelongsTo {
        return $this->belongsTo(Teacher::class);
    }

    public function partyGroups(): HasMany {
        return $this->hasMany(PartyGroup::class);
    }

    public function certificate(): HasOne {
        return $this->hasOne(Certificate::class);
    }

    public function quizResponses() {
        return $this->hasManyThrough(QuizResponse::class, QuizAssignment::class);
    }

    public function quizAssignments() {
        return $this->hasMany(QuizAssignment::class);
    }

    public function getStatusJanuary(): ?int {
        return $this->status_january;
    }

    public function getStatusMarch(): ?int {
        if($this->status_january === 0)
            return 0;
        return $this->status_march;
    }

    public function getStatusMay(): ?int {
        if($this->status_january === 0 || $this->status_march === 0)
            return 0;
        return $this->status_may;
    }

    public function getStatusParty(): ?int {
        if($this->status_january === 0 || $this->status_march === 0 || $this->status_may === 0)
            return 0;
        return $this->status_party;
    }

    public function getStatusPartyGroups(): ?int {
        if($this->status_january === 0 || $this->status_march === 0 || $this->status_may === 0 || $this->status_party === 0)
            return 0;
        return $this->partyGroups()->exists() ? 1 : null;
    }

    public function getQuizScore() {
        return $this->quizResponses()->whereHas('assignment.quiz', function ($q) {
            $q->where('state', '=', Quiz::STATE_CLOSED);
        })->sum('score');
    }

    public function getQuizScoreAll() {
        return $this->quizResponses()->sum('score');
    }

    /**
     * Get the maximum allowed number of groups
     * @return int
     */
    public function getMaxGroups() {
        if($this->students <= 10)
            return 1;
        if($this->students <= 20)
            return 2;
        return 3;
    }

    /**
     * @param bool $status
     */
    public function setPartyStatus(bool $status) {
        $this->status_party = $status;
        $this->save();
    }

    /**
     * Checks if the previous statuses have been answered positively by the teacher.
     * @param string $whichStatus Which status to check, use constants: {@link STATUS_JANUARY}, {@link STATUS_MARCH}, {@link STATUS_MAY}
     * @return bool
     */
    public function arePreviousStatusesPositive($whichStatus) {
        return true; // only may
//        if ($whichStatus === static::STATUS_JANUARY)
//            return true;
//        if ($whichStatus === static::STATUS_MARCH)
//            return $this->status_january === 1;
//        if ($whichStatus === static::STATUS_MAY)
//            return $this->status_january === 1 && $this->status_march === 1;
//
//        return false;
    }

    /**
     * Checks if this class is eligible for the end party.
     * @return bool
     */
    public function isEligibleForParty() {
        //  We allow classes that have answered to at least 5 out of 6 quizzes
        return $this->quizResponses()->count() >= config('app.minimum_required_quiz_responses');

        // Old solution
        return
//            $this->status_january === 1 &&
//            $this->status_march === 1 &&
            $this->status_may === 1 &&
            $this->status_party === 1;
    }

    /**
     * Checks if this class is eligible for receiving a certificate.
     * @return bool
     */
    public function isEligibleForCertificate() {
        return
//            $this->status_january === 1 &&
//            $this->status_march === 1 &&
 //           $this->status_may === 1;
            $this->quizResponses()->whereNotNull('responded_at')->exists();
    }

    /**
     * @param Teacher $teacher
     * @param string $name
     * @param int $students
     * @param School $school
     * @return SchoolClass The newly created SchoolClass
     */
    public static function createForTeacher(Teacher $teacher, $name, $students, School $school): SchoolClass {
        return SchoolClass::create([
            'name' => $name,
            'students' => $students,
            'school_id' => $school->id,
            'teacher_id' => $teacher->id,
        ]);
    }

    /**
     * Set the status of the follow to the given response. Which status to choose is automatically determined
     * through the token as it is unique.
     * @param string $status Which status to update
     * @param bool $newStatus If the class is still not smoking
     * @throws \Exception
     */
    public function setFollowUpStatus(string $status, bool $newStatus) {
        $dbFieldToken = $status . '_token'; // january_token
        $dbFieldStatus = 'status_' . $status; // status_january

        \Log::info('Follow up response is for status: ' . $dbFieldStatus . ' and class ' . $this->toJson());

        $this->update([
            $dbFieldToken => null,
            $dbFieldStatus => $newStatus,
        ]);
    }

    /**
     * Returns the current token to respond the follow up.
     * @return string
     * @throws \Exception
     */
    public function getCurrentToken() {
//        if($this->january_token != null)
//            return $this->january_token;
//        if($this->march_token != null)
//            return $this->march_token;
        if($this->may_token != null)
            return $this->may_token;
        throw new \Exception('SchoolClass has no token. ' . $this->toJson());
    }

    /**
     * Checks if last status was positive. If no follow up reached yet, result will be true.
     * @return bool
     */
    public function isStillParticipating(): bool {
        if (Carbon::now()->gte(EditableDate::find(EditableDate::FOLLOW_UP_3))) {
            return $this->status_may === true;
        }
//        if (Carbon::now()->gte(EditableDate::find(EditableDate::FOLLOW_UP_2))) {
//            return $this->status_march === true;
//        }
//        if (Carbon::now()->gte(EditableDate::find(EditableDate::FOLLOW_UP_1))) {
//            return $this->status_january === true;
//        }
        return true;
    }

    /**
     * @param $status
     * @throws \Exception
     */
    public function prepareSend($status) {
        $this->update([
            $status . '_sent_at' => Carbon::now(),
            $status . '_token' => Uuid::uuid4()->toString(),
        ]);
    }

    /**
     * @param $status
     * @throws \Exception
     */
    public function prepareSendReminder($status) {
        if($this->__get($status . '_token') == null) {
            $this->update([$status . '_token' => Uuid::uuid4()->toString()]);
        }
        $this->update([
            $status . '_reminder_sent_at' => Carbon::now(),
        ]);
    }

    /**
     * @throws \Exception
     */
    public function prepareSendParty() {
        $data = ['party_sent_at' => Carbon::now()];
        if($this->party_token === null)
            $data['party_token'] = Uuid::uuid4()->toString();

        $this->update($data);
    }

    /**
     * @throws \Exception
     */
    public function prepareSendPartyReminder() {
        $data = ['party_reminder_sent_at' => Carbon::now()];
        if($this->party_token === null)
            $data['party_token'] = Uuid::uuid4()->toString();

        $this->update($data);
    }

    public function clearPartyToken() {
        $this->party_token = null;
        $this->save();
    }

    public function prepareSendPartyGroupReminder() {
        $this->update([
            'party_group_reminder_sent_at' => Carbon::now(),
        ]);
    }

    public function getPoints(?Collection $quizzes = null) {
        $quizzes = $quizzes ?? Quiz::all();
        return $quizzes
            ->pluck('assignments')
            ->flatten()
            ->where('school_class_id', $this->id)
            ->sum('response.score');
    }

    public function getMaxPoints(?Collection $quizzes = null) {
        $quizzes = $quizzes ?? Quiz::all();
        return $quizzes->sum('max_score');
    }

}
