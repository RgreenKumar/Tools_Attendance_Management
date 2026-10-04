<?php
require __DIR__ . '/bootstrap.php';

use App\Auth;

// ADMIN sign-in: email/username + password. No code.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('login.php?as=admin');
}
csrf_check();

$auth = new Auth();
$name = $auth->attemptAdmin(trim((string) ($_POST['username'] ?? '')), (string) ($_POST['password'] ?? ''));
if ($name === null) {
    $_SESSION['error'] = 'Incorrect email/username or password.';
    redirect('login.php?as=admin');
}

Auth::login($name, 'admin');
redirect('index.php');
