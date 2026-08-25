<?php

namespace Tests\Feature;

use App\EditableEmail;
use App\Mail\CustomEmail;
use App\SentEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsDomainFixtures;
use Tests\TestCase;

/**
 * Tripwire: the send-once ledger.
 *
 * Scheduled mail fires on the day an EditableDate matches, and the only thing
 * stopping a class receiving the same mail once per scheduler tick is the
 * sent_emails ledger. Two properties hold it up, and both are easy to break
 * without noticing:
 *
 *   1. The ledger is written by CustomEmail's CONSTRUCTOR, not by build() or
 *      by sending. Constructing one already counts as sent.
 *   2. isSentToUser()/isSentToClass() compare with containsStrict(), so the
 *      ids coming back from the database must be integers. A driver or cast
 *      change that yields strings turns every check silently false -- which
 *      means every class gets every mail again, every minute.
 */
class SentEmailLedgerTest extends TestCase
{
    use RefreshDatabase, BuildsDomainFixtures;

    public function testConstructingACustomEmailAlreadyMarksItAsSent()
    {
        $teacher = $this->makeTeacher();
        $class = $this->makeClass($teacher);
        $email = EditableEmail::find(EditableEmail::$MAIL_NEWSLETTER_START);

        $this->assertSame(0, SentEmail::query()->count());

        new CustomEmail($email, $teacher, $class);

        $this->assertSame(
            2,
            SentEmail::query()->count(),
            'The constructor should write one ledger row for the user and one for the class.'
        );
        $this->assertSame(1, SentEmail::query()->whereNotNull('user_id')->count());
        $this->assertSame(1, SentEmail::query()->whereNotNull('school_class_id')->count());
    }

    public function testANullClassWritesOnlyTheUserRow()
    {
        $teacher = $this->makeTeacher();
        $email = EditableEmail::find(EditableEmail::$MAIL_NEWSLETTER_START);

        new CustomEmail($email, $teacher, null);

        $this->assertSame(1, SentEmail::query()->count());
        $this->assertSame(1, SentEmail::query()->whereNotNull('user_id')->count());
    }

    public function testTheLedgerIsWhatStopsASecondSend()
    {
        $teacher = $this->makeTeacher();
        $class = $this->makeClass($teacher);
        $email = EditableEmail::find(EditableEmail::$MAIL_NEWSLETTER_START);

        $this->assertFalse($email->isSentToClass($class));
        $this->assertFalse($email->isSentToUser($teacher->user));

        new CustomEmail($email, $teacher, $class);

        // Reloaded, because the check reads a relation that was already cached.
        $reloaded = EditableEmail::find(EditableEmail::$MAIL_NEWSLETTER_START);

        $this->assertTrue($reloaded->isSentToClass($class));
        $this->assertTrue($reloaded->isSentToUser($teacher->user));
    }

    /**
     * The senders in MailController re-resolve the EditableEmail per class, so
     * this stale-cache behaviour is not a live bug -- but it is load-bearing,
     * and a change to relation caching would flip it without any other signal.
     */
    public function testTheSentEmailsRelationIsCachedOnTheInstanceThatWroteIt()
    {
        $teacher = $this->makeTeacher();
        $class = $this->makeClass($teacher);
        $email = EditableEmail::find(EditableEmail::$MAIL_NEWSLETTER_START);

        $email->isSentToClass($class);   // primes the relation cache with zero rows
        new CustomEmail($email, $teacher, $class);

        $this->assertFalse(
            $email->isSentToClass($class),
            'Expected the cached relation to still report not-sent on this instance.'
        );
        $this->assertTrue($email->fresh()->isSentToClass($class));
    }

    /**
     * containsStrict() means a string id never matches an int id.
     */
    public function testLedgerIdsComeBackFromTheDatabaseAsIntegers()
    {
        $teacher = $this->makeTeacher();
        $class = $this->makeClass($teacher);
        $email = EditableEmail::find(EditableEmail::$MAIL_NEWSLETTER_START);

        new CustomEmail($email, $teacher, $class);

        $row = SentEmail::query()->whereNotNull('school_class_id')->first();

        $this->assertIsInt($row->school_class_id);
        $this->assertIsInt($class->id);

        $userRow = SentEmail::query()->whereNotNull('user_id')->first();
        $this->assertIsInt($userRow->user_id);
    }
}
