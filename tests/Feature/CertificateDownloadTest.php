<?php

namespace Tests\Feature;

use App\Certificate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Ramsey\Uuid\Uuid;
use Tests\Concerns\BuildsDomainFixtures;
use Tests\TestCase;

/**
 * The public certificate download path. Originally written to characterise two
 * defects; FIXED once the upgrade was complete, so it now pins the correct
 * behaviour instead.
 *
 * Both routes are unauthenticated and reached only through an unguessable uid
 * mailed to a teacher. There are two ordinary ways to have nothing to serve,
 * and both used to produce a 500 with a stack trace:
 *
 *   1. An unknown uid -- a mistyped, expired or regenerated link.
 *   2. A known row whose PDF is gone. Certificates are regenerated between
 *      contest years and the old directory is deleted with them, so a stale
 *      link points at a row that still exists and a file that does not.
 *
 * Both are now 404. Nothing about either is exceptional enough to page anyone.
 */
class CertificateDownloadTest extends TestCase
{
    use RefreshDatabase, BuildsDomainFixtures;

    public function testAnUnknownCertificateUidIsNotFound()
    {
        $unknown = Uuid::uuid4()->toString();

        $this->get('/certificat/download/'.$unknown)->assertStatus(404);
        $this->get('/certificat/'.$unknown)->assertStatus(404);
    }

    public function testAKnownCertificateWithAMissingFileIsNotFound()
    {
        Storage::fake();

        $class = $this->makeClass();
        $certificate = Certificate::create([
            'school_class_id' => $class->id,
            'url' => 'certificats/missing/certificat.pdf',
            'uid' => Uuid::uuid4()->toString(),
        ]);

        $this->get('/certificat/download/'.$certificate->uid)
            ->assertStatus(404);
    }

    public function testAValidCertificateDownloads()
    {
        Storage::fake();

        $class = $this->makeClass();
        $certificate = Certificate::create([
            'school_class_id' => $class->id,
            'url' => 'certificats/ok/certificat.pdf',
            'uid' => Uuid::uuid4()->toString(),
        ]);
        Storage::put($certificate->url, '%PDF-1.4 fake');

        $response = $this->get('/certificat/download/'.$certificate->uid);

        $this->assertSame(200, $response->getStatusCode());
    }
}
