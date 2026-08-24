<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Mail Driver
    |--------------------------------------------------------------------------
    |
    | Laravel supports both SMTP and PHP's "mail" function as drivers for the
    | sending of e-mail. You may specify which one you're using throughout
    | your application here. By default, Laravel is setup for SMTP mail.
    |
    | Supported: "smtp", "sendmail", "mailgun", "mandrill", "ses",
    | Supported: "smtp", "sendmail", "mailgun", "ses", "log", "array"
    |
    | This application uses "smtp". That is deliberate and load bearing: the
    | SparkPost driver is removed in Laravel 6.0, and SMTP is the one transport
    | every Laravel version supports, so the mail vendor can be changed by
    | editing .env alone -- no code, no package, no framework coupling.
    |
    */

    'driver' => env('MAIL_DRIVER', 'smtp'),

    /*
    |--------------------------------------------------------------------------
    | SMTP Host Address
    |--------------------------------------------------------------------------
    |
    | Here you may provide the host address of the SMTP server used by your
    | applications. A default option is provided that is compatible with
    | the Mailgun mail service which will provide reliable deliveries.
    |
    */

    'host' => env('MAIL_HOST', 'smtp.mailgun.org'),

    /*
    |--------------------------------------------------------------------------
    | SMTP Host Port
    |--------------------------------------------------------------------------
    |
    | This is the SMTP port used by your application to deliver e-mails to
    | users of the application. Like the host we have set this value to
    | stay compatible with the Mailgun e-mail application by default.
    |
    */

    'port' => env('MAIL_PORT', 587),

    /*
    |--------------------------------------------------------------------------
    | Global "From" Address
    |--------------------------------------------------------------------------
    |
    | You may wish for all e-mails sent by your application to be sent from
    | the same address. Here, you may specify a name and address that is
    | used globally for all e-mails that are sent by your application.
    |
    */

    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
        'name' => env('MAIL_FROM_NAME', 'Example'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Global "Reply-To" Address
    |--------------------------------------------------------------------------
    |
    | Replies are handled by a different mailbox than the sending address, so
    | every Mailable sets Reply-To explicitly. This lives in config rather than
    | being read from env() at send time: env() returns null once config:cache
    | has run, and a null Reply-To fails silently.
    |
    */

    'reply_to' => [
        'address' => env('MAIL_REPLY_TO'),
        'name' => env('MAIL_FROM_NAME', 'Example'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Transport Security
    |--------------------------------------------------------------------------
    |
    | Laravel 13 no longer reads MAIL_ENCRYPTION -- MailManager derives the
    | connection from the scheme alone, so the old key is silently ignored and
    | was removed rather than left here looking load bearing.
    |
    | An empty scheme lets the port decide: 465 becomes "smtps" (implicit TLS
    | from the first byte), anything else "smtp", which connects in the clear
    | and upgrades via STARTTLS the moment the server advertises it -- before
    | AUTH, so credentials never cross an unencrypted socket. Port 587 with an
    | empty scheme is therefore STARTTLS, which is what both SparkPost and
    | Scaleway document.
    |
    | 'require_tls' closes what remains. Without it a server that fails to
    | advertise STARTTLS -- misconfigured, or a stripped connection -- is
    | accepted silently and the API key travels as plaintext. With it the
    | send aborts instead. On by default; a local sink such as Mailpit speaks
    | no TLS at all and needs MAIL_REQUIRE_TLS=false.
    |
    */

    'scheme' => env('MAIL_SCHEME'),

    'require_tls' => env('MAIL_REQUIRE_TLS', true),

    /*
    |--------------------------------------------------------------------------
    | SMTP Server Username
    |--------------------------------------------------------------------------
    |
    | If your SMTP server requires a username for authentication, you should
    | set it here. This will get used to authenticate with your server on
    | connection. You may also set the "password" value below this one.
    |
    */

    'username' => env('MAIL_USERNAME'),

    'password' => env('MAIL_PASSWORD'),

    /*
    |--------------------------------------------------------------------------
    | Recipient Override
    |--------------------------------------------------------------------------
    |
    | When set, every message is redirected to this address instead of its real
    | recipients. This exists because the moment a developer machine is pointed
    | at a live provider to test deliverability, it is also one `queue:work`
    | away from mailing real teachers out of a restored production database.
    |
    | Unset in production, where it must stay unset. AppServiceProvider only
    | touches the mailer when this has a value.
    |
    */

    'always_to' => env('MAIL_ALWAYS_TO'),

    /*
    |--------------------------------------------------------------------------
    | Sendmail System Path
    |--------------------------------------------------------------------------
    |
    | When using the "sendmail" driver to send e-mails, we will need to know
    | the path to where Sendmail lives on this server. A default path has
    | been provided here, which will work well on most of your systems.
    |
    */

    'sendmail' => '/usr/sbin/sendmail -bs',

    /*
    |--------------------------------------------------------------------------
    | Markdown Mail Settings
    |--------------------------------------------------------------------------
    |
    | If you are using Markdown based email rendering, you may configure your
    | theme and component paths here, allowing you to customize the design
    | of the emails. Or, you may simply stick with the Laravel defaults!
    |
    */

    'markdown' => [
        'theme' => 'default',

        'paths' => [
            resource_path('views/vendor/mail'),
        ],
    ],

];
