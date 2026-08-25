<?php


namespace App\Http\Repositories;


use App\SchoolClass;
use App\Teacher;
use App\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class SchoolClassRepository
{
    /**
     * Finds SchoolClass by one of its status tokens
     *
     * @param string $token
     *
     * @return SchoolClass|null
     */
    public function findByStatusToken(string $token): ?SchoolClass
    {
        return SchoolClass::query()
//            ->where('january_token', $token)
//            ->orWhere('march_token', $token)
            ->orWhere('may_token', $token)
            ->first();
    }

    /**
     * Finds SchoolClass by its party token
     *
     * @param string $token
     *
     * @return SchoolClass|null
     */
    public function findByPartyToken(string $token): ?SchoolClass
    {
        return SchoolClass::query()
            ->where('party_token', $token)
            ->first();
    }

    /**
     * Finds all {@link SchoolClass} objects for the currently logged in Teacher. If user is not a teacher, an empty
     * collection is returned
     *
     * @return Collection Collection of {@link SchoolClass} objects
     */
    public function findForLoggedInUser(): Collection
    {
        if (\Auth::user() === null || \Auth::user()->type !== User::TYPE_TEACHER || \Auth::user()->teacher === null)
            return collect();
        return static::findForTeacher(\Auth::user()->teacher);
    }

    /**
     * Finds all {@link SchoolClass} objects for the given Teacher
     *
     * @param Teacher $teacher
     *
     * @return Collection Collection of {@link SchoolClass} objects
     */
    public function findForTeacher(Teacher $teacher): Collection
    {
        return SchoolClass::query()
            ->where('teacher_id', $teacher->id)
            ->get();
    }

    /**
     * Finds SchoolClasses eligible for receiving party invitation
     *
     * @return Collection
     */
    public function findEligibleForFinalParty(): Collection
    {
        return SchoolClass::query()
            ->has('quizResponses', '>=', config('app.minimum_required_quiz_responses'))
            ->get();
    }

    /**
     * Finds SchoolClasses not eligible for receiving party invitation
     *
     * @return Collection
     */
    public function findNotEligibleForFinalParty(): Collection
    {
        return SchoolClass::query()
            ->has('quizResponses', '<', config('app.minimum_required_quiz_responses'))
            ->get();
    }


    /**
     * Finds SchoolClasses eligible for receiving party invitation reminder
     *
     * @return Collection
     */
    public function findEligibleForFinalPartyReminder(): Collection
    {
        return SchoolClass::query()
            ->has('quizResponses', '>=', config('app.minimum_required_quiz_responses'))
            ->doesntHave('partyGroups')
            ->get();
    }

    /**
     * Finds SchoolClasses eligible for receiving party informations
     *
     * @return Collection
     */
    public function findEligibleForFinalPartyInformations(): Collection
    {
        return SchoolClass::query()
            ->has('quizResponses', '>=', config('app.minimum_required_quiz_responses'))
            ->has('partyGroups')
            ->get();
    }

    /**
     * Finds SchoolClasses eligible for receiving certificates
     *
     * @return Collection
     */
    public function findEligibleForCertificate(): Collection
    {
        return SchoolClass::query()
//            ->where('status_january', 1)
//            ->where('status_march', 1)
//            ->where('status_may', 1)
            // ->whereHas('quizResponses', function (Builder $q) {
            //     $q->whereNotNull('responded_at');
            // })
            ->has('quizResponses', '>=', config('app.minimum_required_quiz_responses'))
            ->get();
    }

    /**
     * Finds SchoolClasses NOT eligible for receiving certificates
     *
     * @return Collection
     */
    public function findNotEligibleForCertificate(): Collection
    {
        return SchoolClass::query()
//            ->where('status_january', 0)
//            ->orWhereNull('status_january')
//            ->orWhere('status_march', 0)
//            ->orWhereNull('status_march')
//            ->orWhere('status_may', 0)
//            ->orWhereNull('status_may')
            ->doesntHave('quizResponses')
            ->orWhereHas('quizResponses', function (Builder $q) {
                $q->whereNull('responded_at');
            })
            ->get();

    }

    /**
     * Finds SchoolClasses that are eligible for receiving certificate but don't have one.
     *
     * @return Collection
     */
    public function findEligibleButMissingCertificate(): Collection
    {
        return $this->findEligibleForCertificate()
            ->diff($this->findHavingCertificate());
    }

    /**
     * Finds SchoolClasses having generated certificates, without checking if they are allowed to have it
     *
     * @return Collection
     */
    public function findHavingCertificate(): Collection
    {
        return SchoolClass::query()
            ->whereHas('certificate')
            ->get();
    }

    /**
     * Finds SchoolClasses having generated certificates, without checking if they are allowed to have it
     *
     * @return Collection
     */
    public function findHavingCertificateToBeSent(): Collection
    {
        return SchoolClass::query()
            ->whereHas('certificate', function($query) {
                return $query->whereNull('sent_at');
            })
            ->get();
    }

}
