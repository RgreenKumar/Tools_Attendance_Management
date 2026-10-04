<?php
require __DIR__ . '/bootstrap.php';

use App\Auth;

Auth::logout();
redirect('login.php');
