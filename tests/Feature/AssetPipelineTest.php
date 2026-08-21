<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Guard: the built assets must stay versioned and load as a classic script.
 *
 * Two properties, both easy to lose and both silent when lost.
 *
 * VERSIONING. The compiled bundle is not committed -- public/.gitignore
 * excludes js/app.js, css/* and mix-manifest.json -- so every deploy rebuilds
 * it. mix() appends the content hash from mix-manifest.json; asset() does not.
 * A layout using asset() serves a URL that never changes, so a returning
 * visitor keeps the previous CSS and JavaScript after a deploy. layouts/frontend
 * did exactly that, which meant the public pages -- login, follow-up, party
 * RSVP, certificate download -- had no cache busting at all.
 *
 * CLASSIC SCRIPT. All four layouts load the bundle immediately before
 * @stack('js'), and the fifteen pushed blocks call $ and tinymce at parse time.
 * A module script is deferred by specification and would run after them, so
 * moving to any bundler that emits type="module" breaks the whole admin
 * section. That is why this project stays on Mix rather than Vite.
 */
class AssetPipelineTest extends TestCase
{
    private const LAYOUTS = [
        'app.blade.php',
        'app-sidebar.blade.php',
        'app-single-page.blade.php',
        'frontend.blade.php',
    ];

    public function testEveryLayoutReferencesTheBundleThroughMix()
    {
        foreach (self::LAYOUTS as $layout) {
            $source = file_get_contents(resource_path("views/layouts/{$layout}"));

            foreach (['js/app.js', 'css/app.css'] as $file) {
                $this->assertStringContainsString(
                    "mix('{$file}')",
                    $source,
                    "{$layout} does not resolve {$file} through mix(). Without the content "
                    ."hash, visitors keep the previous build from cache after a deploy."
                );
                $this->assertStringNotContainsString(
                    "asset('{$file}')",
                    $source,
                    "{$layout} still uses asset() for {$file}, which produces an unversioned URL."
                );
            }
        }
    }

    public function testTheBundleIsServedAsAClassicScript()
    {
        $html = $this->get('/login')->getContent();

        $this->assertStringNotContainsString(
            'type="module"',
            $html,
            'The bundle is loaded as an ES module. Module scripts are deferred by '
            .'specification and run AFTER the inline @stack(\'js\') blocks, which call '
            .'$ and tinymce -- breaking every DataTables grid and both editors.'
        );
    }

    public function testThePublicPagesServeVersionedAssetUrls()
    {
        $html = $this->get('/login')->getContent();

        $this->assertMatchesRegularExpression(
            '#/js/app\.js\?id=[a-f0-9]+#',
            $html,
            'The login page serves an unversioned bundle URL.'
        );
    }
}
