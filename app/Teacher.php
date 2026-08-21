<?php

namespace App;

use Illuminate\Database\Eloquent\Factories\HasFactory;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;

/**
 * Class Teacher
 * @package App
 * @property int id
 * @property int salutation_id
 * @property Salutation salutation
 * @property string first_name
 * @property string last_name
 * @property string phone
 * @property string full_name
 * @property Collection classes
 * @property Carbon updated_at
 * @property Carbon created_at
 * @property User user
 *
 * @method static Teacher create(array $valueMap)
 */
class Teacher extends Model {


    use HasFactory;
    protected $fillable = ['first_name', 'last_name', 'phone', 'salutation_id'];

    protected $appends = ['full_name', 'has_multiple_classes', 'has_multiple_schools'];

    public function user(): HasOne {
        return $this->hasOne(User::class);
    }

    public function salutation(): BelongsTo {
        return $this->belongsTo(Salutation::class);
    }

    public function classes(): HasMany {
        return $this->hasMany(SchoolClass::class);
    }

    public function getFullNameAttribute() {
        $s = $this->salutation()->first()->short_form;
        return $s . " " . $this->first_name . " " . $this->last_name;
    }

    public function getHasMultipleClassesAttribute() {
        return $this->classes()->count() > 1;
    }

    public function getHasMultipleSchoolsAttribute() {
        return $this->classes()
                ->get()
                ->pluck('school')
                ->unique()
                ->count() > 1;
    }

    /**
     * Creates a User and a Teacher and connects both of them together.
     * @param $salutation_id int
     * @param $firstName string
     * @param $lastName string
     * @param $email string
     * @param $password string
     * @param $phone string
     * @return Teacher The newly created teacher
     */
    public static function createWithUser($salutation_id, $firstName, $lastName, $email, $password, $phone) {
        $user = User::createUser($email, $password, User::TYPE_TEACHER);
        $teacher = Teacher::create([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'phone' => $phone,
            'salutation_id' => $salutation_id
        ]);
        $teacher->user()->save($user);
        return $teacher;
    }

    public function hasAccessToParty() {
        return $this->classes->map(function (SchoolClass $class) {
                return $class->isEligibleForParty();
            })->filter(function (bool $val) {
                return $val === true;
            })->count() > 0;
    }

    /**
     * @return bool if the teacher is still participating in the contest, meaning at least one of the teachers classes
     * is still participating.
     */
    public function isStillParticipating(): bool {
        return $this
                ->classes
                ->filter(function (SchoolClass $class) {
                    return $class->isStillParticipating();
                })
                ->count() > 0;
    }

}
