<?php
declare(strict_types=1);

namespace App;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/** Builds one employee's own report as an .xlsx file, to attach to their email. */
class EmployeeReportFile
{
    /** @return array{data: string, name: string, mime: string} */
    public static function build(array $emp, string $weekLabel, string $company): array
    {
        $status   = \emp_status($emp);
        $statusTx = ['low' => 'Below requirement', 'grace' => 'Met with grace time', 'ok' => 'Requirement met'][$status];
        $fills    = ['low' => 'FDECEC', 'grace' => 'FFF0E0', 'ok' => 'E8F7EF'];
        [$dailyReq, $graceMin] = \day_targets($emp);

        $ss = new Spreadsheet();
        $sh = $ss->getActiveSheet();
        $sh->setTitle('My Attendance Report');

        $sh->setCellValue('A1', $company);
        $sh->getStyle('A1')->getFont()->setBold(true)->setSize(15);
        $sh->setCellValue('A2', 'Attendance Report — ' . $weekLabel);
        $sh->getStyle('A2')->getFont()->setItalic(true);

        $details = [
            ['Employee ID', $emp['emp_id']],
            ['Name', $emp['name']],
            ['Department', $emp['dept'] ?: '-'],
            ['Period', $weekLabel],
            ['Total hours worked', \fmt_minutes((int) $emp['minutes'])],
            ['Required hours', \fmt_minutes((int) $emp['required']) . ((int) ($emp['period_days'] ?? 0) > 0
                ? ' (' . \fmt_minutes($dailyReq) . ' x ' . (int) $emp['period_days'] . ' working days)' : '')],
        ];
        if ($graceMin > 0) {
            $details[] = ['Grace time allowed', $graceMin . ' min per day'];
        }
        if ((int) $emp['shortfall'] > 0) {
            $details[] = ['Short by', \fmt_minutes((int) $emp['shortfall'])];
        }
        $details[] = ['Status', $statusTx];

        $r = 4;
        foreach ($details as [$k, $v]) {
            $sh->setCellValue("A{$r}", $k);
            $sh->setCellValueExplicit("B{$r}", (string) $v, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sh->getStyle("A{$r}")->getFont()->setBold(true)->getColor()->setRGB('647266');
            $r++;
        }
        $sh->getStyle('B' . ($r - 1))->getFont()->setBold(true);
        $sh->getStyle('B' . ($r - 1))->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($fills[$status]);

        $r += 1;
        $hasInOut = (bool) array_filter($emp['daily'], fn($d) => ($d['in'] ?? '') !== '' || ($d['out'] ?? '') !== '');
        $head = $hasInOut ? ['Date', 'In', 'Out', 'Hours worked', 'Note'] : ['Date', 'Hours worked', 'Note'];
        $last = $hasInOut ? 'E' : 'C';
        $hRow = $r;
        $sh->fromArray($head, null, "A{$r}");
        $sh->getStyle("A{$r}:{$last}{$r}")->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sh->getStyle("A{$r}:{$last}{$r}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('146336');
        $r++;

        foreach ($emp['daily'] as $d) {
            $hours = \fmt_minutes((int) $d['minutes']);
            $note  = $d['note'] ?: 'Present';
            $line  = $hasInOut ? [$d['date'], $d['in'] ?? '', $d['out'] ?? '', $hours, $note] : [$d['date'], $hours, $note];
            $sh->fromArray($line, null, "A{$r}");
            $dayStatus = \day_status((int) $d['minutes'], $dailyReq, $graceMin);
            $hoursCell = ($hasInOut ? 'D' : 'B') . $r;
            if ($dayStatus === 'day-ok') {
                $sh->getStyle($hoursCell)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E8F7EF');
                $sh->getStyle($hoursCell)->getFont()->setBold(true)->getColor()->setRGB('146336');
            } elseif ($dayStatus === 'day-grace') {
                $sh->getStyle($hoursCell)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFF0E0');
                $sh->getStyle($hoursCell)->getFont()->setBold(true)->getColor()->setRGB('B45309');
            } elseif ($dayStatus === 'day-low') {
                $sh->getStyle($hoursCell)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FDECEC');
                $sh->getStyle($hoursCell)->getFont()->setBold(true)->getColor()->setRGB('B3261E');
            }
            $r++;
        }

        $sh->fromArray($hasInOut ? ['Total', '', '', \fmt_minutes((int) $emp['minutes']), ''] : ['Total', \fmt_minutes((int) $emp['minutes']), ''], null, "A{$r}");
        $sh->getStyle("A{$r}:{$last}{$r}")->getFont()->setBold(true);
        $sh->getStyle("A{$hRow}:{$last}{$r}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('DFE7E1');

        foreach (range('A', 'E') as $col) {
            $sh->getColumnDimension($col)->setAutoSize(true);
        }

        // Write to memory (no temp file left behind).
        ob_start();
        (new Xlsx($ss))->save('php://output');
        $data = (string) ob_get_clean();

        $safe = preg_replace('/[^A-Za-z0-9]+/', '_', $weekLabel);
        $id   = preg_replace('/[^A-Za-z0-9]+/', '', (string) $emp['emp_id']);
        return [
            'data' => $data,
            'name' => "Attendance_Report_{$id}_{$safe}.xlsx",
            'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ];
    }
}
