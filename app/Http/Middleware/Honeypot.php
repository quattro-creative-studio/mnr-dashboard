<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Encryption\DecryptException;

/**
 * Drops form submissions made by spam bots.
 *
 * Pairs with the x-honeypot component, which adds two fields to the form:
 * - a field hidden from humans that bots fill in like any other input;
 * - the encrypted time the form was rendered, since bots post within a second
 *   of loading the page while a human needs a few seconds to fill it in.
 *
 * A caught submission is redirected back as if nothing happened, so the bot
 * gets no hint of what gave it away.
 */
class Honeypot {
    const FIELD = 'website';
    const TIME_FIELD = 'form_rendered_at';
    const MIN_SECONDS = 3;

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  \Closure $next
     * @return mixed
     */
    public function handle($request, Closure $next) {
        $reason = $this->spamReason($request);
        if($reason !== null) {
            \Log::warning('Honeypot rejected a submission (' . $reason . ') on ' . $request->path() . ' from ' . $request->ip());
            return redirect()->back()->withInput($request->except([
                'password', 'password_confirmation',
                'teacher_password', 'teacher_password_confirmation',
                self::FIELD, self::TIME_FIELD,
            ]));
        }
        return $next($request);
    }

    private function spamReason($request) {
        if(filled($request->input(self::FIELD)))
            return 'hidden field filled';

        try {
            $renderedAt = (int) decrypt((string) $request->input(self::TIME_FIELD));
        } catch(DecryptException $e) {
            return 'missing or tampered timestamp';
        }

        if(time() - $renderedAt < self::MIN_SECONDS)
            return 'submitted too fast';

        return null;
    }
}
