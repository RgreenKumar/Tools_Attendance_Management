<?php
require __DIR__ . '/bootstrap.php';

use App\Auth;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('register.php');
}
csrf_check();

$auth = new Auth();
if ($auth->isRegistered()) {
    redirect('login.php');
}

$username = trim((string) ($_POST['username'] ?? ''));
$password = (string) ($_POST['password'] ?? '');
$password2 = (string) ($_POST['password2'] ?? '');

if (!filter_var($username, FILTER_VALIDATE_EMAIL) || strlen($password) < 8 || $password !== $password2) {
    $_SESSION['error'] = 'Check your details: a valid email, a password of 8+ characters, and both passwords matching.';
    redirect('register.php');
}

$auth->register($username, $password);
$_SESSION['notice'] = 'Admin account created. Please sign in.';
redirect('login.php');
