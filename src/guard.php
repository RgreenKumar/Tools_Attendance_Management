<?php
// Admin-only pages: upload, reports, history, employees, mail settings.
use App\Auth;

$auth = new Auth();
if (!$auth->isRegistered()) {
    redirect('register.php');
}
Auth::requireAdmin();
