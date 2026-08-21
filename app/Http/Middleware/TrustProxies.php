<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Illuminate\Http\Request;

class TrustProxies extends Middleware
{
    /**
     * The trusted proxies for this application.
     *
     * null, deliberately, and matching Laravel's own skeleton default.
     *
     * This used to be '*', which trusts whatever X-Forwarded-* headers arrive.
     * That was only ever safe because the container was unreachable except
     * through nginx on loopback. On a Forge server that assumption does not
     * hold, and '*' lets any client spoof X-Forwarded-For to forge its IP, or
     * X-Forwarded-Proto to influence generated URLs.
     *
     * On a standard Forge site nothing needs to be trusted: nginx is the web
     * server talking to PHP-FPM over a local socket, not a reverse proxy in
     * front of one, and it passes the scheme through fastcgi_param rather than
     * a forwarded header. The framework also special-cases *.on-forge.com and
     * *.on-vapor.com, trusting the calling IP there automatically, so a Forge
     * staging domain keeps working with this set to null.
     *
     * If a CDN or load balancer is ever put in front -- Cloudflare, for
     * instance -- trust THEIR published ranges here explicitly. Never '*' again.
     *
     * @var array<int, string>|string|null
     */
    protected $proxies;

    /**
     * The headers that should be used to detect proxies.
     *
     * Symfony 6 removed Request::HEADER_X_FORWARDED_ALL, which this used to
     * name. The replacement below is Laravel's own default and evaluates to
     * exactly the same bitmask, 30 (0b011110) -- verified, not assumed. It is
     * an equivalence, not a widening: X_FORWARDED_PREFIX is still excluded, so
     * proxy trust is unchanged by this hop.
     *
     * @var int
     */
    protected $headers =
        Request::HEADER_X_FORWARDED_FOR |
        Request::HEADER_X_FORWARDED_HOST |
        Request::HEADER_X_FORWARDED_PORT |
        Request::HEADER_X_FORWARDED_PROTO |
        Request::HEADER_X_FORWARDED_AWS_ELB;
}
