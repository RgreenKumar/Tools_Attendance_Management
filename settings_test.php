<?php
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/src/guard.php';

use App\MailService;
use App\MailSettings;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('settings.php');
}
csrf_check();

$to = trim((string) ($_POST['test_email'] ?? ''));
if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['error'] = 'Enter a valid email address to send the test to.';
    redirect('settings.php');
}

$mailSettings = new MailSettings();
if (!$mailSettings->isConfigured()) {
    $_SESSION['error'] = 'Save your mail settings first — host, username, password and from email are all required.';
    redirect('settings.php');
}

try {
    (new MailService($mailSettings->get(), $config['company']))->sendTest($to);
    $_SESSION['notice'] = "Test email sent to {$to}. Check the inbox (and spam folder).";
} catch (Throwable $ex) {
    $_SESSION['error'] = 'Could not send the test email: ' . $ex->getMessage();
}

redirect('settings.php');
