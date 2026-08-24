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

    /**
     * Guard: TinyMCE must stay out of the bundle.
     *
     * It is copied verbatim to public/vendor/tinymce and loaded with its own
     * script tag by the two admin views that need it. Re-adding an import here
     * would undo two separate things at once, neither of them loudly.
     *
     * LICENCE. Bundling concatenates TinyMCE with this application's own code
     * into a single delivered file, which is what makes a "combined work"
     * argument available to a copyleft licence. Kept as a separate, unmodified
     * file it is mere aggregation, and the version in use can be chosen on its
     * merits rather than on its licence.
     *
     * WEIGHT. app.js is loaded by all four layouts -- every teacher page, every
     * public token page -- while the editor appears on exactly two admin pages.
     * Bundled, it added roughly 350 KB gzipped to every single page view.
     */
    public function testTheEditorIsNotBundledIntoTheApplicationJavascript()
    {
        $source = file_get_contents(resource_path('js/app.js'));

        $this->assertDoesNotMatchRegularExpression(
            "/^\\s*(import|require)\\s*\\(?\\s*['\"]?[^'\"\\n]*tinymce/mi",
            $source,
            'resources/js/app.js imports TinyMCE again, which puts it back into '
            .'the bundle served on every page and re-opens the combined-work '
            .'question the copy was made to avoid.'
        );

        $styles = file_get_contents(resource_path('sass/app.scss'));

        $this->assertStringNotContainsString(
            'tinymce',
            $styles,
            'app.scss pulls a TinyMCE stylesheet back into the site-wide CSS.'
        );
    }

    public function testTheAdminEditorViewsLoadTheEditorThemselves()
    {
        foreach (['emails-edit.blade.php', 'quiz-create.blade.php'] as $view) {
            $source = file_get_contents(resource_path("views/admin/{$view}"));

            $this->assertStringContainsString(
                "asset('vendor/tinymce/tinymce.min.js')",
                $source,
                "admin/{$view} calls tinymce.init() but never loads TinyMCE, so "
                .'the editor is missing now that it is no longer in the bundle.'
            );
        }
    }
}
