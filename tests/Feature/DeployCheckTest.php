<?php

namespace Tests\Feature;

use App\Quiz;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * A check that cannot fail is not a check.
 *
 * deploy:check exists to catch settings that break the contest silently -- mail
 * that never leaves, links nobody can open, a worker nobody started. Its whole
 * value is the non-zero exit, so each of these tests puts one setting wrong and
 * insists the command notices.
 */
class DeployCheckTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A correctly configured production environment, as a starting point for
     * each test to break in exactly one way.
     */
    private function configureAsProduction(array $overrides = []): void
    {
        Storage::fake('local');

        config(array_merge([
            'app.env' => 'production',
            'app.debug' => false,
            'app.key' => 'base64:'.base64_encode(random_bytes(32)),
            'app.url' => 'https://www.missionnichtrauchen.lu',
            'app.minimum_required_quiz_responses' => 5,
            'mail.driver' => 'smtp',
            'mail.host' => 'smtp.tem.scaleway.com',
            'mail.username' => 'project-id',
            'mail.password' => 'secret',
            'mail.from.address' => 'envoi@missionnichtrauchen.lu',
            'mail.require_tls' => true,
            'mail.always_to' => null,
            'queue.default' => 'database',
            'session.driver' => 'database',
        ], $overrides));
    }

    public function testACorrectlyConfiguredEnvironmentPasses()
    {
        $this->configureAsProduction();

        $this->artisan('deploy:check')->assertExitCode(0);
    }

    /**
     * The one that matters most. Mails are built by queue workers, which have
     * no HTTP request: Laravel fabricates one from APP_URL. So an http:// value
     * here puts http:// links in every certificate, party and quiz email --
     * and nothing anywhere reports it.
     */
    public function testAnInsecurePublicUrlFailsTheCheck()
    {
        $this->configureAsProduction(['app.url' => 'http://www.missionnichtrauchen.lu']);

        $this->artisan('deploy:check')->assertExitCode(1);
    }

    /**
     * Set on developer machines so test mail cannot reach teachers. Left on in
     * production it would divert every message the contest depends on.
     */
    public function testARecipientOverrideFailsTheCheck()
    {
        $this->configureAsProduction(['mail.always_to' => 'dev@example.test']);

        $this->artisan('deploy:check')->assertExitCode(1);
    }

    public function testADebugEnabledEnvironmentFailsTheCheck()
    {
        $this->configureAsProduction(['app.debug' => true]);

        $this->artisan('deploy:check')->assertExitCode(1);
    }

    public function testAMailTransportThatDeliversNothingFailsTheCheck()
    {
        $this->configureAsProduction(['mail.driver' => 'log']);

        $this->artisan('deploy:check')->assertExitCode(1);
    }

    public function testMissingMailCredentialsFailTheCheck()
    {
        $this->configureAsProduction(['mail.password' => null]);

        $this->artisan('deploy:check')->assertExitCode(1);
    }

    /**
     * quiz:update runs every minute. A quiz left open well past its closing
     * time is the one symptom of a dead scheduler this application produces by
     * itself, so the check reads it rather than adding a heartbeat.
     */
    public function testAQuizLeftOpenPastItsClosingTimeFailsTheCheck()
    {
        $this->configureAsProduction();

        Quiz::create([
            'name' => 'Quiz oublié',
            'email_text' => 'x',
            'max_score' => 10,
            'closes_at' => now()->subHour(),
            'state' => Quiz::STATE_RUNNING,
        ]);

        $this->artisan('deploy:check')->assertExitCode(1);
    }

    public function testAStalledQueueFailsTheCheck()
    {
        $this->configureAsProduction();

        \DB::table('jobs')->insert([
            'queue' => 'default',
            'payload' => '{}',
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => now()->subDay()->getTimestamp(),
            'created_at' => now()->subDay()->getTimestamp(),
        ]);

        $this->artisan('deploy:check')->assertExitCode(1);
    }

    /**
     * The whole point of the Redis branch. Before it existed, deploy:check
     * returned early for any driver other than "database", so a Redis queue
     * got no worker check and no failed-job check at all -- and reported a
     * clean bill of health while nothing was being processed.
     */
    public function testAnUnreachableRedisFailsTheCheck()
    {
        $this->configureAsProduction([
            'queue.default' => 'redis',
            // A port nothing listens on, so the connection is refused rather
            // than hanging.
            'database.redis.default' => [
                'host' => '127.0.0.1',
                'port' => 6399,
                'database' => 0,
            ],
        ]);

        $this->artisan('deploy:check')->assertExitCode(1);
    }

    /**
     * Failed jobs are silent losses whatever the driver: CustomEmail marks a
     * message sent in its constructor, so the ledger will not allow a retry.
     */
    public function testFailedJobsAreReportedOnARedisQueueToo()
    {
        $this->configureAsProduction(['queue.default' => 'redis']);

        \DB::table('failed_jobs')->insert([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'connection' => 'redis',
            'queue' => 'default',
            'payload' => '{}',
            'exception' => 'test',
            'failed_at' => now(),
        ]);

        // A warning, not a failure, so this asserts the row is present rather
        // than the exit code.
        $this->artisan('deploy:check')
            ->expectsOutputToContain('perte silencieuse');
    }
}
