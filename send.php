<?php
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/src/guard.php';

use App\MailService;
use App\MailSettings;
use App\ReportStore;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('report.php');
}
csrf_check();

$store    = new ReportStore();
$reportId = (int) ($_POST['id'] ?? $_SESSION['report_id'] ?? 0);
$report   = $reportId > 0 ? $store->find($reportId) : null;
if (!$report) {
    redirect('index.php');
}

$mailSettings = new MailSettings();
if (!$mailSettings->isConfigured()) {
    $_SESSION['error'] = 'Email is not set up yet. Configure SMTP on the Mail Settings page first.';
    redirect('report.php?id=' . $reportId);
}

set_time_limit(300);

$mailer = new MailService($mailSettings->get(), $config['company']);

foreach ($report['employees'] as $id => $emp) {
    if (($report['mail_log'][$id]['status'] ?? '') === 'sent') {
        continue; // already sent, nothing to retry
    }
    if (!filter_var($emp['email'], FILTER_VALIDATE_EMAIL)) {
        $store->setMail($reportId, (string) $id, 'skipped', 'No email on file');
        continue;
    }
    try {
        $mailer->sendReport($emp, $report['week_label']);
        $store->setMail($reportId, (string) $id, 'sent');
    } catch (Throwable $ex) {
        $store->setMail($reportId, (string) $id, 'failed', $ex->getMessage());
    }
}

$_SESSION['notice'] = 'Retried the emails that had not gone out yet.';
redirect('report.php?id=' . $reportId);
