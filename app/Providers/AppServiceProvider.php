<?php

namespace App\Providers;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        \Schema::defaultStringLength(191);

        $this->redirectAllMailWhenConfigured();
    }

    /**
     * Send every message to a single address instead of its real recipients.
     *
     * Only when mail.always_to has a value, which production leaves unset. The
     * guard matters twice over: it keeps the mailer from being resolved on
     * every request in production, and it means a stray MAIL_ALWAYS_TO is the
     * only thing that can ever divert live mail.
     *
     * @return void
     */
    private function redirectAllMailWhenConfigured()
    {
        $address = config('mail.always_to');

        if (! $address) {
            return;
        }

        Mail::alwaysTo($address);
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }
}
