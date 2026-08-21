<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Illuminate\Http\Request;

class TrustProxies extends Middleware
{
    /**
     * The trusted proxies for this application.
     *
     * '*' is safe only because the application container is unreachable except
     * through nginx on loopback. On a Forge server that assumption does not
     * hold, and '*' would let any client spoof X-Forwarded-For to forge its IP
     * or X-Forwarded-Proto to influence generated URLs.
     *
     * MUST become null on Forge, or the load balancer's published ranges if one
     * is ever put in front. See the pre-production checklist in
     * docs/UPGRADE-LOG.md.
     *
     * @var array<int, string>|string|null
     */
    protected $proxies = '*';

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
