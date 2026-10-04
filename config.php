<?php
// ---- Edit these values ----
return [
    'company'               => 'RGreen Technologies',
    'required_weekly_hours' => 45,               // default 'required hours for the week' on the upload page
    'working_days_per_week' => 5,                // default 'working days' on the upload page (daily target = weekly hours / working days)
    'upload_dir'            => __DIR__ . '/uploads',
    // Old JSON files: only read once, to copy existing data into MySQL on first run.
    'legacy_files' => [
        'employees' => __DIR__ . '/data/employees.json',
        'auth'      => __DIR__ . '/data/auth.json',
    ],

    // MySQL. XAMPP default is user "root" with an empty password. The database
    // and its tables are created automatically the first time the app runs.
    'db' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'rgreen_attendance',
        'user' => 'root',
        'pass' => '',
    ],

    'max_upload_mb'         => 5,
    'session_timeout_minutes' => 30,             // auto sign-out after this much inactivity
    'debug'                 => false,             // true while developing: shows PHP errors on screen

    // ONE-TIME: the email account that SENDS the employees' sign-in codes and the weekly reports.
    // Use the ADMIN's Gmail: turn on 2-Step Verification, create an App Password at
    // https://myaccount.google.com/apppasswords and paste it as 'password' below (spaces are fine).
    // Leave 'username' blank to use the admin's own email automatically, or type the address here.
    // Nobody types this on the login page. (The Mail Settings page can override it later.)
    'smtp' => [
        'host'       => 'smtp.gmail.com',
        'port'       => 587,
        'encryption' => 'tls',             // 'tls' (port 587) or 'ssl' (port 465)
        'username'   => '',                // blank = the admin's email; or e.g. yourcompany@gmail.com
        'password'   => '',                // the 16-character App Password
        'from_email' => '',                // leave blank to use the username
        'from_name'  => 'RGreen Technologies',
    ],

    // Email (SMTP) can also be set — configure it from the "Mail
    // Settings" page inside the app once you're logged in.
];
