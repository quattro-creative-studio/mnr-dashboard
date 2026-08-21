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
            if ($route->getName()) {
                $declared[$route->getName()] = $route->parameterNames();
            }
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

                $unknown = array_diff($keys[1], $declared[$name]);

                if ($unknown) {
                    $problems[] = sprintf(
                        '%s: route [%s] declares (%s) but was given (%s)',
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
