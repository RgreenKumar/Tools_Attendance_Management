<?php
declare(strict_types=1);

namespace App;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as XlDate;

/**
 * Reads a weekly attendance export and totals worked hours per employee.
 *
 * Supports two layouts, auto-detected:
 *
 *  1. Device export (e.g. ZKTeco "Employee Attendance Table"): several employees
 *     side by side per sheet, each in its own block of columns, with a
 *     "Time Card" section giving Before Noon / After Noon / Overtime In+Out
 *     punches per day. This has no email column, so email must come from the
 *     EmployeeDirectory.
 *
 *  2. Flat table: one row per employee per day, with columns
 *     Emp ID | Name | Email | Date | In Time | Out Time.
 */
class AttendanceProcessor
{
    private const BLOCK_WIDTH = 15;

    /**
     * @param int $weeklyMinutes required hours for the week (in minutes), typed on the upload page
     * @param int $graceMinutes  grace time PER DAY in minutes (0-60)
     * @param int $workingDays   working days in the week, typed on the upload page (NOT read from the file)
     *
     * Daily target = weekly hours / working days. Grace allowance for the week = grace x working days.
     * Example: 45h, 5 days, 30 min grace -> 9h per day; week passes (orange) from 42h 30m, green from 45h.
     */
    public function process(string $path, int $weeklyMinutes, int $graceMinutes = 0, int $workingDays = 5): array
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($path);

        $deviceSheets = $this->findDeviceSheets($spreadsheet);

        $result = $deviceSheets
            ? $this->processDeviceExport($deviceSheets, $weeklyMinutes)
            : $this->processFlatTable($spreadsheet->getActiveSheet(), $weeklyMinutes);

        if (!$result['employees']) {
            throw new \RuntimeException('No employee rows were found in this file.');
        }

        $period = $this->calendarPeriod($result['employees']);

        $days          = max(1, min(7, $workingDays));
        $graceMinutes  = max(0, min(60, $graceMinutes));
        $dailyMinutes  = (int) round($weeklyMinutes / $days);
        $weekAllowance = \weekly_grace($graceMinutes, $days);

        // green: total >= required; orange: short, but by no more than the grace allowance; red: short by more.
        foreach ($result['employees'] as &$emp) {
            $emp['required']       = $weeklyMinutes;
            $emp['daily_required'] = $dailyMinutes;
            $emp['period_days']    = $days;
            $emp['grace_minutes']  = $graceMinutes;
            $emp['shortfall']      = max(0, $weeklyMinutes - $emp['minutes']);
            $emp['low']            = $emp['minutes'] < $weeklyMinutes - $weekAllowance;
            $emp['graced']         = !$emp['low'] && $emp['minutes'] < $weeklyMinutes;
        }
        unset($emp);

        uasort($result['employees'], fn($a, $b) => strcasecmp($a['name'], $b['name']));

        return $result + [
            'required_minutes' => $weeklyMinutes,
            'daily_minutes'    => $dailyMinutes,
            'period_days'      => $days,
            'grace_minutes'    => $graceMinutes,
        ] + $period;
    }

    /** Real calendar span of the data: earliest/latest date seen and how many distinct calendar days that covers. */
    private function calendarPeriod(array $employees): array
    {
        $keys = [];
        foreach ($employees as $emp) {
            foreach ($emp['daily'] as $day) {
                if (!empty($day['date_key'])) {
                    $keys[] = $day['date_key'];
                }
            }
        }
        $keys = array_unique($keys);
        sort($keys);

        return [
            'period_start' => $keys ? $keys[array_key_first($keys)] : null,
            'period_end'   => $keys ? $keys[array_key_last($keys)] : null,
            'period_days'  => count($keys) ?: 1,
        ];
    }

    // ---------------------------------------------------------------
    // Layout 1: device export (multiple employee blocks per sheet)
    // ---------------------------------------------------------------

    /** @return \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet[] sheets that contain a "Time Card" block */
    private function findDeviceSheets(\PhpOffice\PhpSpreadsheet\Spreadsheet $spreadsheet): array
    {
        $sheets = [];
        foreach ($spreadsheet->getAllSheets() as $sheet) {
            $maxRow = min($sheet->getHighestRow(), 200);
            for ($r = 1; $r <= $maxRow; $r++) {
                if ($this->cell($sheet, $r, 1) === 'Time Card') {
                    $sheets[] = $sheet;
                    break;
                }
            }
        }
        return $sheets;
    }

    private function processDeviceExport(array $sheets, int $requiredMinutes): array
    {
        $employees = [];
        $warnings  = [];

        foreach ($sheets as $sheet) {
            [$deptRow, $blockCols] = $this->findBlocks($sheet);
            if ($deptRow === null) {
                continue;
            }
            $dateRow = $deptRow + 1;

            foreach ($blockCols as $c) {
                $userId = trim((string) $this->cell($sheet, $dateRow, $c + 9));
                if ($userId === '') {
                    continue; // empty slot in the template
                }

                $name = trim((string) $this->cell($sheet, $deptRow, $c + 9));
                $dept = trim((string) $this->cell($sheet, $deptRow, $c + 1));

                $tcRow = $this->findInColumn($sheet, $c, 'Time Card');
                if ($tcRow === null) {
                    continue;
                }
                $sessionCols = $this->findSessionColumns($sheet, $tcRow + 2, $c);
                $dataStart   = $tcRow + 3;
                $startDate   = $this->parseRangeStart((string) $this->cell($sheet, $dateRow, $c + 1));

                if (!isset($employees[$userId])) {
                    $employees[$userId] = [
                        'emp_id'  => $userId,
                        'name'    => $name,
                        'dept'    => $dept,
                        'minutes' => 0,
                        'days'    => 0,
                        'missing' => 0,
                        'daily'   => [],
                    ];
                }

                $r = $dataStart;
                $dayOffset = 0;
                $maxRow = $sheet->getHighestRow();
                while ($r <= $maxRow) {
                    $label = $this->cell($sheet, $r, $c);
                    if ($label === null || trim((string) $label) === '') {
                        break;
                    }

                    $dayMinutes = 0;
                    $hadPunch   = false;
                    $partial    = false;

                    foreach ($sessionCols as [$inCol, $outCol]) {
                        $in  = $this->toMinutes($this->cell($sheet, $r, $inCol));
                        $out = $this->toMinutes($this->cell($sheet, $r, $outCol));
                        if ($in !== null && $out !== null) {
                            $diff = $out - $in;
                            if ($diff < 0) {
                                $diff += 1440;
                            }
                            $dayMinutes += $diff;
                            $hadPunch = true;
                        } elseif ($in !== null || $out !== null) {
                            $partial = true;
                            $hadPunch = true;
                        }
                    }

                    $note = '';
                    if ($partial) {
                        $note = 'Missing punch';
                        $employees[$userId]['missing']++;
                        $warnings[] = "{$name} (ID {$userId}): a punch is missing on " . ($label ?: 'one day') . ', counted as partial.';
                    } elseif (!$hadPunch) {
                        $note = 'Absent / no punch';
                    } else {
                        $employees[$userId]['days']++;
                    }

                    $dateForRow = $startDate ? (clone $startDate)->modify("+{$dayOffset} days") : null;
                    $dateLabel  = $dateForRow ? $dateForRow->format('D, d M Y') : (string) $label;
                    $dateKey    = $dateForRow ? $dateForRow->format('Y-m-d') : null;

                    $employees[$userId]['minutes'] += $dayMinutes;
                    $employees[$userId]['daily'][] = [
                        'date'     => $dateLabel,
                        'date_key' => $dateKey,
                        'in'       => '',
                        'out'      => '',
                        'minutes'  => $dayMinutes,
                        'note'     => $note,
                    ];

                    $r++;
                    $dayOffset++;
                }
            }
        }

        foreach ($employees as &$emp) {
            $emp['required']  = $requiredMinutes;
            $emp['shortfall'] = max(0, $requiredMinutes - $emp['minutes']);
            $emp['low']       = $emp['minutes'] < $requiredMinutes;
        }
        unset($emp);

        return ['employees' => $employees, 'warnings' => $warnings, 'source' => 'device'];
    }

    /** Locate every block's "Dept." header cell. Returns [row, [columns]] or [null, []]. */
    private function findBlocks(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet): array
    {
        $maxRow = min($sheet->getHighestRow(), 30);
        $maxCol = $sheet->getHighestColumn();
        $maxColIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($maxCol);

        for ($r = 1; $r <= $maxRow; $r++) {
            $cols = [];
            for ($c = 1; $c <= $maxColIndex; $c++) {
                if (trim((string) $this->cell($sheet, $r, $c)) === 'Dept.') {
                    $cols[] = $c;
                }
            }
            if ($cols) {
                return [$r, $cols];
            }
        }
        return [null, []];
    }

    private function findInColumn(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, int $col, string $text): ?int
    {
        $maxRow = $sheet->getHighestRow();
        for ($r = 1; $r <= $maxRow; $r++) {
            if (trim((string) $this->cell($sheet, $r, $col)) === $text) {
                return $r;
            }
        }
        return null;
    }

    /** Pair up In/Out columns within one block, starting at the block's first column. */
    private function findSessionColumns(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, int $headerRow, int $blockStart): array
    {
        $pairs = [];
        $limit = $blockStart + self::BLOCK_WIDTH;
        $c = $blockStart;
        while ($c < $limit) {
            if (trim((string) $this->cell($sheet, $headerRow, $c)) === 'In') {
                $oc = $c + 1;
                while ($oc < $limit && trim((string) $this->cell($sheet, $headerRow, $oc)) !== 'Out') {
                    $oc++;
                }
                if ($oc < $limit) {
                    $pairs[] = [$c, $oc];
                }
                $c = $oc + 1;
            } else {
                $c++;
            }
        }
        return $pairs;
    }

    private function parseRangeStart(string $range): ?\DateTime
    {
        if (preg_match('/(\d{4})-(\d{2})-(\d{2})/', $range, $m)) {
            return \DateTime::createFromFormat('Y-m-d', "{$m[1]}-{$m[2]}-{$m[3]}") ?: null;
        }
        return null;
    }

    // ---------------------------------------------------------------
    // Layout 2: flat table (Emp ID | Name | Email | Date | In | Out)
    // ---------------------------------------------------------------

    private const FLAT_ALIASES = [
        'emp_id' => ['emp id', 'employee id', 'empid', 'emp no', 'employee no', 'id'],
        'name'   => ['name', 'employee name', 'emp name'],
        'email'  => ['email', 'e-mail', 'mail', 'email id', 'email address'],
        'date'   => ['date', 'day', 'attendance date'],
        'in'     => ['in time', 'in', 'intime', 'check in', 'punch in', 'time in'],
        'out'    => ['out time', 'out', 'outtime', 'check out', 'punch out', 'time out'],
    ];

    private function processFlatTable(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, int $requiredMinutes): array
    {
        $rows = $sheet->toArray(null, true, false, false);
        if (count($rows) < 2) {
            throw new \RuntimeException('The file has no data rows.');
        }

        $map = $this->mapFlatColumns(array_shift($rows));

        $employees = [];
        $warnings  = [];

        foreach ($rows as $i => $row) {
            $rowNo = $i + 2;
            $id = trim((string) ($row[$map['emp_id']] ?? ''));
            if ($id === '') {
                continue;
            }

            $name  = trim((string) ($row[$map['name']] ?? ''));
            $email = trim((string) ($row[$map['email']] ?? ''));
            $in    = $this->toMinutes($row[$map['in']] ?? null);
            $out   = $this->toMinutes($row[$map['out']] ?? null);
            [$date, $dateKey] = isset($map['date'])
                ? $this->flatDateParts($row[$map['date']] ?? null, $rowNo)
                : ['Row ' . $rowNo, null];

            if (!isset($employees[$id])) {
                $employees[$id] = [
                    'emp_id'  => $id,
                    'name'    => $name,
                    'email'   => $email,
                    'dept'    => '',
                    'minutes' => 0,
                    'days'    => 0,
                    'missing' => 0,
                    'daily'   => [],
                ];
            }

            $worked = 0;
            $note   = '';
            if ($in === null && $out === null) {
                $note = 'Absent / no punch';
            } elseif ($in === null || $out === null) {
                $note = 'Missing punch';
                $employees[$id]['missing']++;
                $warnings[] = "Row {$rowNo} ({$name}): in/out time missing, counted as 0 hours.";
            } else {
                $worked = $out - $in;
                if ($worked < 0) {
                    $worked += 1440;
                }
                $employees[$id]['days']++;
            }

            $employees[$id]['minutes'] += $worked;
            $employees[$id]['daily'][] = [
                'date' => $date, 'date_key' => $dateKey, 'in' => $this->clock($in), 'out' => $this->clock($out),
                'minutes' => $worked, 'note' => $note,
            ];
        }

        foreach ($employees as &$emp) {
            $emp['required']  = $requiredMinutes;
            $emp['shortfall'] = max(0, $requiredMinutes - $emp['minutes']);
            $emp['low']       = $emp['minutes'] < $requiredMinutes;
        }
        unset($emp);

        return ['employees' => $employees, 'warnings' => $warnings, 'source' => 'flat'];
    }

    private function mapFlatColumns(array $header): array
    {
        $map = [];
        foreach ($header as $index => $cell) {
            $text = preg_replace('/^\xEF\xBB\xBF/', '', (string) $cell);
            $h = strtolower(trim(preg_replace('/[\s_]+/', ' ', $text)));
            foreach (self::FLAT_ALIASES as $key => $names) {
                if (!isset($map[$key]) && in_array($h, $names, true)) {
                    $map[$key] = $index;
                }
            }
        }
        $required = ['emp_id' => 'Emp ID', 'name' => 'Name', 'in' => 'In Time', 'out' => 'Out Time'];
        $missing  = array_diff_key($required, $map);
        if ($missing) {
            throw new \RuntimeException(
                'This file does not look like a device export or a simple attendance sheet. '
                . 'Missing column(s): ' . implode(', ', $missing) . '.'
            );
        }
        return $map;
    }

    /** @return array{0:string,1:?string} [display label, ISO Y-m-d key or null] */
    private function flatDateParts(mixed $v, int $rowNo): array
    {
        if ($v === null || $v === '') {
            return ['Row ' . $rowNo, null];
        }
        if (is_numeric($v)) {
            $dt = XlDate::excelToDateTimeObject((float) $v);
            return [$dt->format('D, d M Y'), $dt->format('Y-m-d')];
        }
        $t = strtotime((string) $v);
        return $t ? [date('D, d M Y', $t), date('Y-m-d', $t)] : [(string) $v, null];
    }

    // ---------------------------------------------------------------
    // Shared helpers
    // ---------------------------------------------------------------

    private function cell(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, int $row, int $col): mixed
    {
        $ref = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col) . $row;
        return $sheet->getCell($ref)->getValue();
    }

    private function toMinutes(mixed $v): ?int
    {
        if ($v === null || $v === '') {
            return null;
        }
        if ($v instanceof \DateTimeInterface) {
            return (int) $v->format('H') * 60 + (int) $v->format('i');
        }
        if (is_numeric($v)) {
            $fraction = (float) $v - floor((float) $v);
            return ((int) round($fraction * 1440)) % 1440;
        }
        $t = strtotime(trim((string) $v));
        if ($t === false) {
            return null;
        }
        return (int) date('H', $t) * 60 + (int) date('i', $t);
    }

    private function clock(?int $m): string
    {
        return $m === null ? '--' : sprintf('%02d:%02d', intdiv($m, 60), $m % 60);
    }
}
