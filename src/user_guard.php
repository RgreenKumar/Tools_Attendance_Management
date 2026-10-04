<?php
// Normal-user pages: any signed-in employee account (never the admin).
use App\Auth;

$auth = new Auth();
if (!$auth->isRegistered()) {
    redirect('register.php');
}
Auth::requireUser();
