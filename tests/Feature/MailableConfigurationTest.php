<?php

namespace Tests\Feature;

use App\EditableEmail;
use App\Mail\CustomEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsDomainFixtures;
use Tests\TestCase;

/**
 * Regression: Mailables must not read env() at send time.
 *
 * All five Mailables used to call env('MAIL_FROM_ADDRESS') and
 * env('MAIL_REPLY_TO') inside build(). env() only reads the process
 * environment, and once `php artisan config:cache` has run -- which is the
 * normal state of a deployed Laravel application, and what Forge does on every
 * deploy -- the .env file is never loaded, so those calls return null.
 *
 * The failure is silent: mail goes out with no From address and no Reply-To,
 * or the transport rejects it and the job lands in failed_jobs where nobody
 * looks. This test simulates that exact condition by removing the variables
 * from the environment after boot, which is precisely what config caching does.
 */
class MailableConfigurationTest extends TestCase
{
    use RefreshDatabase, BuildsDomainFixtures;

    /** @var array */
    private $savedEnv = [];

    protected function tearDown(): void
    {
        foreach ($this->savedEnv as $key => $value) {
            if ($value === false) {
                putenv($key);
                continue;
            }

            putenv("{$key}={$value}");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }

        parent::tearDown();
    }

    /**
     * Remove a variable from the environment the way config:cache effectively
     * does, remembering it so tearDown can put it back.
     */
    private function stripFromEnvironment(string $key): void
    {
        $this->savedEnv[$key] = getenv($key);

        putenv($key);
        unset($_ENV[$key], $_SERVER[$key]);
    }

    private function buildableMail(): CustomEmail
    {
        $teacher = $this->makeTeacher();
        $class = $this->makeClass($teacher);

        $email = EditableEmail::find(EditableEmail::$MAIL_NEWSLETTER_START);
        $email->text = 'Bonjour %PROF%, la classe %NOM_CLASSE% est inscrite.';
        $email->subject = 'Sujet de test';
        $email->save();

        return new CustomEmail($email->fresh(), $teacher, $class);
    }

    public function testTheSenderAndReplyToComeFromConfiguration()
    {
        config([
            'mail.from.address' => 'envoi@missionnichtrauchen.lu',
            'mail.from.name' => 'Mission Nichtrauchen',
            'mail.reply_to.address' => 'no-reply@missionnichtrauchen.lu',
        ]);

        $mail = $this->buildableMail();
        $mail->build();

        $this->assertCount(1, $mail->from);
        $this->assertSame('envoi@missionnichtrauchen.lu', $mail->from[0]['address']);
        $this->assertSame('Mission Nichtrauchen', $mail->from[0]['name']);

        $this->assertCount(1, $mail->replyTo);
        $this->assertSame('no-reply@missionnichtrauchen.lu', $mail->replyTo[0]['address']);
    }

    /**
     * The actual regression. Before the fix this test fails with a null From
     * address, which is what production has been doing whenever config was
     * cached.
     */
    public function testTheSenderSurvivesAConfigCachedEnvironment()
    {
        config([
            'mail.from.address' => 'envoi@missionnichtrauchen.lu',
            'mail.from.name' => 'Mission Nichtrauchen',
            'mail.reply_to.address' => 'no-reply@missionnichtrauchen.lu',
        ]);

        $this->stripFromEnvironment('MAIL_FROM_ADDRESS');
        $this->stripFromEnvironment('MAIL_REPLY_TO');
        $this->stripFromEnvironment('APP_NAME');

        // Precondition: env() is now blind, exactly as it is after config:cache.
        $this->assertNull(env('MAIL_FROM_ADDRESS'));
        $this->assertNull(env('MAIL_REPLY_TO'));

        $mail = $this->buildableMail();
        $mail->build();

        $this->assertSame('envoi@missionnichtrauchen.lu', $mail->from[0]['address']);
        $this->assertSame('Mission Nichtrauchen', $mail->from[0]['name']);
        $this->assertSame('no-reply@missionnichtrauchen.lu', $mail->replyTo[0]['address']);
    }

    public function testNoMailableReadsTheEnvironmentDirectly()
    {
        $offenders = [];

        foreach (glob(app_path('Mail/*.php')) as $file) {
            if (strpos(file_get_contents($file), 'env(') !== false) {
                $offenders[] = basename($file);
            }
        }

        $this->assertSame(
            [],
            $offenders,
            'These Mailables read env() at send time and will lose their values '
            .'under config:cache: '.implode(', ', $offenders)
        );
    }
}
