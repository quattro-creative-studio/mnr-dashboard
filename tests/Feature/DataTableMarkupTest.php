<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Guard: a DataTable's tbody must not carry a hand-written colspan row.
 *
 * Seven admin views rendered their own "no rows" line as a single
 * <td colspan="N">. DataTables cannot map a spanning cell onto its columns and
 * aborts with "Incorrect column count" -- as a blocking alert() box, on the
 * first page an administrator sees after logging in.
 *
 * It only fires when a table happens to be empty, which is why it survived
 * years of use and appeared the moment a freshly migrated staging database was
 * opened. The counts were wrong anyway: admin/classes hardcoded colspan="16"
 * for a table whose width is 16 plus one column per quiz.
 *
 * DataTables renders its own empty state, translated in resources/js/app.js.
 */
class DataTableMarkupTest extends TestCase
{
    /**
     * Views that initialise a DataTable, found rather than listed, so a new one
     * is covered the day it is written.
     */
    private function viewsUsingDataTables(): array
    {
        $found = [];

        foreach (glob(resource_path('views/**/*.blade.php')) as $path) {
            $source = file_get_contents($path);

            if (preg_match('/\.(dataTable|DataTable)\(/', $source)) {
                $found[$path] = $source;
            }
        }

        return $found;
    }

    public function testNoDataTableRendersItsOwnEmptyRow()
    {
        $views = $this->viewsUsingDataTables();

        $this->assertNotEmpty($views, 'No DataTable views found; this guard would pass vacuously.');

        $offenders = [];

        foreach ($views as $path => $source) {
            if (preg_match('/<td[^>]*colspan/i', $source)) {
                $offenders[] = basename($path);
            }
        }

        $this->assertSame(
            [],
            $offenders,
            'These views hand-write a <td colspan> row inside a DataTable, which '
            ."aborts with \"Incorrect column count\" as soon as the table is empty:\n  "
            .implode("\n  ", $offenders)
            ."\nDelete the row and let DataTables render its own empty state."
        );
    }

    /**
     * The empty state is user-facing text in an application whose interface is
     * entirely French, so the translation has to survive too.
     */
    public function testTheEmptyStateIsTranslated()
    {
        $source = file_get_contents(resource_path('js/app.js'));

        $this->assertStringContainsString(
            'emptyTable',
            $source,
            'DataTables would fall back to its English empty message.'
        );
    }
}
