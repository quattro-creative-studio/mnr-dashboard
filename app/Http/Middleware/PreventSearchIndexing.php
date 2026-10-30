<?php

namespace App\Http\Middleware;

use Closure;

/**
 * Keeps every page of the portal out of search engines, in every environment.
 *
 * Nothing here is meant to be found through a search: the public website is
 * what ranks, and the unauthenticated pages are token URLs (certificates,
 * follow-up and party answers) that must never end up in search results.
 */
class PreventSearchIndexing {
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  \Closure $next
     * @return mixed
     */
    public function handle($request, Closure $next) {
        $response = $next($request);
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        return $response;
    }
}
