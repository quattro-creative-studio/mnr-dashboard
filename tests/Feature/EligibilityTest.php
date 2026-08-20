<?php

namespace Tests\Feature;

use App\Http\Repositories\SchoolClassRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsDomainFixtures;
use Tests\TestCase;

/**
 * Tripwire: who gets a party invitation and a certificate.
 *
 * Eligibility used to be driven by the january/march/may follow-up statuses.
 * It is now driven purely by how many quiz_responses a class has, compared
 * against config('app.minimum_required_quiz_responses'). The old mechanism is
 * deliberately kept in the codebase but inert -- commented-out blocks, an
 * unconditional `return true` in arePreviousStatusesPositive(), and an
 * unreachable `return` after the quiz check in isEligibleForParty().
 *
 * These tests pin the threshold AND pin the fact that the old path is dead,
 * so that a future refactor cannot quietly resurrect it.
 */
class EligibilityTest extends TestCase
{
    use RefreshDatabase, BuildsDomainFixtures;

    /** @var int */
    private $threshold = 5;

    protected function setUp()
    {
        parent::setUp();

        config(['app.minimum_required_quiz_responses' => $this->threshold]);
    }

    public function testAClassBelowTheThresholdIsNotEligible()
    {
        $class = $this->makeClass();
        $this->giveQuizResponses($class, $this->threshold - 1);

        $this->assertFalse($class->isEligibleForParty());
    }

    public function testAClassExactlyOnTheThresholdIsEligible()
    {
        $class = $this->makeClass();
        $this->giveQuizResponses($class, $this->threshold);

        $this->assertTrue(
            $class->isEligibleForParty(),
            'The comparison is >=, so the threshold itself must qualify.'
        );
    }

    public function testAClassAboveTheThresholdIsEligible()
    {
        $class = $this->makeClass();
        $this->giveQuizResponses($class, $this->threshold + 1);

        $this->assertTrue($class->isEligibleForParty());
    }

    /**
     * The old rule would have made this class eligible. The new one must not.
     */
    public function testTheRetiredFollowUpStatusesNoLongerGrantEligibility()
    {
        $class = $this->makeClass(null, [
            'status_january' => 1,
            'status_march' => 1,
            'status_may' => 1,
            'status_party' => 1,
        ]);

        $this->assertFalse(
            $class->isEligibleForParty(),
            'Follow-up statuses must no longer influence eligibility.'
        );
    }

    public function testPreviousStatusesAreAlwaysReportedPositive()
    {
        $class = $this->makeClass(null, [
            'status_january' => null,
            'status_march' => null,
            'status_may' => null,
        ]);

        $this->assertTrue($class->arePreviousStatusesPositive('january'));
        $this->assertTrue($class->arePreviousStatusesPositive('march'));
        $this->assertTrue($class->arePreviousStatusesPositive('may'));
        $this->assertTrue($class->arePreviousStatusesPositive('anything-at-all'));
    }

    public function testTheRepositoryAgreesWithTheModel()
    {
        $below = $this->makeClass();
        $this->giveQuizResponses($below, $this->threshold - 1);

        $exactly = $this->makeClass();
        $this->giveQuizResponses($exactly, $this->threshold);

        $repository = app(SchoolClassRepository::class);

        $party = $repository->findEligibleForFinalParty()->pluck('id');
        $certificate = $repository->findEligibleForCertificate()->pluck('id');

        $this->assertTrue($party->contains($exactly->id));
        $this->assertFalse($party->contains($below->id));

        $this->assertTrue($certificate->contains($exactly->id));
        $this->assertFalse($certificate->contains($below->id));
    }

    public function testPartyReminderOnlyTargetsEligibleClassesWithoutGroups()
    {
        $withoutGroups = $this->makeClass();
        $this->giveQuizResponses($withoutGroups, $this->threshold);

        $repository = app(SchoolClassRepository::class);

        $this->assertTrue(
            $repository->findEligibleForFinalPartyReminder()->pluck('id')->contains($withoutGroups->id)
        );
        $this->assertFalse(
            $repository->findEligibleForFinalPartyInformations()->pluck('id')->contains($withoutGroups->id),
            'Informations go to classes that HAVE registered groups.'
        );
    }
}
