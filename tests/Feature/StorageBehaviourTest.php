<?php

namespace Tests\Feature;

use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\FileNotFoundException as FlysystemFileNotFoundException;
use Tests\TestCase;

/**
 * Characterisation: Flysystem 1 storage semantics, recorded on Laravel 5.7.
 *
 * This is the most valuable file in the suite and the one with the shortest
 * shelf life. Laravel 9 replaces Flysystem 1 with Flysystem 3, and the change
 * is not an API rename -- it is a change from "raise on failure" to "return a
 * falsy value on failure". Every assertion below is expected to CHANGE at that
 * hop. That is the point: written now, these tests turn an invisible semantic
 * shift into a red suite at the exact commit that causes it.
 *
 * Expected flips at Laravel 9 (documented, not yet true):
 *   get() on a missing file        throws  ->  returns null
 *   delete() on a missing file     false   ->  true
 *   put() on failure               true    ->  returns false
 *
 * The application reads storage in 19 places across 9 files. The one that
 * matters most is certificate download: today a missing PDF fails loudly, and
 * after the hop it will build a response and die inside the stream instead.
 *
 * @see app/Certificate.php
 * @see app/Http/Controllers/CertificateController.php
 * @see app/Http/Managers/SchoolClassManager.php
 */
class StorageBehaviourTest extends TestCase
{
    /** @var string */
    private $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = sys_get_temp_dir().'/mnr-storage-test-'.uniqid();

        config(['filesystems.disks.probe' => [
            'driver' => 'local',
            'root' => $this->root,
        ]]);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->root)) {
            exec('rm -rf '.escapeshellarg($this->root));
        }

        parent::tearDown();
    }

    private function disk()
    {
        return Storage::disk('probe');
    }

    public function testReadingAMissingFileThrows()
    {
        $this->expectException(FileNotFoundException::class);

        $this->disk()->get('does-not-exist.txt');
    }

    public function testReadingAMissingStreamThrows()
    {
        $this->expectException(FileNotFoundException::class);

        $this->disk()->readStream('does-not-exist.txt');
    }

    public function testMetadataOnAMissingFileThrowsTheFlysystemException()
    {
        $this->expectException(FlysystemFileNotFoundException::class);

        $this->disk()->size('does-not-exist.txt');
    }

    public function testDeletingAMissingFileReportsFailure()
    {
        $this->assertFalse(
            $this->disk()->delete('does-not-exist.txt'),
            'Flysystem 1 reports false here; Flysystem 3 reports true. '
            .'Anything treating this as "did we actually remove something" flips meaning.'
        );
    }

    public function testDeletingAnExistingFileReportsSuccess()
    {
        $this->disk()->put('gone.txt', 'x');

        $this->assertTrue($this->disk()->delete('gone.txt'));
        $this->assertFalse($this->disk()->exists('gone.txt'));
    }

    public function testExistsDoesNotThrow()
    {
        $this->assertFalse($this->disk()->exists('does-not-exist.txt'));
    }

    public function testPutReturnsTrueAndRoundTrips()
    {
        $this->assertTrue($this->disk()->put('nested/dir/file.txt', 'hello'));
        $this->assertSame('hello', $this->disk()->get('nested/dir/file.txt'));
    }

    /**
     * SchoolClassManager::generateCertificate() writes with an explicit
     * visibility argument. It survives the Flysystem 3 move, but pin it --
     * a silent change here would make every certificate URL unreadable.
     *
     * @see app/Http/Managers/SchoolClassManager.php
     */
    public function testPutAcceptsAnExplicitVisibilityArgument()
    {
        $this->assertTrue($this->disk()->put('certificats/x/certificat.pdf', '%PDF-1.4', 'public'));
        $this->assertSame('public', $this->disk()->getVisibility('certificats/x/certificat.pdf'));
    }
}
