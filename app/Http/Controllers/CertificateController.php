<?php
namespace App\Http\Controllers;

use App\Certificate;
use App\Http\Repositories\CertificateRepository;
use Illuminate\Support\Facades\Storage;

class CertificateController extends Controller {

    /**
     * @var CertificateRepository
     */
    private $certificateRepository;

    public function __construct(CertificateRepository $certificateRepository) {
        $this->certificateRepository = $certificateRepository;
    }

    /**
     * Public page offering the certificate download.
     *
     * Reached only through an unguessable uid mailed to the teacher, so an
     * unknown one means a mistyped, expired or regenerated link -- a 404, not
     * an error. This used to dereference a null model and return 500 with a
     * stack trace on an unauthenticated route.
     */
    public function downloadPage($certificate) {
        $cert = Certificate::where('uid', $certificate)->firstOrFail();

        return view('external.certificate-download', ['certificate' => $cert]);
    }

    /**
     * Stream the PDF itself.
     *
     * Two ways to have nothing to serve, and both used to produce a 500: an
     * unknown uid, and a row whose file is gone -- certificates are regenerated
     * between contest years and the old directory is deleted with them.
     * Storage::download() raises UnableToRetrieveMetadata in that second case
     * because it reads the size for Content-Length.
     */
    public function downloadCertificate($certificate) {
        $cert = Certificate::where('uid', $certificate)->firstOrFail();

        abort_unless(Storage::exists($cert->url), 404);

        return Storage::download($cert->url);
    }

}
