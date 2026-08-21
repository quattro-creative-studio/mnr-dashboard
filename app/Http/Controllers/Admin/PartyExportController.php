<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\PartyGroup;
use App\SchoolClass;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Exception;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;

class PartyExportController extends Controller {

    public function export() {
        $this->deleteOldFiles();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $this->addHeaders($sheet);
        $this->addData($sheet);
        $this->addFooter($sheet);

        $writer = new XlsxWriter($spreadsheet);
        \Storage::makeDirectory('export/party');
        $relPath = "export/party/fete-de-cloture-" . date('YmdHis') . ".xlsx";
        // Storage::path() rather than a hardcoded "../storage/app/..." relative
        // path: that only resolves correctly when the working directory is
        // public/, which is true under php-fpm and false from the console, a
        // queue worker, or a test -- where it would write outside the project.
        $writer->save(\Storage::path($relPath));
        return \Storage::download($relPath);
    }

    /**
     * @param Worksheet $sheet
     * @throws Exception
     */
    private function addHeaders(Worksheet $sheet) {
        $headers = [
            'Nom du groupe',
            'Nombre d\'étudiants',
            'Lycée',
            'Classe',
            'Langue',
            'Salutation',
            'Nom Prof',
            'Prénom prof',
            'Téléphone',
            'Points de la classe',
            'Points groupe rallye',
        ];
        $sheet->getRowDimension(1)->setRowHeight(50);
        for ($i = 0; $i < count($headers); $i++) {
            $letter = chr(65 + $i);
            $cell = $letter . '1';
            $sheet->setCellValue($cell, $headers[$i]);
            $sheet->getColumnDimension($letter)->setAutoSize(true);
        }
        $sheet->getStyle('A1:K1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A1:K1')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle('A1:K1')->getFont()->setBold(true);
        $sheet->getStyle('A1:K1')->getFill()->setFillType(Fill::FILL_SOLID)->setStartColor(new Color('FFFCD5B4'));
        $sheet->getStyle('A1:K1')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)
            ->setColor(new Color(Color::COLOR_BLACK));
    }

    private function addData(Worksheet $sheet) {
        PartyGroup::all()->each(function (PartyGroup $group, $row) use ($sheet) {
            $row = $row + 2;
            $sheet->setCellValue("A$row", $group->name);
            $sheet->setCellValue("B$row", $group->students);
            $sheet->setCellValue("C$row", $group->schoolClass->school->name);
            $sheet->setCellValue("D$row", $group->schoolClass->name);
            $sheet->setCellValue("E$row", $group->language);
            $sheet->setCellValue("F$row", $group->schoolClass->teacher->salutation->long_form);
            $sheet->setCellValue("G$row", $group->schoolClass->teacher->last_name);
            $sheet->setCellValue("H$row", $group->schoolClass->teacher->first_name);
            $sheet->setCellValue("I$row", " " . $group->schoolClass->teacher->phone);
            $sheet->setCellValue("J$row", " " . $group->schoolClass->getQuizScore());
            $sheet->setCellValue("K$row", " ");
            $sheet->getStyle("A$row:K$row")->getBorders()
                ->getAllBorders()->setBorderStyle(Border::BORDER_THIN)
                ->setColor(new Color(Color::COLOR_BLACK));
        });
    }

    /**
     * @param Worksheet $sheet
     * @throws Exception
     */
    private function addFooter(Worksheet $sheet) {
        $classes = PartyGroup::query()->count();
        $students = PartyGroup::query()->sum('students');

        $row = $classes + 1 + 3; // header + space after
        $sheet->setCellValue("A$row", $classes);
        $sheet->setCellValue("B$row", $students);

        $sheet->getStyle("A$row:K$row")->getBorders()
            ->getAllBorders()->setBorderStyle(Border::BORDER_THIN)
            ->setColor(new Color(Color::COLOR_BLACK));
        $sheet->getStyle("A$row:K$row")->getFill()->setFillType(Fill::FILL_SOLID)->setStartColor(new Color('FFFCD5B4'));
        $sheet->getStyle("A$row:K$row")->getFont()->setBold(true);
        $sheet->getStyle("A$row:K$row")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);


        $sheet->setAutoFilter('A1:K' . ($classes + 1));
    }

    private function deleteOldFiles() {
        Storage::delete(Storage::files('export/party'));
    }
}
