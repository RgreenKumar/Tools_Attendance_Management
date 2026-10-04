<?php
declare(strict_types=1);

namespace App;

use PDO;

/**
 * Saves every weekly report (header + one row per employee, including the
 * day-by-day breakdown and the email result) so past weeks can be reopened,
 * downloaded again, or have failed emails retried.
 */
class ReportStore
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::pdo();
    }

    /** @param array $mailLog [emp_id => ['status' => 'sent|failed|skipped', 'detail' => '..']] */
    public function create(array $result, string $weekLabel, string $fileName, array $mailLog): int
    {
        $this->db->beginTransaction();
        try {
            $this->db->prepare(
                'INSERT INTO reports (week_label, period_start, period_end, file_name, required_minutes, daily_minutes, period_days, grace_minutes, warnings)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([
                $weekLabel,
                $result['period_start'] ?: null,
                $result['period_end'] ?: null,
                $fileName,
                (int) $result['required_minutes'],
                (int) ($result['daily_minutes'] ?? 0),
                (int) ($result['period_days'] ?? 0),
                (int) ($result['grace_minutes'] ?? 0),
                json_encode(array_values($result['warnings'] ?? []), JSON_UNESCAPED_UNICODE),
            ]);
            $reportId = (int) $this->db->lastInsertId();

            $ins = $this->db->prepare(
                'INSERT INTO report_entries
                   (report_id, emp_id, name, dept, days, leave_days, minutes, required, shortfall, low, email, mail_status, mail_detail, daily)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            foreach ($result['employees'] as $empId => $e) {
                $mail = $mailLog[$empId] ?? ['status' => 'pending', 'detail' => ''];
                $ins->execute([
                    $reportId,
                    (string) $empId,
                    (string) $e['name'],
                    (string) ($e['dept'] ?? ''),
                    (int) $e['days'],
                    (int) ($e['leave_days'] ?? 0),
                    (int) $e['minutes'],
                    (int) $e['required'],
                    (int) $e['shortfall'],
                    $e['low'] ? 1 : 0,
                    (string) $e['email'],
                    $mail['status'],
                    mb_substr((string) $mail['detail'], 0, 500),
                    json_encode($e['daily'], JSON_UNESCAPED_UNICODE),
                ]);
            }
            $this->db->commit();
            return $reportId;
        } catch (\Throwable $t) {
            $this->db->rollBack();
            throw $t;
        }
    }

    /** One saved report in the same shape the report page and emails expect, or null. */
    public function find(int $id): ?array
    {
        $st = $this->db->prepare('SELECT * FROM reports WHERE id = ?');
        $st->execute([$id]);
        $r = $st->fetch();
        if (!$r) {
            return null;
        }

        $st = $this->db->prepare('SELECT * FROM report_entries WHERE report_id = ? ORDER BY name, id');
        $st->execute([$id]);

        $employees = [];
        $mailLog   = [];
        foreach ($st->fetchAll() as $row) {
            $key             = $row['emp_id'];
            $row['grace_minutes'] = (int) $r['grace_minutes'];
            $row['daily_minutes'] = (int) $r['daily_minutes'];
            $row['period_days']   = (int) $r['period_days'];
            $employees[$key] = self::rowToEmployee($row);
            $mailLog[$key]   = ['status' => $row['mail_status'], 'detail' => $row['mail_detail']];
        }

        return [
            'id'               => (int) $r['id'],
            'week_label'       => $r['week_label'],
            'period_start'     => $r['period_start'],
            'period_end'       => $r['period_end'],
            'file_name'        => $r['file_name'],
            'required_minutes' => (int) $r['required_minutes'],
            'daily_minutes'    => (int) $r['daily_minutes'],
            'period_days'      => (int) $r['period_days'],
            'grace_minutes'    => (int) $r['grace_minutes'],
            'warnings'         => json_decode((string) $r['warnings'], true) ?: [],
            'created_at'       => $r['created_at'],
            'employees'        => $employees,
            'mail_log'         => $mailLog,
        ];
    }

    private static function rowToEmployee(array $row): array
    {
        return [
            'emp_id'     => (string) $row['emp_id'],
            'name'       => $row['name'],
            'dept'       => $row['dept'],
            'days'       => (int) $row['days'],
            'leave_days' => (int) $row['leave_days'],
            'minutes'    => (int) $row['minutes'],
            'required'   => (int) $row['required'],
            'shortfall'  => (int) $row['shortfall'],
            'low'        => (bool) $row['low'],
            'graced'     => !$row['low'] && (int) $row['minutes'] < (int) $row['required'],
            'grace_minutes' => (int) ($row['grace_minutes'] ?? 0),
            'daily_required' => (int) ($row['daily_minutes'] ?? 0),
            'period_days'   => (int) ($row['period_days'] ?? 0),
            'email'      => $row['email'],
            'daily'      => json_decode((string) $row['daily'], true) ?: [],
        ];
    }

    /**
     * One employee's entry in one report, with the report's period details, or null.
     * @return array{report: array, emp: array}|null
     */
    public function entry(int $reportId, string $empId): ?array
    {
        $st = $this->db->prepare(
            'SELECT r.id AS report_id, r.week_label, r.period_start, r.period_end, r.file_name, r.created_at,
                    r.required_minutes, r.daily_minutes, r.period_days, r.grace_minutes, e.*
             FROM report_entries e JOIN reports r ON r.id = e.report_id
             WHERE e.report_id = ? AND e.emp_id = ? LIMIT 1'
        );
        $st->execute([$reportId, $empId]);
        $row = $st->fetch();
        if (!$row) {
            return null;
        }
        return [
            'report' => [
                'id'               => (int) $row['report_id'],
                'week_label'       => $row['week_label'],
                'period_start'     => $row['period_start'],
                'period_end'       => $row['period_end'],
                'file_name'        => $row['file_name'],
                'created_at'       => $row['created_at'],
                'required_minutes' => (int) $row['required_minutes'],
                'daily_minutes'    => (int) $row['daily_minutes'],
                'period_days'      => (int) $row['period_days'],
                'grace_minutes'    => (int) $row['grace_minutes'],
            ],
            'emp' => self::rowToEmployee($row),
        ];
    }

    /** Every week this employee appears in, newest first (for the employee's own page). */
    public function forEmployee(string $empId): array
    {
        $st = $this->db->prepare(
            'SELECT r.id AS report_id, r.week_label, r.period_start, e.minutes, e.required, e.low, e.days
             FROM report_entries e JOIN reports r ON r.id = e.report_id
             WHERE e.emp_id = ?
             ORDER BY r.period_start DESC, r.id DESC'
        );
        $st->execute([$empId]);
        return $st->fetchAll();
    }

    public function setMail(int $reportId, string $empId, string $status, string $detail = ''): void
    {
        $this->db->prepare('UPDATE report_entries SET mail_status = ?, mail_detail = ? WHERE report_id = ? AND emp_id = ?')
            ->execute([$status, mb_substr($detail, 0, 500), $reportId, $empId]);
    }

    /** Newest first, with headline counts, for the History page. */
    public function all(): array
    {
        return $this->db->query(
            "SELECT r.id, r.week_label, r.file_name, r.created_at,
                    COUNT(e.id) AS employees,
                    COALESCE(SUM(e.low), 0) AS below,
                    COALESCE(SUM(e.mail_status = 'sent'), 0) AS sent
             FROM reports r LEFT JOIN report_entries e ON e.report_id = r.id
             GROUP BY r.id, r.week_label, r.file_name, r.created_at
             ORDER BY r.id DESC"
        )->fetchAll();
    }
}
