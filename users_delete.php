<?php
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/src/guard.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('users.php');
}
csrf_check();

$id = (int) ($_POST['id'] ?? 0);
if ($id > 0) {
    $auth->deleteUser($id);
    $_SESSION['notice'] = 'Login removed. Set a new password on the Employees page to give access again.';
}
redirect('users.php');
