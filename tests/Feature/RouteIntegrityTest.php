<?php

namespace Tests\Feature;

use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Guard: the route table must stay cacheable and free of duplicate names.
 *
 * Both properties are invisible in normal use and both bite at deploy time.
 *
 * Route caching is a standard step of a Forge deploy. It was impossible in
 * this application until the stock `auth:api` /user closure was removed from
 * routes/api.php -- unreferenced scaffolding, pointing at a token guard for a
 * column the users table does not even have.
 *
 * Duplicate names are harmless today only because the pairs were byte
 * identical, so the second registration landed on the same key in the route
 * collection and simply replaced the first. Laravel 7 rejects duplicate names
 * outright and Laravel 12 changes which registration wins for uncached
 * routing, so a non-identical pair would resolve differently after an upgrade.
 */
class RouteIntegrityTest extends TestCase
{
    public function testNoRouteNameIsRegisteredTwiceInTheSource()
    {
        $counts = [];

        foreach (glob(base_path('routes/*.php')) as $file) {
            preg_match_all("/->name\('([^']+)'\)/", file_get_contents($file), $matches);

            foreach ($matches[1] as $name) {
                $counts[$name] = ($counts[$name] ?? 0) + 1;
            }
        }

        $duplicates = array_keys(array_filter($counts, function ($count) {
            return $count > 1;
        }));

        $this->assertSame(
            [],
            $duplicates,
            'These route names are registered more than once: '.implode(', ', $duplicates)
        );
    }

    public function testEveryRouteIsSerialisableSoRouteCacheCanRun()
    {
        $closures = [];

        foreach (Route::getRoutes() as $route) {
            /** @var RoutingRoute $route */
            if ($route->getAction('uses') instanceof \Closure) {
                $closures[] = implode('|', $route->methods()).' '.$route->uri();
            }
        }

        $this->assertSame(
            [],
            $closures,
            'php artisan route:cache fails on closure routes, which breaks deploys: '
            .implode(', ', $closures)
        );
    }

    /**
     * Guard: never trust every proxy again.
     *
     * '*' tells the framework to trust whatever X-Forwarded-* headers arrive,
     * so any client can forge its own IP address or change the scheme used to
     * build URLs. It was tolerable only while the application container was
     * unreachable except through nginx on loopback, and that stops being true
     * on Forge.
     *
     * A standard Forge site needs no proxy trust at all; the framework already
     * special-cases *.on-forge.com. If a CDN is ever added, trust its published
     * ranges here -- an explicit list passes this test, a wildcard does not.
     */
    public function testTheApplicationDoesNotTrustEveryProxy()
    {
        $property = new \ReflectionProperty(\App\Http\Middleware\TrustProxies::class, 'proxies');
        $property->setAccessible(true);
        $proxies = $property->getValue(new \App\Http\Middleware\TrustProxies());

        $this->assertNotContains(
            $proxies,
            ['*', '**'],
            'TrustProxies trusts every proxy. Any client can then spoof X-Forwarded-For '
            .'to forge its IP, or X-Forwarded-Proto to influence generated URLs. List the '
            .'CDN or load balancer ranges explicitly instead.'
        );
    }

    public function testThePublicTokenRoutesAreStillRegistered()
    {
        // Unauthenticated, secured only by an unguessable token in the URL.
        // Losing one of these silently breaks a whole teacher-facing flow.
        $names = collect(Route::getRoutes())->map(function ($route) {
            return $route->getName();
        })->filter()->all();

        foreach ([
            'follow-up',
            'party-response',
            'certificate.download',
            'external.quiz.show',
            'api.webhook.quizmaker',
        ] as $name) {
            $this->assertContains($name, $names, "Public token route [{$name}] is missing.");
        }
    }
}
