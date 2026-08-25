<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Tests\TestCase;

/**
 * Regression: MAIL_ENCRYPTION stopped being read, and nothing said so.
 *
 * Under Laravel 5.7 the transport was built from MAIL_ENCRYPTION=tls. Laravel
 * 13 builds it from the scheme instead and never looks at that key, so the
 * setting kept sitting in config and .env meaning nothing at all. That is the
 * worst kind of stale configuration: it reads as though transport security is
 * pinned when in fact nothing is pinning it.
 *
 * What actually protects the credentials is Symfony's opportunistic STARTTLS,
 * which upgrades the socket before AUTH -- but only if the server advertises
 * STARTTLS. A server that does not advertise it, whether misconfigured or
 * because the connection was tampered with, is accepted in silence and the
 * API key is written to a plaintext socket. 'require_tls' turns that silence
 * into a refusal.
 *
 * These tests build transports; none of them opens a socket, since Symfony
 * connects lazily on the first send.
 */
class SmtpTransportSecurityTest extends TestCase
{
    /**
     * Configure a provider the way .env does, then build its transport.
     */
    private function transportFor(array $overrides = []): EsmtpTransport
    {
        config(array_merge([
            'mail.driver' => 'smtp',
            'mail.host' => 'smtp.eu.sparkpostmail.com',
            'mail.port' => 587,
            'mail.username' => 'SMTP_Injection',
            'mail.password' => 'not-a-real-key',
            'mail.scheme' => null,
            'mail.require_tls' => true,
        ], $overrides));

        // Drop any mailer built from the previous configuration.
        Mail::purge('smtp');

        $transport = Mail::mailer('smtp')->getSymfonyTransport();

        $this->assertInstanceOf(EsmtpTransport::class, $transport);

        return $transport;
    }

    public function testPortFiveEightSevenNegotiatesStartTlsAndRefusesToSendWithoutIt()
    {
        $transport = $this->transportFor();

        // "smtp" rather than "smtps": the socket opens in the clear and is
        // upgraded by STARTTLS before any credential is sent.
        $this->assertSame('smtp://smtp.eu.sparkpostmail.com:587', (string) $transport);

        $this->assertTrue(
            $transport->isTlsRequired(),
            'The transport would fall back to plaintext if the server did not '
            .'offer STARTTLS, sending the SMTP API key in the clear.'
        );
    }

    public function testPortFourSixFiveUsesImplicitTlsWithoutBeingToldTo()
    {
        $transport = $this->transportFor(['mail.port' => 465]);

        // Symfony omits the port when it is the scheme's default, so the bare
        // "smtps://" host is itself the evidence that port 465 flipped the
        // scheme to implicit TLS.
        $this->assertSame('smtps://smtp.eu.sparkpostmail.com', (string) $transport);
    }

    public function testAnExplicitSchemeOverridesThePort()
    {
        $transport = $this->transportFor(['mail.scheme' => 'smtps', 'mail.port' => 2465]);

        $this->assertSame('smtps://smtp.eu.sparkpostmail.com:2465', (string) $transport);
    }

    /**
     * A local sink has to be able to opt out, or nobody can develop offline.
     */
    public function testTlsCanBeWaivedForALocalSink()
    {
        $transport = $this->transportFor([
            'mail.host' => 'localhost',
            'mail.port' => 1025,
            'mail.require_tls' => false,
        ]);

        $this->assertFalse($transport->isTlsRequired());
    }

    /**
     * The point of the whole file: keep the dead key from coming back and
     * looking authoritative again.
     */
    public function testTheConfigurationDoesNotCarryAKeyLaravelIgnores()
    {
        $this->assertArrayNotHasKey(
            'encryption',
            config('mail'),
            'config/mail.php declares "encryption", which Laravel 13 never '
            .'reads. Transport security comes from "scheme" and "require_tls".'
        );
    }

    public function testTheDefaultIsToRequireTls()
    {
        // Re-evaluate the config file with the variable absent, so this asserts
        // the declared default rather than whatever this machine's .env says.
        $saved = getenv('MAIL_REQUIRE_TLS');
        putenv('MAIL_REQUIRE_TLS');
        unset($_ENV['MAIL_REQUIRE_TLS'], $_SERVER['MAIL_REQUIRE_TLS']);

        try {
            $declared = require base_path('config/mail.php');
        } finally {
            if ($saved !== false) {
                putenv("MAIL_REQUIRE_TLS={$saved}");
                $_ENV['MAIL_REQUIRE_TLS'] = $saved;
                $_SERVER['MAIL_REQUIRE_TLS'] = $saved;
            }
        }

        $this->assertTrue(
            $declared['require_tls'],
            'Transport security must be opt-out, not opt-in.'
        );
    }
}
