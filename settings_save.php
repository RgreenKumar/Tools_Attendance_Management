<?php
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/src/guard.php';

use App\MailSettings;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('settings.php');
}
csrf_check();

$data = [
    'host'       => trim((string) ($_POST['host'] ?? '')),
    'port'       => (int) ($_POST['port'] ?? 0),
    'encryption' => in_array($_POST['encryption'] ?? '', ['tls', 'ssl'], true) ? $_POST['encryption'] : 'tls',
    'username'   => trim((string) ($_POST['username'] ?? '')),
    'password'   => (string) ($_POST['password'] ?? ''),
    'from_email' => trim((string) ($_POST['from_email'] ?? '')),
    'from_name'  => trim((string) ($_POST['from_name'] ?? '')),
];

if ($data['host'] === '' || $data['port'] < 1 || $data['port'] > 65535 || $data['username'] === ''
    || !filter_var($data['from_email'], FILTER_VALIDATE_EMAIL)) {
    $_SESSION['error'] = 'Check the details: host, a valid port, a username, and a valid "from" email are all required.';
    redirect('settings.php');
}

(new MailSettings())->save($data);
$_SESSION['notice'] = 'Mail settings saved.';
redirect('settings.php');
