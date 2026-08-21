<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery as Middleware;

class PreventRequestForgery extends Middleware
{
    /**
     * Indicates whether the XSRF-TOKEN cookie should be set on the response.
     *
     * @var bool
     */
    protected $addHttpCookie = true;

    /**
     * The URIs that should be excluded from request-forgery verification.
     *
     * Deliberately empty. The quiz-maker webhook is the only unauthenticated
     * POST route, and it lives under routes/api.php, which does not carry the
     * web middleware group -- so it is already outside this check rather than
     * excepted from it.
     *
     * @var array
     */
    protected $except = [
        //
    ];
}
