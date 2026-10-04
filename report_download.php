<?php
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/src/guard.php';

use App\ReportStore;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Guard against any stray warning/notice output (from this app or a library)
// landing in the response before the binary file — that would corrupt the
// download and show as garbled text in the browser instead of a file prompt.
while (ob_get_level() > 0) {
    ob_end_clean();
}
ob_start();

$reportId = (int) ($_GET['id'] ?? $_SESSION['report_id'] ?? 0);
$report   = $reportId > 0 ? (new ReportStore())->find($reportId) : null;
if (!$report) {
    redirect('index.php');
}

$mailLog = $report['mail_log'] ?? [];

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Attendance Report');

$sheet->setCellValue('A1', $config['company']);
$sheet->mergeCells('A1:I1');
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(15);

$sheet->setCellValue('A2', 'Weekly Attendance Report — ' . $report['week_label']);
$sheet->mergeCells('A2:I2');
$sheet->getStyle('A2')->getFont()->setSize(11)->setItalic(true);

$graceMin = (int) ($report['grace_minutes'] ?? 0);
$sheet->setCellValue('A3', 'Requirement: ' . ((int) $report['daily_minutes'] > 0
        ? fmt_minutes((int) $report['daily_minutes']) . ' per day x ' . (int) $report['period_days'] . ' working days = '
        : '') . fmt_minutes($report['required_minutes']) . ' in total'
    . ($graceMin > 0 ? '   |   Grace time: ' . $graceMin . ' min per day' : '') . '   |   Generated: ' . date('d M Y, H:i'));
$sheet->mergeCells('A3:I3');
$sheet->getStyle('A3')->getFont()->setSize(9.5)->getColor()->setRGB('667266');

$headers = ['Employee ID', 'Name', 'Department', 'Days Worked', 'Total Hours', 'Required Hours', 'Shortfall', 'Status', 'Email Status'];
$sheet->fromArray($headers, null, 'A5');
$sheet->getStyle('A5:I5')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
$sheet->getStyle('A5:I5')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('146336');
$sheet->getStyle('A5:I5')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

$row = 6;
foreach ($report['employees'] as $id => $emp) {
    $mail = $mailLog[$id] ?? null;
    $emailStatus = match ($mail['status'] ?? null) {
        'sent'    => 'Sent',
        'failed'  => 'Failed',
        'skipped' => 'No email on file',
        default   => '—',
    };

    $sheet->fromArray([
        $emp['emp_id'],
        $emp['name'],
        $emp['dept'] ?? '',
        $emp['days'],
        fmt_minutes($emp['minutes']),
        fmt_minutes($emp['required']),
        $emp['shortfall'] > 0 ? fmt_minutes($emp['shortfall']) : '-',
        ['low' => 'Below requirement', 'grace' => 'Met with grace time', 'ok' => 'Requirement met'][emp_status($emp)],
        $emailStatus,
    ], null, "A{$row}");

    $fill = ['low' => 'FDECEC', 'grace' => 'FFF0E0', 'ok' => null][emp_status($emp)];
    if ($fill) {
        $sheet->getStyle("A{$row}:I{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($fill);
    }
    $row++;
}

$sheet->getStyle("A5:I" . ($row - 1))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('DFE7E1');

foreach (range('A', 'I') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// ---- Second sheet: hours worked on each day, one row per employee (numbers, so Excel can sum them) ----
$dayCols = day_columns($report['employees']);
$daySheet = $spreadsheet->createSheet();
$daySheet->setTitle('Hours Per Day');
$daySheet->setCellValue('A1', 'Hours worked per day (decimal hours) — ' . $report['week_label']);
$daySheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);

$dHead = array_merge(['Employee ID', 'Name'], array_values($dayCols), ['Total (hours)']);
$daySheet->fromArray($dHead, null, 'A3');
$lastCol = Coordinate::stringFromColumnIndex(count($dHead));
$daySheet->getStyle("A3:{$lastCol}3")->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
$daySheet->getStyle("A3:{$lastCol}3")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('146336');

$dRow = 4;
foreach ($report['employees'] as $emp) {
    $byDay = [];
    foreach ($emp['daily'] as $d) {
        $byDay[day_key($d)] = $d['minutes'];
    }
    $line = [$emp['emp_id'], $emp['name']];
    foreach ($dayCols as $key => $label) {
        $line[] = isset($byDay[$key]) ? round($byDay[$key] / 60, 2) : '';
    }
    $line[] = round($emp['minutes'] / 60, 2);
    $daySheet->fromArray($line, null, "A{$dRow}");
    $dFill = ['low' => 'FDECEC', 'grace' => 'FFF0E0', 'ok' => null][emp_status($emp)];
    if ($dFill) {
        $daySheet->getStyle("A{$dRow}:{$lastCol}{$dRow}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($dFill);
    }
    $dRow++;
}
$daySheet->getStyle("A3:{$lastCol}" . ($dRow - 1))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('DFE7E1');
for ($i = 1; $i <= count($dHead); $i++) {
    $daySheet->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setAutoSize(true);
}
$spreadsheet->setActiveSheetIndex(0);

$safeLabel = preg_replace('/[^A-Za-z0-9]+/', '_', $report['week_label']);
$filename  = "Attendance_Report_{$safeLabel}.xlsx";

if (ob_get_length()) {
    ob_end_clean();
}

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');
header('Pragma: public');

(new Xlsx($spreadsheet))->save('php://output');
exit;
