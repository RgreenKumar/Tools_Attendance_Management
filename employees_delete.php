<?php
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/src/guard.php';

use App\EmployeeDirectory;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('employees.php');
}
csrf_check();

$id = trim((string) ($_POST['id'] ?? ''));
if ($id !== '') {
    (new EmployeeDirectory())->delete($id);
    $_SESSION['notice'] = "Employee \"{$id}\" deleted.";
}

redirect('employees.php');
