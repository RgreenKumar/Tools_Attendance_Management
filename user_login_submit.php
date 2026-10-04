<?php
require __DIR__ . '/bootstrap.php';

use App\Auth;

// EMPLOYEE sign-in: email + password (the login is created automatically by the admin on the Employees page).
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('login.php');
}
csrf_check();

$auth     = new Auth();
$email    = trim((string) ($_POST['email'] ?? ''));
$password = (string) ($_POST['password'] ?? '');
$fail     = function (string $message) use ($email): never {
    $_SESSION['error'] = $message;
    $_SESSION['old']   = ['email' => $email];
    redirect('login.php');
};

// Slow down guessing: 5 wrong tries in this browser session -> wait 5 minutes.
$tries = $_SESSION['login_fail'] ?? ['n' => 0, 'at' => 0];
if ($tries['n'] >= 5 && time() - (int) $tries['at'] < 300) {
    $fail('Too many wrong attempts. Please wait a few minutes and try again.');
}
if ($tries['n'] >= 5) {
    $tries = ['n' => 0, 'at' => 0];
}

$user = filter_var($email, FILTER_VALIDATE_EMAIL) ? $auth->attemptUser($email, $password) : null;
if ($user === null) {
    $_SESSION['login_fail'] = ['n' => $tries['n'] + 1, 'at' => time()];
    $fail('Incorrect email or password.');
}

unset($_SESSION['login_fail']);
Auth::login($user['email'], 'user', $user['emp_id']);
redirect('my_report.php');
