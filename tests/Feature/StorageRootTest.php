<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Guard: the local disk root must keep pointing at storage/app.
 *
 * Every certificate ever generated is stored at storage/app/certificats/{uuid}/
 * and addressed through a path held in certificates.url. Teacher-uploaded
 * documents live under storage/app/documents/. Neither is regenerable: the
 * certificate PDFs carry the contest year and the documents were uploaded by
 * hand.
 *
 * Laravel 12 changes the DEFAULT root of the local disk to storage/app/private.
 * This application is insulated only because config/filesystems.php states
 * 'root' => storage_path('app') explicitly. Remove or "tidy up" that line while
 * copying in a newer config file and every stored path resolves one directory
 * too deep: certificate downloads 404, and the files look lost.
 *
 * Cheap to assert, expensive to discover in June.
 */
class StorageRootTest extends TestCase
{
    public function testTheLocalDiskRootIsStorageApp()
    {
        $this->assertSame(
            storage_path('app'),
            config('filesystems.disks.local.root'),
            'The local disk root moved. Existing certificate and document paths are '
            .'relative to storage/app; anything else makes them unreachable.'
        );
    }

    public function testTheDefaultDiskIsTheLocalOne()
    {
        $this->assertSame(
            'local',
            config('filesystems.default'),
            'Certificates and documents are written to the default disk. Pointing it '
            .'elsewhere without migrating the files makes every stored path invalid.'
        );
    }

    /**
     * The paths the application actually writes to, asserted as relative --
     * which is what certificates.url and documents.filename hold.
     */
    public function testStoredPathsResolveUnderStorageApp()
    {
        $disk = Storage::disk('local');

        $this->assertSame(
            storage_path('app/certificats/example/certificat.pdf'),
            $disk->path('certificats/example/certificat.pdf')
        );
        $this->assertSame(
            storage_path('app/documents/example.pdf'),
            $disk->path('documents/example.pdf')
        );
    }
}
