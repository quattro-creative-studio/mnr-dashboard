<?php

namespace App\Http\Middleware;

use Closure;

/**
 * Lets the public website embed the page in an iframe.
 *
 * nginx adds "X-Frame-Options: SAMEORIGIN" to every response. Browsers ignore
 * X-Frame-Options when a CSP frame-ancestors directive is present, so this
 * header alone is enough to allow the public site while still refusing others.
 */
class AllowFramingByPublicSite {
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  \Closure $next
     * @return mixed
     */
    public function handle($request, Closure $next) {
        $response = $next($request);
        $response->headers->set('Content-Security-Policy', "frame-ancestors 'self' https://missionnichtrauchen.lu");
        return $response;
    }
}
