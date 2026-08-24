<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class MailTest extends Command {
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mail:test {recipient}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send one message through the configured SMTP transport, bypassing the queue';

    /**
     * Execute the console command.
     *
     * Deliberately synchronous. Every mail in this application is queued, so a
     * failure normally surfaces as a row in failed_jobs that nobody reads --
     * which is exactly the wrong feedback loop when the thing being tested is
     * whether the credentials and the sending domain work at all.
     *
     * @return int
     */
    public function handle() {
        $recipient = $this->argument('recipient');

        $transport = Mail::mailer()->getSymfonyTransport();

        $this->table(['Setting', 'Value'], [
            ['Transport', (string) $transport],
            ['Username', config('mail.username') ?: '(none)'],
            ['Password', config('mail.password') ? '(set)' : '(none)'],
            ['TLS required', config('mail.require_tls') ? 'yes' : 'no'],
            ['From', config('mail.from.address')],
            ['Reply-To', config('mail.reply_to.address') ?: '(none)'],
            ['Redirected to', config('mail.always_to') ?: '(not redirected)'],
        ]);

        if (config('mail.always_to')) {
            $this->warn(sprintf(
                'MAIL_ALWAYS_TO is set, so this goes to %s rather than %s.',
                config('mail.always_to'),
                $recipient
            ));
        }

        try {
            Mail::raw(
                "Test d'envoi depuis Mission Nichtrauchen.\n\n"
                .'Transport : '.$transport."\n"
                .'Date : '.now()->toDateTimeString()."\n",
                function ($message) use ($recipient) {
                    $message->to($recipient)->subject('Mission Nichtrauchen - test SMTP');
                }
            );
        } catch (TransportExceptionInterface $e) {
            // The SMTP server's own refusal is the useful part: a 550 names an
            // unverified sending domain, a 535 names bad credentials.
            $this->error('The transport refused the message:');
            $this->error($e->getMessage());

            return 1;
        }

        $this->info('Accepted by the server. Delivery itself is now the provider\'s business -- check its event log if nothing arrives.');

        return 0;
    }
}
