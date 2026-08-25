<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Quiz;
use App\QuizAssignment;
use App\SchoolClass;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Exception;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;

class ClassExportController extends Controller {

    public function export() {
        $this->deleteOldFiles();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $this->addHeaders($sheet);
        $this->addData($sheet);
        $this->addFooter($sheet);

        $writer = new XlsxWriter($spreadsheet);
        \Storage::makeDirectory('export/classes');
        $relPath = "export/classes/classes-" . date('YmdHis') . ".xlsx";
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
            'NOMBRE INSCRIPTION',
            'LYCEE',
            'ADRESSE',
            'CODE POSTAL',
            'VILLE',
            'SALUTATION',
            'NOMPROF',
            'PRENOMPROF',
            'EMAIL',
            'NUMÉRO TÉLÉPHONE',
            'CLASSE',
            'NOMBRE D\'ELEVES',
//            'JANVIER',
//            'MARS',
            'MAI',
            'FETE',
            'FETE COMPLETEE',
        ];
        foreach (Quiz::orderBy('id')->get()->pluck('name')->values()->toArray() as $quizName) {
            $headers[] = $quizName;
        }
        $headers[] = "QUIZ TOTAL";
        $sheet->getRowDimension(1)->setRowHeight(50);
        for ($i = 1; $i <= count($headers); $i++) {
            $sheet->setCellValue([$i, 1], $headers[$i - 1]);
            $sheet->getColumnDimensionByColumn($i)->setAutoSize(true);
            $sheet->getStyle([$i, 1])->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle([$i, 1])->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
            $sheet->getStyle([$i, 1])->getFont()->setBold(true);
            $sheet->getStyle([$i, 1])->getFill()->setFillType(Fill::FILL_SOLID)->setStartColor(new Color('FFFCD5B4'));
            $sheet->getStyle([$i, 1])->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)
                ->setColor(new Color(Color::COLOR_BLACK));
        }

        $sheet->setAutoFilter(
            Coordinate::stringFromColumnIndex(1).'1:'
            .Coordinate::stringFromColumnIndex(sizeof($headers)).(SchoolClass::count() + 1)
        );
    }

    private function addData(Worksheet $sheet) {
        SchoolClass::all()->each(function (SchoolClass $class, $row) use ($sheet) {
            $row = $row + 2;
            $data = [
                $class->id,
                $class->school->name,
                $class->school->address,
                $class->school->postal_code,
                $class->school->city,
                $class->teacher->salutation->long_form,
                $class->teacher->last_name,
                $class->teacher->first_name,
                $class->teacher->user->email,
                " " . $class->teacher->phone,
                $class->name,
                $class->students,
//                $this->statusToString($class->getStatusJanuary()),
//                $this->statusToString($class->getStatusMarch()),
                $this->statusToString($class->getStatusMay()),
                $this->statusToString($class->getStatusParty()),
                $this->statusToString($class->getStatusPartyGroups()),
            ];

            foreach (Quiz::orderBy('id')->pluck('id') as $i => $quiz) {
                $assignment = QuizAssignment::where('quiz_id', $quiz)
                    ->where('school_class_id', $class->id)
                    ->first();
                $data[] = optional(optional($assignment)->response)->score;
            }
            $data[] = $class->getQuizScoreAll();

            for($i = 0; $i < sizeof($data); $i++) {
                $col = $i + 1;
                $sheet->setCellValue([$col, $row], $data[$i]);
            }

            $sheet->getStyle(
                Coordinate::stringFromColumnIndex(1).$row.':'
                .Coordinate::stringFromColumnIndex($col).$row
            )
                ->getBorders()
                ->getAllBorders()
                ->setBorderStyle(Border::BORDER_THIN)
                ->setColor(new Color(Color::COLOR_BLACK));

            $sheet->getStyle("A$row")->getFill()->setFillType(Fill::FILL_SOLID)->setStartColor(new Color('FFFCD5B4'));
            $sheet->getCell("J$row")->setDataType(DataType::TYPE_STRING);
            $sheet->getCell("K$row")->setDataType(DataType::TYPE_STRING);

        });
    }

    /**
     * @param Worksheet $sheet
     * @throws Exception
     */
    private function addFooter(Worksheet $sheet) {
        $classes = SchoolClass::query()->count();
        $students = SchoolClass::query()->sum('students');
//        $status_january = SchoolClass::query()->count('status_january');
//        $status_march = SchoolClass::query()->count('status_march');
        $status_may = SchoolClass::query()->count('status_may');
        $status_party = SchoolClass::all()->pluck('status_party')->sum();

        $row = $classes + 1 + 3; // header + space after
        $sheet->setCellValue("K$row", $classes);
        $sheet->setCellValue("L$row", $students);
//        $sheet->setCellValue("M$row", $status_january);
//        $sheet->setCellValue("N$row", $status_march);
        $sheet->setCellValue("M$row", $status_may);
        $sheet->setCellValue("N$row", $status_party);

        $sheet->getStyle("A$row:Q$row")->getBorders()
            ->getAllBorders()->setBorderStyle(Border::BORDER_THIN)
            ->setColor(new Color(Color::COLOR_BLACK));
        $sheet->getStyle("A$row:Q$row")->getFill()->setFillType(Fill::FILL_SOLID)->setStartColor(new Color('FFFCD5B4'));
        $sheet->getStyle("A$row:Q$row")->getFont()->setBold(true);
        $sheet->getStyle("A$row:Q$row")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    }

    private function statusToString($status) {
        if ($status === null)
            return "pas répondu";
        if ($status === 1)
            return "oui";
        if ($status === 0)
            return "non";
        return "";
    }

    private function deleteOldFiles() {
        Storage::delete(Storage::files('export/classes'));
    }
}
