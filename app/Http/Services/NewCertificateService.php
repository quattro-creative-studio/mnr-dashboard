<?php

namespace App\Http\Services;

use App\SchoolClass;
use tFPDF;

class NewCertificateService {

    private $fontSizeLarge = 25;
    private $lineHeight = 11.5;

    public function __construct() {
        if(!defined('FPDF_FONTPATH'))
            define('FPDF_FONTPATH', resource_path('fpdf/'));
    }

    /**
     * Initializes PDF Object
     * @return tFPDF
     */
    private function createPdf(): tFpdf {
        $pdf = new tFpdf('L');
        $pdf->AddPage();
        $pdf->AddFont('RockwellBold','B', 'rockweb.php');
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

        $pdf->Image(public_path('images/pdf/certificate-bg.jpg'), 0, 0, $pdf->GetPageWidth(), $pdf->GetPageHeight());

        $pdf->SetTextColor(38, 36, 37);
        $pdf->SetFont('RockwellBold', 'B', 12);
        $text = '2024';
        $pdf->SetXY(60, 29.5);
        $pdf->Cell(50, 10, $text, 0, 0, 'R');

        $pdf->SetTextColor(38, 36, 37);
        $pdf->SetFont('RockwellBold', 'B', 12);
        $text = '2025';
        $pdf->SetXY(188, 29.5);
        $pdf->Cell(50, 10, $text, 0, 0, 'L');

        $this->line($pdf, $class->name, 1.3, 'B');
        $this->line($pdf, $class->school->name, 2.3, 'B');

        return $pdf->Output('S', 'certificat.pdf');
    }

    /**
     * Adds a line to pdf using specified style and position
     * @param tFpdf $pdf pdf to render onto
     * @param string $text text to display
     * @param int $pos line number, can be float
     * @param string $style empty: default, 'B': Bold and large
     */
    private function line(tFpdf $pdf, string $text, $pos = 1, string $style = '') {

        $pdf->SetFont('RockwellBold', 'B', $this->fontSizeLarge);


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
