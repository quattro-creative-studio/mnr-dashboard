<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Guard: the security-advisory block must be restored before this ships.
 *
 * Climbing from Laravel 5.7 to 13 means installing several end-of-life
 * versions in sequence. Every one of them, and most of its transitive tree,
 * carries published advisories, and Composer 2.10 refuses to install packages
 * under advisory by default -- so the climb is impossible with the block on.
 *
 * Turning it off is therefore a precondition of the migration, not a judgement
 * that the vulnerabilities are acceptable. What makes it safe is that
 * production stays on 5.7 for the whole climb and no intermediate version is
 * ever deployed.
 *
 * The danger is not the switch. It is forgetting the switch. This test exists
 * so that the destination cannot be reached with it still flipped.
 */
class AdvisoryPolicyTest extends TestCase
{
    private function composerJson(): array
    {
        return json_decode(file_get_contents(base_path('composer.json')), true);
    }

    private function advisoryBlockIsDisabled(): bool
    {
        $composer = $this->composerJson();

        return ($composer['config']['policy']['advisories']['block'] ?? true) === false;
    }

    public function testTheBlockIsRestoredOnceTheLadderReachesItsTarget()
    {
        $major = (int) explode('.', app()->version())[0];

        if ($major < 13) {
            $this->assertTrue(true, "Laravel {$major}: still climbing, the block may stay off.");

            return;
        }

        $this->assertFalse(
            $this->advisoryBlockIsDisabled(),
            'The ladder has reached Laravel 13 but composer.json still disables the '
            .'security-advisory block. Restore config.policy.advisories.block to true '
            .'and make `composer audit` clean before deploying.'
        );
    }

    public function testDisablingTheBlockIsDocumentedRatherThanSilent()
    {
        if (! $this->advisoryBlockIsDisabled()) {
            $this->assertTrue(true, 'Block is on; nothing to document.');

            return;
        }

        $notes = $this->composerJson()['extra']['upgrade-notes'] ?? [];

        $this->assertArrayHasKey(
            'advisories-block',
            $notes,
            'The advisory block is disabled with no recorded reason. Anyone reading '
            .'composer.json must be able to see why, and when it comes back.'
        );
        $this->assertArrayHasKey('advisories-block-removal', $notes);
    }

    /**
     * The resolution target must track the PHP the application actually runs,
     * otherwise Composer happily resolves a tree the runtime cannot execute.
     */
    public function testTheResolutionPlatformMatchesTheRunningPhp()
    {
        $pinned = $this->composerJson()['config']['platform']['php'] ?? null;

        $this->assertNotNull($pinned, 'config.platform.php should pin the PHP target for each hop.');

        $this->assertSame(
            implode('.', array_slice(explode('.', $pinned), 0, 2)),
            implode('.', [PHP_MAJOR_VERSION, PHP_MINOR_VERSION]),
            'composer.json resolves for PHP '.$pinned.' but the suite is running on '
            .PHP_VERSION.'. Bump config.platform.php at the same time as the runtime.'
        );
    }
}
