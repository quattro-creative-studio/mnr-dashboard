<?php

namespace Tests\Feature;

use App\Certificate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Ramsey\Uuid\Uuid;
use Tests\Concerns\BuildsDomainFixtures;
use Tests\TestCase;

/**
 * Characterisation: the public certificate download path.
 *
 * This is deliberately a record of current behaviour, NOT an endorsement of
 * it. Nothing here is fixed; the tests exist because this route sits directly
 * in the blast radius of the Flysystem 1 -> 3 change at Laravel 9.
 *
 * CertificateController::downloadCertificate() does:
 *
 *     $cert = Certificate::where('uid', $certificate)->first();
 *     return \Storage::download($cert->url);
 *
 * There is no guard on the model and no guard on the file. Two consequences,
 * both pinned below:
 *
 *   1. An unknown uid dereferences null and returns 500 instead of 404, on an
 *      unauthenticated public route.
 *   2. A known certificate whose PDF is missing fails inside Storage. Today
 *      that raises before a response is produced. Under Flysystem 3 reads stop
 *      raising and start returning falsy, so this is expected to change shape
 *      -- the response gets built and then dies mid-stream, which is much
 *      harder to diagnose from a log.
 *
 * If either assertion below flips during the upgrade, that is the signal.
 */
class CertificateDownloadTest extends TestCase
{
    use RefreshDatabase, BuildsDomainFixtures;

    public function testAnUnknownCertificateUidIsNotHandled()
    {
        $response = $this->get('/certificat/download/'.Uuid::uuid4()->toString());

        $this->assertSame(
            500,
            $response->getStatusCode(),
            'Current behaviour: the controller dereferences a null model. '
            .'A 404 would be correct, and this is the assertion to change when that is fixed.'
        );
    }

    public function testAKnownCertificateWithAMissingFileFailsBeforeRespondingToday()
    {
        Storage::fake();

        $class = $this->makeClass();
        $certificate = Certificate::create([
            'school_class_id' => $class->id,
            'url' => 'certificats/missing/certificat.pdf',
            'uid' => Uuid::uuid4()->toString(),
        ]);

        $response = $this->get('/certificat/download/'.$certificate->uid);

        $this->assertSame(
            500,
            $response->getStatusCode(),
            'Flysystem 1 raises on a missing read, so the failure happens before a '
            .'response is streamed. Under Flysystem 3 this is expected to become a '
            .'200 that dies mid-stream instead.'
        );
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
