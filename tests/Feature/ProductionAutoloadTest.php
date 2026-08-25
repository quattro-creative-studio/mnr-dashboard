<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Guard: config/app.php must not name a class that production will not install.
 *
 * Forge deploys with `composer install --no-dev`, so nothing from require-dev
 * exists on the server. A provider hardcoded in config/app.php is loaded
 * unconditionally, and the whole deploy dies on
 * "Class ... not found" before a single page is served.
 *
 * That is exactly how barryvdh/laravel-ide-helper broke the first Forge
 * install. It had been listed by hand since the commit that added it, even
 * though the package supports Laravel's auto-discovery -- which registers it
 * where it is installed and stays quiet where it is not. The manual line was
 * redundant in development and fatal in production.
 *
 * The whole class of failure is invisible locally: everything is installed, so
 * everything resolves.
 */
class ProductionAutoloadTest extends TestCase
{
    /**
     * Namespace prefixes owned by require-dev packages, read from the installed
     * package metadata rather than guessed.
     */
    private function developmentOnlyNamespaces(): array
    {
        $composer = json_decode(file_get_contents(base_path('composer.json')), true);
        $devPackages = array_keys($composer['require-dev'] ?? []);

        $installed = json_decode(file_get_contents(base_path('vendor/composer/installed.json')), true);
        $packages = $installed['packages'] ?? $installed;

        $namespaces = [];

        foreach ($packages as $package) {
            if (! in_array($package['name'] ?? '', $devPackages, true)) {
                continue;
            }

            foreach (['psr-4', 'psr-0'] as $standard) {
                foreach (array_keys($package['autoload'][$standard] ?? []) as $prefix) {
                    if ($prefix !== '') {
                        $namespaces[$prefix] = $package['name'];
                    }
                }
            }
        }

        return $namespaces;
    }

    public function testNoHardcodedProviderComesFromADevelopmentOnlyPackage()
    {
        $devNamespaces = $this->developmentOnlyNamespaces();

        $this->assertNotEmpty(
            $devNamespaces,
            'Could not read the require-dev namespaces, so this guard would pass vacuously.'
        );

        $offenders = [];

        foreach (config('app.providers') as $provider) {
            foreach ($devNamespaces as $prefix => $package) {
                if (str_starts_with($provider, $prefix)) {
                    $offenders[] = "{$provider} (from {$package})";
                }
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "config/app.php names providers that `composer install --no-dev` will not install, "
            ."so every deploy dies during package:discover: \n  ".implode("\n  ", $offenders)
            ."\nRemove them and let auto-discovery register them where they exist."
        );
    }

    /**
     * Same failure, same cause, different file: an alias resolves lazily, so it
     * kills a page rather than the deploy -- which is worse, not better.
     */
    public function testNoHardcodedAliasComesFromADevelopmentOnlyPackage()
    {
        $devNamespaces = $this->developmentOnlyNamespaces();
        $offenders = [];

        foreach (config('app.aliases', []) as $alias => $class) {
            foreach ($devNamespaces as $prefix => $package) {
                if (str_starts_with($class, $prefix)) {
                    $offenders[] = "{$alias} => {$class} (from {$package})";
                }
            }
        }

        $this->assertSame([], $offenders, implode("\n  ", $offenders));
    }
}
