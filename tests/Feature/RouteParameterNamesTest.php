<?php

namespace Tests\Feature;

use App\Certificate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Ramsey\Uuid\Uuid;
use Tests\Concerns\BuildsDomainFixtures;
use Tests\TestCase;

/**
 * Tripwire: route() calls must name the parameters the route actually declares.
 *
 * Laravel 5.x tolerated a mismatch, filling the missing segment positionally
 * from whatever leftover value it had, and produced a working URL by accident.
 * Laravel 6 raises UrlGenerationException instead.
 *
 * This has bitten twice already, and neither was found by reading code:
 *
 *   - PlaceHolder::getReplacement() passed 'status' to /suivi/{token}/{stillNonSmoking}.
 *     Caught at the 6.0 hop by PlaceHolderTest.
 *   - Two Blade views passed 'uid' and 'certificate' to routes declaring
 *     {certificate_uid}. Caught only by loading the page in a browser, because
 *     the earlier audit had grepped PHP and not templates. One of them is the
 *     certificate email template -- rendering it threw, so the whole send would
 *     have failed for every eligible class.
 *
 * A grep finds these once. This finds them forever, templates included.
 */
class RouteParameterNamesTest extends TestCase
{
    use RefreshDatabase, BuildsDomainFixtures;

    /**
     * @return array<string, array<int, string>> route name => declared parameters
     */
    private function declaredParameters(): array
    {
        $declared = [];

        foreach (Route::getRoutes() as $route) {
            if (! $route->getName()) {
                continue;
            }

            // Required only: an optional {param?} may legitimately be omitted,
            // so demanding it would flag correct calls.
            preg_match_all('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', $route->uri(), $required);

            $declared[$route->getName()] = $required[1];
        }

        return $declared;
    }

    private function sourceFiles(): array
    {
        $files = [];

        foreach ([resource_path('views'), app_path()] as $root) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));
            foreach ($iterator as $file) {
                if ($file->isFile() && str_ends_with($file->getFilename(), '.php')) {
                    $files[] = $file->getPathname();
                }
            }
        }

        return $files;
    }

    public function testEveryNamedRouteArgumentMatchesItsRouteDeclaration()
    {
        $declared = $this->declaredParameters();
        $problems = [];

        foreach ($this->sourceFiles() as $file) {
            $source = file_get_contents($file);

            if (! preg_match_all(
                '/route\(\s*[\'"]([a-zA-Z0-9._-]+)[\'"]\s*,\s*\[([^\]]*)\]/',
                $source,
                $matches,
                PREG_SET_ORDER
            )) {
                continue;
            }

            $relative = str_replace(base_path().'/', '', $file);

            foreach ($matches as [, $name, $arguments]) {
                if (! isset($declared[$name])) {
                    $problems[] = "{$relative}: route [{$name}] does not exist";
                    continue;
                }

                // Positional arrays are fine -- only named keys can disagree.
                if (! preg_match_all('/[\'"]([a-zA-Z_][a-zA-Z0-9_]*)[\'"]\s*=>/', $arguments, $keys)) {
                    continue;
                }

                // Extra keys are not an error: Laravel appends them as a query
                // string, which is how its own password-reset link carries the
                // email address. The defect this guard exists for is the
                // opposite -- a required parameter misspelled, so it lands in
                // the query string and the real one is missing, which throws
                // UrlGenerationException at render time.
                $missing = array_diff($declared[$name], $keys[1]);

                if ($missing) {
                    $problems[] = sprintf(
                        '%s: route [%s] requires (%s) but was given (%s)',
                        $relative,
                        $name,
                        implode(', ', $declared[$name]) ?: 'no parameters',
                        implode(', ', $keys[1])
                    );
                }
            }
        }

        $this->assertSame(
            [],
            $problems,
            "Route parameter names disagree with their declarations:\n  ".implode("\n  ", $problems)
        );
    }

    /**
     * The certificate email is the one that would have failed silently at send
     * time rather than on a page someone was looking at.
     */
    public function testTheCertificateEmailTemplateRenders()
    {
        $class = $this->makeClass();
        $certificate = Certificate::create([
            'school_class_id' => $class->id,
            'url' => 'certificats/x/certificat.pdf',
            'uid' => Uuid::uuid4()->toString(),
        ]);

        $html = view('emails.teacher-certificate', [
            'teacher' => $class->teacher,
            'class' => $class,
            'certificate' => $certificate,
        ])->render();

        $this->assertStringContainsString($certificate->uid, $html);
    }

    /**
     * A positional route() argument can be null, which static analysis cannot
     * see. admin/certificates.blade.php passed $class->certificate straight into
     * route() for every row while already computing $cert to grey the button
     * out -- so a single class without a certificate took the whole page down.
     * With five classes and four certificates locally, it did.
     */
    public function testTheCertificatesPageRendersWhenAClassHasNoCertificate()
    {
        $withCertificate = $this->makeClass();
        Certificate::create([
            'school_class_id' => $withCertificate->id,
            'url' => 'certificats/x/certificat.pdf',
            'uid' => Uuid::uuid4()->toString(),
        ]);

        $this->makeClass();   // deliberately without one

        $admin = \App\User::factory()->create(['type' => \App\User::TYPE_ADMIN]);

        $this->actingAs($admin)->get('/admin/certificates')->assertStatus(200);
    }

    public function testThePublicCertificatePagesRespond()
    {
        $class = $this->makeClass();
        $certificate = Certificate::create([
            'school_class_id' => $class->id,
            'url' => 'certificats/x/certificat.pdf',
            'uid' => Uuid::uuid4()->toString(),
        ]);

        $this->get("/certificat/{$certificate->uid}")->assertStatus(200);
    }
}
