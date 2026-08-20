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
        $this->guardAgainstSendingRealMail();

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

    /**
     * Guard the mail transport, for the same reason as the database.
     *
     * phpunit.xml neutralises mail with MAIL_DRIVER=array. That key is read by
     * config/mail.php only while the flat, pre-Laravel-7 mail config survives.
     * Restructuring into the `mailers` array -- which becomes mandatory at
     * Laravel 9 with Symfony Mailer -- renames it to MAIL_MAILER, at which
     * point the override silently stops applying and the suite falls back to
     * whatever .env says. That is usually SMTP.
     *
     * The failure mode is not a red test. It is a green test run that quietly
     * delivered mail to real teachers. So assert on the resolved driver rather
     * than trusting the override, and read both spellings so the check keeps
     * working across the whole upgrade ladder.
     *
     * @return void
     */
    protected function guardAgainstSendingRealMail()
    {
        $driver = config('mail.driver') ?: config('mail.default');

        if (in_array($driver, ['array', 'log', 'null'], true)) {
            return;
        }

        throw new RuntimeException(sprintf(
            "Refusing to run tests with mail driver [%s].\n"
            ."Tests must not be able to deliver mail. If this fired after a framework\n"
            ."upgrade, config/mail.php has probably moved from MAIL_DRIVER to\n"
            ."MAIL_MAILER -- update the <php> block in phpunit.xml to match.",
            var_export($driver, true)
        ));
    }
}
