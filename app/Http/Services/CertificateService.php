<?php

namespace App\Http\Services;

use App\SchoolClass;
use Fpdf\Fpdf;

class CertificateService {

    private $fontSizeSmall = 18;
    private $fontSizeLarge = 24;
    private $lineHeight = 11.5;

    public function __construct() {
        if(!defined('FPDF_FONTPATH'))
            define('FPDF_FONTPATH', resource_path('fpdf/'));
    }

    /**
     * Initializes PDF Object
     * @return Fpdf
     */
    private function createPdf(): Fpdf {
        $pdf = new Fpdf('L');
        $pdf->AddPage();
        $pdf->AddFont('Calibri','', 'Calibri.php');
        $pdf->AddFont('Calibri','B', 'CALIBRIB.php');
        return $pdf;
    }

    /**
     * Generates a certificate for a given SchoolClass
     * @param SchoolClass $class
     * @return string
     */
    public function generateCertificate(SchoolClass $class) {
        $pdf = $this->createPdf();

        $pdf->SetTitle('Certificat', true);
        $pdf->SetAuthor('Fondation Cancer', true);

        $pdf->Image(public_path('images/pdf/bg.jpg'), 0, 0, $pdf->GetPageWidth(), $pdf->GetPageHeight());

        $this->line($pdf, 'La Fondation Cancer félicite la classe');
        $this->line($pdf, $class->name, 2, 'B');
        $this->line($pdf, $class->school->name, 3, 'B');
        $this->line($pdf, 'pour avoir relevé avec succès le défi de', 4);
        $this->line($pdf, 'ne pas fumer durant cinq mois.', 4.7);

        return $pdf->Output('S', 'certificat.pdf');
    }

    /**
     * Adds a line to pdf using specified style and position
     * @param Fpdf $pdf pdf to render onto
     * @param string $text text to display
     * @param int $pos line number, can be float
     * @param string $style empty: default, 'B': Bold and large
     */
    private function line(Fpdf $pdf, string $text, $pos = 1, string $style = '') {
        if($style == '') {
            $pdf->SetFont('Calibri', '', $this->fontSizeSmall);
        } else if($style == 'B') {
            $pdf->SetFont('Calibri', 'B', $this->fontSizeLarge);
        }

        $textC = $this->conv($text);
        $textW = $pdf->GetStringWidth($textC);
        $pdf->Text($pdf->GetPageWidth() / 2 - $textW / 2, $pdf->GetPageHeight() / 2 + $this->lineHeight * $pos, $textC);
    }

    /**
     * Convert Text from UTF-8 to usable pdf text
     * @param $text
     * @return string
     */
    private function conv($text): string {
        return iconv('UTF-8', 'windows-1252', $text);
    }

}
