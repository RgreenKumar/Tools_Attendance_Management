<?php
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/src/guard.php';

use App\AttendanceProcessor;
use App\EmployeeDirectory;
use App\MailService;
use App\MailSettings;
use App\ReportStore;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php');
}
csrf_check();

$file = $_FILES['attendance'] ?? null;
if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
    fail_and_back('The file did not upload. Choose a file and try again.');
}

$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!in_array($ext, ['xlsx', 'xls', 'csv'], true)) {
    fail_and_back('Only .xlsx, .xls or .csv files are allowed.');
}
if ($file['size'] > $config['max_upload_mb'] * 1024 * 1024) {
    fail_and_back('File is larger than ' . $config['max_upload_mb'] . ' MB.');
}

$dest = $config['upload_dir'] . '/' . bin2hex(random_bytes(8)) . '.' . $ext;
if (!move_uploaded_file($file['tmp_name'], $dest)) {
    fail_and_back('Could not save the uploaded file. Check that the uploads folder is writable.');
}

set_time_limit(300); // calculating + emailing everyone can take a while

try {
    $weeklyHours  = max(1, min(168, (float) ($_POST['required_hours'] ?? $config['required_weekly_hours'])));
    $workingDays  = max(1, min(7, (int) ($_POST['working_days'] ?? $config['working_days_per_week'])));
    $graceRaw     = trim((string) ($_POST['grace_minutes'] ?? ''));
    $graceMinutes = $graceRaw === '' ? 0 : max(0, min(60, (int) round((float) $graceRaw))); // blank = normal calculation
    $result = (new AttendanceProcessor())->process($dest, (int) round($weeklyHours * 60), $graceMinutes, $workingDays);

    // Week label comes from the real calendar dates found in the file, not a
    // typed-in guess — this is also what keeps the whole system date-accurate.
    $weekLabel = ($result['period_start'] && $result['period_end'])
        ? date('d M Y', strtotime($result['period_start'])) . ' – ' . date('d M Y', strtotime($result['period_end']))
        : 'this period';

    $directory = new EmployeeDirectory();
    $directory->syncFromUpload($result['employees']);
    $result['employees'] = $directory->attachEmails($result['employees']);

    // Automated step: email every employee their own report — a formal red
    // alert if they're below the requirement, a formal green confirmation if
    // they met it. No manual "send" click needed. Uses the SMTP details the
    // admin entered on the Mail Settings page, not a config file.
    $mailSettings = new MailSettings();
    $configured   = $mailSettings->isConfigured();
    $mailLog      = [];

    if ($configured) {
        $mailer = new MailService($mailSettings->get(), $config['company']);
        foreach ($result['employees'] as $id => $emp) {
            if (!filter_var($emp['email'], FILTER_VALIDATE_EMAIL)) {
                $mailLog[$id] = ['status' => 'skipped', 'detail' => 'No email on file'];
                continue;
            }
            try {
                $mailer->sendReport($emp, $weekLabel);
                $mailLog[$id] = ['status' => 'sent', 'detail' => ''];
            } catch (Throwable $ex) {
                $mailLog[$id] = ['status' => 'failed', 'detail' => $ex->getMessage()];
            }
        }
    } else {
        foreach ($result['employees'] as $id => $emp) {
            $mailLog[$id] = ['status' => 'skipped', 'detail' => 'Email is not set up yet — configure it on the Mail Settings page'];
        }
    }

    $_SESSION['report_id'] = (new ReportStore())->create($result, $weekLabel, $file['name'], $mailLog);
    if (!$configured) {
        $_SESSION['notice'] = 'Hours were calculated, but no emails were sent yet — set up SMTP on the Mail Settings page, then retry from this report.';
    }
} catch (Throwable $ex) {
    fail_and_back('Could not read the file: ' . $ex->getMessage());
} finally {
    @unlink($dest); // no need to keep the file
}

redirect('report.php');
