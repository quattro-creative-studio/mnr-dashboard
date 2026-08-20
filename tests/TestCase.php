<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    /**
     * Guard the test database before any database trait can touch it.
     *
     * RefreshDatabase and DatabaseMigrations drop or truncate every table they
     * can reach, and they fire from setUpTraits(). Overriding it here means the
     * check runs after the application has booted -- so the value tested is the
     * fully resolved connection, not just whatever phpunit.xml declared -- but
     * still before a single table is dropped.
     *
     * A misconfigured phpunit.xml therefore aborts the run instead of wiping
     * the development database.
     *
     * @return array
     */
    protected function setUpTraits()
    {
        $this->guardAgainstNonTestDatabase();

        return parent::setUpTraits();
    }

    /**
     * @return void
     */
    protected function guardAgainstNonTestDatabase()
    {
        $connection = config('database.default');
        $database = config("database.connections.{$connection}.database");

        if ($database === ':memory:') {
            return;
        }

        if (is_string($database) && preg_match('/(_test|_testing)$/', $database)) {
            return;
        }

        throw new RuntimeException(sprintf(
            "Refusing to run tests against database [%s] on connection [%s].\n"
            ."Tests drop and recreate every table. Point DB_DATABASE at a schema whose\n"
            ."name ends in _test (see the <php> block in phpunit.xml).",
            var_export($database, true),
            $connection
        ));
    }
}
