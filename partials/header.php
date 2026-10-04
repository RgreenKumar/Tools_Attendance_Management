<?php
/** @var array $config expected in scope */
use App\Auth;
$current = basename($_SERVER['SCRIPT_NAME']);
$navItem = fn(string $file) => $current === $file ? 'active' : '';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ctext y='.9em' font-size='90'%3E%F0%9F%8C%BF%3C/text%3E%3C/svg%3E">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <title><?= isset($pageTitle) ? e($pageTitle) . ' · ' : '' ?><?= e($config['company']) ?></title>
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="app">
  <div class="sidebar-backdrop" id="sidebarBackdrop"></div>
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
      <span class="sidebar-mark">🌿</span>
      <span class="sidebar-brand-text"><?= e($config['company']) ?><small><?= Auth::isAdmin() ? 'Attendance Portal' : 'Employee Portal' ?></small></span>
    </div>
    <nav class="sidebar-nav">
    <?php if (Auth::isAdmin()): ?>
      <a href="index.php" class="<?= $navItem('index.php') ?>">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3v12m0 0-4-4m4 4 4-4M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/></svg>
        Upload
      </a>
      <a href="employees.php" class="<?= in_array($current, ['employees.php', 'employees_edit.php'], true) ? 'active' : '' ?>">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        Employees
      </a>
      <a href="users.php" class="<?= $navItem('users.php') ?>">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="m17 11 2 2 4-4"/></svg>
        Users
      </a>
      <a href="history.php" class="<?= $navItem('history.php') ?>">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5M12 7v5l3 2"/></svg>
        History
      </a>
      <a href="settings.php" class="<?= $navItem('settings.php') ?>">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16v12H8l-4 4V4Z"/><path d="M8 9h8M8 12h5"/></svg>
        Mail Settings
      </a>
    <?php else: ?>
      <a href="my_report.php" class="<?= $navItem('my_report.php') ?>">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
        My hours
      </a>
      <a href="my_profile.php" class="<?= $navItem('my_profile.php') ?>">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
        My profile
      </a>
    <?php endif; ?>
    </nav>
    <div class="sidebar-account">
      <div class="sidebar-account-name"><?= e(Auth::currentUser() ?? '') ?></div>
      <a href="logout.php" class="sidebar-logout">Sign out</a>
    </div>
  </aside>

  <div class="mobile-topbar">
    <button type="button" class="mobile-menu-btn" id="mobileMenuBtn" aria-label="Open menu">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M3 12h18M3 18h18"/></svg>
    </button>
    <span class="mobile-topbar-title"><?= e($pageTitle ?? $config['company']) ?></span>
  </div>

  <main class="content">
