<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Storage;
use League\Flysystem\UnableToRetrieveMetadata;
use Tests\TestCase;

/**
 * Flysystem storage semantics. Written on Laravel 5.7 to predict the Flysystem
 * 1 -> 3 change; UPDATED at the Laravel 9 hop, where it fired exactly as
 * intended. Four assertions went red at the commit that caused the change,
 * instead of the change being invisible.
 *
 * What actually flipped:
 *
 *   get() on a missing file         threw   ->  returns null
 *   readStream() on a missing file  threw   ->  returns null
 *   delete() on a missing file      false   ->  true
 *   size() on a missing file        League\Flysystem\FileNotFoundException
 *                                           ->  League\Flysystem\UnableToRetrieveMetadata
 *
 * The practical impact on this application turned out to be nil, which is only
 * worth knowing because it was measured rather than assumed:
 *
 *   - Storage::get() and Storage::readStream(): zero call sites. The change
 *     from raising to returning null has nothing to apply to.
 *   - Storage::delete(): four call sites, every one of which discards the
 *     return value, so false -> true changes nothing.
 *
 * One correction to the migration plan, which predicted that Storage::download()
 * on a missing file would stop failing fast and instead build a response that
 * dies mid-stream. It does not: download() still raises, because it reads the
 * file size to set Content-Length. Verified, not assumed.
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

    public function testReadingAMissingFileReturnsNullInsteadOfThrowing()
    {
        $this->assertNull(
            $this->disk()->get('does-not-exist.txt'),
            'Flysystem 1 raised FileNotFoundException here. Nothing in this application '
            .'calls Storage::get(), so the change has no point of application -- but any '
            .'new caller must check the return value rather than rely on an exception.'
        );
    }

    public function testReadingAMissingStreamReturnsNullInsteadOfThrowing()
    {
        $this->assertNull($this->disk()->readStream('does-not-exist.txt'));
    }

    public function testMetadataOnAMissingFileRaisesUnableToRetrieveMetadata()
    {
        $this->expectException(UnableToRetrieveMetadata::class);

        $this->disk()->size('does-not-exist.txt');
    }

    public function testDeletingAMissingFileNowReportsSuccess()
    {
        $this->assertTrue(
            $this->disk()->delete('does-not-exist.txt'),
            'Flysystem 3 reports true whether or not anything was removed. All four '
            .'Storage::delete() call sites discard the return value, so nothing depends '
            .'on this -- but a future caller must not read it as "something was deleted".'
        );
    }

    /**
     * The plan expected this to become a 200 that dies mid-stream. It does not:
     * download() reads the file size to set Content-Length, so a missing file
     * still fails before any bytes are sent. Pinned so that if a future
     * Flysystem stops raising here, the certificate route is re-examined.
     */
    public function testDownloadingAMissingFileStillFailsBeforeStreaming()
    {
        $this->expectException(UnableToRetrieveMetadata::class);

        $this->disk()->download('does-not-exist.txt');
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
