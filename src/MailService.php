<?php
declare(strict_types=1);

namespace App;

use PHPMailer\PHPMailer\PHPMailer;

class MailService
{
    public function __construct(private array $smtp, private string $company)
    {
    }

    /** Sends one employee their formal weekly attendance report. Throws on failure. */
    public function sendReport(array $emp, string $weekLabel): void
    {
        $mail = $this->newMailer();
        $mail->addAddress($emp['email'], $emp['name']);

        $status = emp_status($emp);

        $mail->isHTML(true);
        $mail->Subject = sprintf(
            '%s – Weekly Attendance Report (%s): %s',
            $this->company,
            $weekLabel,
            ['low' => 'Hours Below Requirement', 'grace' => 'Requirement Met (Grace Time Applied)', 'ok' => 'Requirement Met'][$status]
        );
        $mail->Body    = $this->htmlBody($emp, $weekLabel, $status);
        $mail->AltBody = $this->plainBody($emp, $weekLabel, $status);

        // The employee's own day-by-day report, attached so it can be downloaded straight from the email.
        // If building it ever fails, the email still goes out without it.
        try {
            $file = EmployeeReportFile::build($emp, $weekLabel, $this->company);
            $mail->addStringAttachment($file['data'], $file['name'], PHPMailer::ENCODING_BASE64, $file['mime']);
        } catch (\Throwable $e) {
            // no attachment
        }

        $mail->send();
    }

    /** Sends a short test message to confirm the SMTP settings actually work. Throws on failure. */
    public function sendTest(string $toEmail): void
    {
        $mail = $this->newMailer();
        $mail->addAddress($toEmail);
        $mail->isHTML(true);
        $mail->Subject = $this->company . ' – Test Email';
        $mail->Body    = '<div style="font-family:Arial,sans-serif;font-size:14px">'
            . '<p>This is a test message from the ' . e($this->company) . ' Attendance Portal.</p>'
            . '<p>If you received this, the mail settings are working correctly.</p></div>';
        $mail->AltBody = "This is a test message from the {$this->company} Attendance Portal.\n"
            . "If you received this, the mail settings are working correctly.";
        $mail->send();
    }

    /** Sends the admin's 6-digit sign-in code. Throws on failure. */
    public function sendCode(string $toEmail, string $code, int $minutes): void
    {
        $mail = $this->newMailer();
        $mail->addAddress($toEmail);
        $mail->isHTML(true);
        $mail->Subject = $this->company . ' – Your sign-in code';
        $mail->Body    = '<div style="font-family:Arial,sans-serif;font-size:14px;color:#16241c">'
            . '<p>Use this code to finish signing in to the ' . e($this->company) . ' Attendance Portal:</p>'
            . '<p style="font-size:30px;font-weight:700;letter-spacing:6px;margin:14px 0">' . e($code) . '</p>'
            . '<p>It expires in ' . $minutes . ' minutes. If you did not try to sign in, ignore this email and consider changing your password.</p></div>';
        $mail->AltBody = "Your sign-in code for the {$this->company} Attendance Portal is: {$code}\n"
            . "It expires in {$minutes} minutes. If you did not try to sign in, ignore this email.";
        $mail->send();
    }

    private function newMailer(): PHPMailer
    {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = $this->smtp['host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $this->smtp['username'];
        $mail->Password   = $this->smtp['password'];
        $mail->SMTPSecure = $this->smtp['encryption'] === 'ssl'
            ? PHPMailer::ENCRYPTION_SMTPS
            : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port    = (int) $this->smtp['port'];
        $mail->CharSet = 'UTF-8';

        $mail->setFrom($this->smtp['from_email'], $this->smtp['from_name']);
        $mail->addReplyTo($this->smtp['from_email'], $this->smtp['from_name']);

        return $mail;
    }

    private function plainBody(array $emp, string $weekLabel, string $status): string
    {
        $low   = $status === 'low';
        $grace = $status === 'grace';
        $intro = $low
            ? "This is to inform you that your attendance record for the period {$weekLabel} indicates that your "
                . 'total working hours were below the minimum requirement set by the company.'
            : ($grace
                ? "This is to confirm that your attendance record for the period {$weekLabel} is counted as meeting the "
                    . 'minimum working hours requirement after the grace time of ' . (int) ($emp['grace_minutes'] ?? 0) . ' minutes per day was applied.'
                : "This is to confirm that your attendance record for the period {$weekLabel} meets the minimum "
                    . 'working hours requirement set by the company.');

        $lines = sprintf(
            "Dear %s,\n\n%s\n\nEmployee ID: %s\nDepartment: %s\nPeriod: %s\nTotal Hours Worked: %s\nRequired Hours: %s\n",
            $emp['name'], $intro, $emp['emp_id'], $emp['dept'] ?: '-', $weekLabel,
            fmt_minutes($emp['minutes']), fmt_minutes($emp['required'])
        );
        if ((int) ($emp['grace_minutes'] ?? 0) > 0) {
            $lines .= 'Grace Time Allowed: ' . (int) $emp['grace_minutes'] . " min per day\n";
        }
        if ($low) {
            $lines .= 'Shortfall: ' . fmt_minutes($emp['shortfall']) . "\n";
        } elseif ($grace) {
            $lines .= 'Shortfall (covered by grace time): ' . fmt_minutes($emp['shortfall']) . "\n";
        }
        $lines .= "\nDaily breakdown:\n";
        foreach ($emp['daily'] as $d) {
            $lines .= sprintf("  %s — %s (%s)\n", $d['date'], fmt_minutes($d['minutes']), $d['note'] ?: 'Present');
        }

        $lines .= "\nYour detailed day-by-day report is attached to this email as an Excel file.\n"
            . "\nShould you have any questions regarding this report, please contact the Human Resources department.\n\n"
            . "Regards,\nHuman Resources\n{$this->company}\n\n"
            . 'This is an automatically generated message; please do not reply to this email directly.';

        return $lines;
    }

    private function htmlBody(array $emp, string $weekLabel, string $status): string
    {
        $low     = $status === 'low';
        $grace   = $status === 'grace';
        $graceMin = (int) ($emp['grace_minutes'] ?? 0);
        $accent  = $low ? '#b3261e' : ($grace ? '#b45309' : '#146336');
        $tint    = $low ? '#fdecec' : ($grace ? '#fff0e0' : '#e8f7ef');
        $tintLn  = $low ? '#f3cdc9' : ($grace ? '#f7cf9f' : '#bfe8cf');
        $label   = $low ? 'ATTENDANCE ALERT — HOURS BELOW REQUIREMENT'
            : ($grace ? 'ATTENDANCE CONFIRMATION — REQUIREMENT MET WITH GRACE TIME' : 'ATTENDANCE CONFIRMATION — REQUIREMENT MET');
        $intro   = $low
            ? 'This is to inform you that your attendance record for the period below indicates that your total '
                . 'working hours were <strong>below the minimum requirement</strong> set by the company. Details are provided below for your reference.'
            : ($grace
                ? 'This is to confirm that your attendance record for the period below is <strong>counted as meeting the minimum working '
                    . 'hours requirement</strong> after the grace time of ' . $graceMin . ' minutes per day was applied. Details are provided below for your reference.'
                : 'This is to confirm that your attendance record for the period below <strong>meets the minimum working '
                    . 'hours requirement</strong> set by the company. Details are provided below for your reference.');

        $cell = 'padding:8px 12px;border:1px solid #e2e8e4;text-align:left';
        $rows = '';
        [$dailyReq] = day_targets($emp);
        foreach ($emp['daily'] as $d) {
            $st   = day_status((int) $d['minutes'], $dailyReq, $graceMin);
            $hCss = $st === 'day-ok' ? ';background:#e8f7ef;color:#146336;font-weight:700'
                  : ($st === 'day-grace' ? ';background:#fff0e0;color:#b45309;font-weight:700'
                  : ($st === 'day-low' ? ';background:#fdecec;color:#b3261e;font-weight:700' : ''));
            $rows .= '<tr>'
                . '<td style="' . $cell . '">' . e($d['date']) . '</td>'
                . '<td style="' . $cell . $hCss . '">' . e(fmt_minutes($d['minutes'])) . '</td>'
                . '<td style="' . $cell . '">' . e($d['note'] ?: 'Present') . '</td>'
                . '</tr>';
        }

        $detailRows = [
            ['Employee ID', $emp['emp_id']],
            ['Department', $emp['dept'] ?: '—'],
            ['Reporting Period', $weekLabel],
            ['Total Hours Worked', fmt_minutes($emp['minutes'])],
            ['Required Hours', fmt_minutes($emp['required']) . ((int) ($emp['period_days'] ?? 0) > 0
                ? ' (' . fmt_minutes($dailyReq) . ' x ' . (int) $emp['period_days'] . ' working days)' : '')],
        ];
        if ($graceMin > 0) {
            $detailRows[] = ['Grace Time Allowed', $graceMin . ' min per day'];
        }
        if ($low) {
            $detailRows[] = ['Shortfall', fmt_minutes($emp['shortfall'])];
        } elseif ($grace) {
            $detailRows[] = ['Shortfall (covered by grace time)', fmt_minutes($emp['shortfall'])];
        }

        $detailHtml = '';
        foreach ($detailRows as [$k, $v]) {
            $detailHtml .= '<tr>'
                . '<td style="padding:4px 0;color:#647266;font-size:13px;width:180px">' . e($k) . '</td>'
                . '<td style="padding:4px 0;font-weight:700;font-size:13px">' . e($v) . '</td>'
                . '</tr>';
        }

        return '<div style="font-family:\'Segoe UI\',Arial,sans-serif;font-size:14px;color:#16241c;max-width:600px;margin:0 auto">'
            . '<div style="background:#146336;color:#ffffff;padding:18px 24px;font-weight:700;font-size:17px;letter-spacing:.01em">'
            . e($this->company) . '</div>'
            . '<div style="border:1px solid #dfe7e1;border-top:0;padding:26px 24px">'

            . '<div style="background:' . $tint . ';border:1px solid ' . $tintLn . ';color:' . $accent . ';'
            . 'padding:10px 16px;border-radius:6px;font-weight:700;font-size:12.5px;letter-spacing:.04em;margin-bottom:20px">'
            . e($label) . '</div>'

            . '<p style="margin:0 0 14px">Dear ' . e($emp['name']) . ',</p>'
            . '<p style="margin:0 0 20px;line-height:1.6">' . $intro . '</p>'

            . '<table style="width:100%;border-collapse:collapse;margin-bottom:22px">' . $detailHtml . '</table>'

            . '<table style="border-collapse:collapse;width:100%;margin-bottom:22px"><tr style="background:#f7faf8">'
            . '<th style="' . $cell . ';font-size:12px;text-transform:uppercase;color:#647266">Date</th>'
            . '<th style="' . $cell . ';font-size:12px;text-transform:uppercase;color:#647266">Hours</th>'
            . '<th style="' . $cell . ';font-size:12px;text-transform:uppercase;color:#647266">Remarks</th></tr>'
            . $rows . '</table>'

            . '<p style="margin:0 0 14px;line-height:1.6;background:#f7faf8;border:1px solid #e2e8e4;padding:10px 14px;border-radius:6px">'
            . '&#128206; Your detailed day-by-day report is <strong>attached to this email</strong> (Excel file) &mdash; open the attachment to view or download it.</p>'
            . '<p style="margin:0 0 6px;line-height:1.6">Should you have any questions regarding this report, please contact the Human Resources department.</p>'
            . '<p style="margin:20px 0 0">Regards,<br>Human Resources<br>' . e($this->company) . '</p>'

            . '<hr style="border:0;border-top:1px solid #dfe7e1;margin:22px 0 14px">'
            . '<p style="margin:0;color:#647266;font-size:12px">This is an automatically generated message. Please do not reply to this email directly.</p>'
            . '</div></div>';
    }
}
