<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Proves the test harness is safe to run.
 *
 * This is the only test that exercises RefreshDatabase for its own sake: it
 * confirms migrations run against the isolated schema and that the guard in
 * Tests\TestCase is actually wired up. If this test ever fails, stop and fix
 * the configuration before running anything else.
 */
class DatabaseIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function testTestsRunAgainstAnIsolatedDatabase()
    {
        $connection = config('database.default');
        $database = config("database.connections.{$connection}.database");

        $this->assertNotSame(
            'missionnichtrauchendb',
            $database,
            'Tests are pointed at the development database.'
        );
        $this->assertMatchesRegularExpression('/_test$/', $database);
    }

    public function testMigrationsRanAgainstTheTestDatabase()
    {
        $this->assertTrue(Schema::hasTable('school_classes'));
        $this->assertTrue(Schema::hasTable('editable_emails'));
        $this->assertSame(0, \DB::table('school_classes')->count());
    }
}
