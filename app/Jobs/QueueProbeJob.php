<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

/**
 * A job whose only purpose is to prove a worker picked it up.
 *
 * Deliberately touches no domain data: testing the queue by triggering a real
 * feature mixes "is the worker running" with "does that feature work", and a
 * failure then means either.
 *
 * With a recipient it also sends from inside the worker process, which is the
 * combination that actually matters here -- every mail in this application is
 * queued, so a worker that runs but cannot reach SMTP delivers nothing while
 * looking perfectly healthy.
 */
class QueueProbeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @var string */
    private $token;

    /** @var string|null */
    private $recipient;

    public function __construct(string $token, ?string $recipient = null)
    {
        $this->token = $token;
        $this->recipient = $recipient;
    }

    public static function cacheKey(string $token): string
    {
        return 'queue-probe:'.$token;
    }

    public function handle(): void
    {
        $result = ['at' => now()->toDateTimeString(), 'mail' => null];

        if ($this->recipient !== null) {
            try {
                Mail::raw(
                    "Ce message a été envoyé depuis un worker de queue.\n\n"
                    ."Jeton : {$this->token}\n"
                    .'Date  : '.now()->toDateTimeString()."\n",
                    function ($message) {
                        $message->to($this->recipient)
                            ->subject('Mission Nichtrauchen - test de la file');
                    }
                );
                $result['mail'] = 'envoyé';
            } catch (\Throwable $e) {
                // Recorded rather than rethrown: a failed send here is the
                // answer the command is looking for, not an incident.
                $result['mail'] = 'refusé : '.$e->getMessage();
            }
        }

        Cache::put(self::cacheKey($this->token), $result, now()->addMinutes(10));
    }
}
