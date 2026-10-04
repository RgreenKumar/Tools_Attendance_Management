<?php
declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

$config = require __DIR__ . '/config.php';

error_reporting(E_ALL);
ini_set('display_errors', $config['debug'] ? '1' : '0');

App\Database::configure($config['db'], $config['legacy_files']);
App\MailSettings::setFallback($config['smtp'] ?? []);

// Show a readable page (not a blank screen) if something fails, e.g. MySQL is not running.
set_exception_handler(function (Throwable $e) use ($config): void {
    error_log((string) $e);
    http_response_code(500);
    $db  = $e instanceof App\DatabaseUnavailable;
    $msg = $db
        ? 'The app could not reach its MySQL database. Start <b>MySQL</b> in the XAMPP Control Panel, '
          . 'check the <code>db</code> settings in <code>config.php</code>, then refresh this page.'
        : 'Something went wrong. Please try again.';
    echo '<!doctype html><meta charset="utf-8"><title>Error</title>'
       . '<body style="font-family:system-ui,sans-serif;background:#f3f6f4;display:flex;min-height:100vh;align-items:center;justify-content:center;margin:0">'
       . '<div style="background:#fff;border:1px solid #e3ebe6;border-radius:12px;padding:32px;max-width:520px;line-height:1.6">'
       . '<h2 style="margin:0 0 10px">' . ($db ? 'Database not available' : 'Error') . '</h2><p>' . $msg . '</p>'
       . ($config['debug'] ? '<pre style="white-space:pre-wrap;color:#b3261e;font-size:12px">' . htmlspecialchars($e->getMessage()) . '</pre>' : '')
       . '</div></body>';
});

// Session cookie: dies when the browser closes, not readable by JavaScript.
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => !empty($_SERVER['HTTPS']),
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

// Auto sign-out after a period of inactivity, so coming back later
// always lands on the login page.
$timeout = (int) ($config['session_timeout_minutes'] ?? 30) * 60;
if (!empty($_SESSION['user']) && isset($_SESSION['last_activity'])
    && (time() - $_SESSION['last_activity']) > $timeout) {
    unset($_SESSION['user'], $_SESSION['role'], $_SESSION['emp_id'], $_SESSION['report_id']);
    $_SESSION['error'] = 'You were signed out after a period of inactivity. Please sign in again.';
}
$_SESSION['last_activity'] = time();
