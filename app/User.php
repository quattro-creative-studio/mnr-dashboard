<?php

namespace App;

use Illuminate\Database\Eloquent\Factories\HasFactory;

use App\Mail\ResetPasswordMail;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Collection;
use Session;

/**
 * Represents a user that can login to the app. Different types of users have their own models containing information
 * specific to their user type.
 * @package App
 * @property int id
 * @property string email
 * @property Carbon email_verified_at
 * @property string password
 * @property string remember_token
 * @property Carbon created_at
 * @property Carbon updated_at
 * @property string type
 * @property Teacher teacher
 * @property OpenedDocument opened_document
 *
 * @method static User create(array $valueMap)
 */
class User extends Authenticatable {

    use HasFactory;
    use Notifiable;

    public const TYPE_ADMIN = "admin";
    public const TYPE_TEACHER = "teacher";

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'email', 'password', 'password_set_at', 'type'
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    /**
     * Cast so the users list can format it; without this it is a raw string.
     */
    protected $casts = [
        'password_set_at' => 'datetime',
    ];

    protected $hidden = [
        'password', 'remember_token',
    ];

    public function teacher(): BelongsTo {
        return $this->belongsTo(Teacher::class);
    }

    public function openedDocuments(): HasMany
    {
        return $this->hasMany(OpenedDocuments::class);
    }

    public function getCountUnopenedDocumentsAttribute()
    {
        $notificationDocumentsCount = Document::where('notification', true)->count();
        $openedDocumentsCount = $this->openedDocuments()->whereHas('document', function ($query) {
            $query->where('notification', true);
        })->count();

        return  $notificationDocumentsCount - $openedDocumentsCount;
    }

    /**
     * Finds all users of the given type.
     * @param string $type
     * @return Collection Collection of {@link User} objects
     */
    public static function findByType(string $type) {
        return static::query()->where('type', $type)->get();
    }

    public function updatePassword($password) {
        $this->update([
            'password' => \Hash::make($password),
        ]);
    }

    /**
     * Create a user from a password its owner chose.
     *
     * password_set_at is stamped here because this path is only used where a
     * human typed the password: teacher registration. An invited administrator
     * goes the other way -- a random password, then the reset form -- and the
     * column stays null until they arrive there, which is what the users list
     * reads to tell a pending invitation from an active account.
     */
    public static function createUser($email, $password, $type) {
        return static::create([
            'email' => $email,
            'password' => \Hash::make($password),
            'password_set_at' => now(),
            'type' => $type,
        ]);
    }

    public function sendPasswordResetNotification($token) {
        \Mail::to($this->email)->queue(new ResetPasswordMail($token));
        Session::flash('message', 'Votre e-mail a été envoyé avec succès');
    }

    /**
     * At least once class has accepted invitation for party
     * @return bool
     */
    public function hasAccessToParty() {
        $partyInvitationDate = \App\EditableDate::find(EditableDate::FINAL_INVITATION_PARTY);
        return $this
                ->teacher
                ->classes
                ->filter(function (SchoolClass $c) {
                    return $c->isEligibleForParty();
                    // return $c->status_party;
                })
                ->count() > 0
                && $partyInvitationDate->isPast();
    }

}
