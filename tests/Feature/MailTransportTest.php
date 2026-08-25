<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Guard: the mail transport must stay vendor-neutral SMTP.
 *
 * This application shipped with MAIL_DRIVER=sparkpost. Laravel removes the
 * SparkPost driver in 6.0 with no first-party replacement -- the upgrade guide
 * says only to "adopt a community maintained package of your choice" -- so
 * that setting is a hard stop two hops in.
 *
 * The answer is not a different driver. It is SMTP, which every Laravel
 * version from 4 to 13 supports identically. Keeping SMTP means the mail
 * vendor is chosen entirely by .env values, so it can be decided, changed or
 * reversed without touching code, adding a package, or coupling to a
 * framework version.
 *
 * These assertions exist so that a future convenience -- "let's use the
 * official <vendor> driver" -- is a deliberate decision rather than something
 * that quietly reintroduces the coupling this migration removed.
 */
class MailTransportTest extends TestCase
{
    public function testNoSparkpostCredentialsRemainInServices()
    {
        $this->assertNull(
            config('services.sparkpost'),
            'The SparkPost credentials block is dead configuration; the driver does not exist past Laravel 5.x.'
        );
    }

    public function testTheExampleEnvironmentDoesNotAskForARemovedDriver()
    {
        $example = file_get_contents(base_path('.env.example'));

        $this->assertDoesNotMatchRegularExpression(
            '/^MAIL_DRIVER\s*=\s*sparkpost/mi',
            $example,
            '.env.example still tells a new deployment to use a driver Laravel 6 deletes.'
        );
        $this->assertMatchesRegularExpression(
            '/^MAIL_DRIVER\s*=\s*smtp/mi',
            $example,
            '.env.example should point new deployments at SMTP.'
        );
    }

    /**
     * The guard in Tests\TestCase is what actually enforces this on every test.
     * This asserts it is wired up, the same way DatabaseIsolationTest does for
     * the database.
     */
    public function testTestsCannotDeliverMail()
    {
        $driver = config('mail.driver') ?: config('mail.default');

        $this->assertContains(
            $driver,
            ['array', 'log', 'null'],
            'The test suite resolved a real mail transport. A green run could have '
            .'delivered mail to real recipients.'
        );
    }

    public function testTheApplicationDeclaresAnSmtpHostAndPort()
    {
        // Not asserting the driver itself: phpunit.xml overrides it to "array"
        // so tests never send. What matters is that the SMTP settings exist.
        $this->assertNotNull(config('mail.host'));
        $this->assertNotNull(config('mail.port'));
    }
}
