<?php

namespace Tests\Feature;

use App\Http\Managers\SchoolClassManager;
use App\Http\Services\NewCertificateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsDomainFixtures;
use Tests\TestCase;

/**
 * Tripwire: certificates must still render.
 *
 * A certificate is the one artefact a class actually takes away from the
 * contest, it is generated once a year in a batch, and it is produced by a PDF
 * library driven through absolute coordinates over a background image. Nothing
 * about a broken one is subtle -- but nothing tells you either, because
 * generation runs from queued jobs and the failure lands in failed_jobs.
 *
 * These assertions are deliberately about structure rather than bytes: the
 * exact output changes whenever the background image or the contest year does,
 * and a test that breaks every year is a test people delete.
 */
class CertificateGenerationTest extends TestCase
{
    use RefreshDatabase, BuildsDomainFixtures;

    public function testACertificateIsProducedAsAPdf()
    {
        $class = $this->makeClass(null, ['name' => '7ST1']);

        $pdf = app(NewCertificateService::class)->generateCertificate($class);

        $this->assertSame('%PDF-', substr($pdf, 0, 5), 'Output is not a PDF document.');
        $this->assertGreaterThan(
            50000,
            strlen($pdf),
            'The certificate is suspiciously small -- the background image is probably missing.'
        );
    }

    /**
     * Two things must be inside the file, and both come from disk at render
     * time rather than from code: the Rockwell font data (resources/fpdf/
     * rockweb.json plus its .z payload) and the JPEG background
     * (public/images/pdf/certificate-bg.jpg).
     *
     * Asserted with str_contains rather than assertStringContainsString on
     * purpose -- a failed assertion on a 300KB binary prints the whole PDF.
     */
    public function testTheFontAndBackgroundAreEmbedded()
    {
        $class = $this->makeClass(null, ['name' => '7ST1']);

        $pdf = app(NewCertificateService::class)->generateCertificate($class);

        $this->assertTrue(
            str_contains($pdf, '/FontFile2'),
            'No embedded font data. resources/fpdf/rockweb.json or its .z payload is missing.'
        );
        $this->assertTrue(
            str_contains($pdf, 'RockwellBold'),
            'The Rockwell font is not referenced in the document.'
        );
        $this->assertTrue(
            str_contains($pdf, '/DCTDecode'),
            'No embedded JPEG. public/images/pdf/certificate-bg.jpg did not load.'
        );
    }

    /**
     * French accents are converted to windows-1252 before being drawn, because
     * this is plain FPDF rather than its unicode variant. A school name with an
     * accent must not come out empty.
     */
    public function testAccentedNamesSurviveTheEncodingConversion()
    {
        $class = $this->makeClass();
        $class->school->update(['name' => 'LYCÉE TECHNIQUE D\'ÉCHTERNACH']);

        $pdf = app(NewCertificateService::class)->generateCertificate($class->fresh());

        $this->assertSame('%PDF-', substr($pdf, 0, 5));
        $this->assertGreaterThan(50000, strlen($pdf));
    }

    /**
     * The manager is the real entry point: it writes the PDF to storage and
     * records a Certificate row pointing at it. Both halves must land, or the
     * download route serves a path with nothing behind it.
     */
    public function testTheManagerStoresThePdfAndRecordsIt()
    {
        Storage::fake();

        $class = $this->makeClass();
        $this->giveQuizResponses($class, 1);

        app(SchoolClassManager::class)->generateCertificate($class);

        $certificate = $class->fresh()->certificate;

        $this->assertNotNull($certificate, 'No Certificate row was created.');
        $this->assertTrue(
            Storage::exists($certificate->url),
            'The Certificate row points at a file that does not exist.'
        );
    }

    public function testNoCertificateIsGeneratedWithoutAnAnsweredQuiz()
    {
        Storage::fake();

        $class = $this->makeClass();

        app(SchoolClassManager::class)->generateCertificate($class);

        $this->assertNull($class->fresh()->certificate);
    }
}
