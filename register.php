<?php
require __DIR__ . '/bootstrap.php';

use App\Auth;

$auth = new Auth();
if ($auth->isRegistered()) {
    redirect('login.php');
}

$error = $_SESSION['error'] ?? null;
unset($_SESSION['error']);
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
  <title>Create admin account · <?= e($config['company']) ?></title>
  <link rel="stylesheet" href="assets/style.css">
</head>
<body class="auth-body">
  <div class="auth-card">
    <div class="auth-brand">
      <span class="auth-mark">🌿</span>
      <span><?= e($config['company']) ?></span>
    </div>
    <h1>Create the admin account</h1>
    <p class="lead">One-time setup. You'll sign in with this email and your password.</p>

    <?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>

    <form method="post" action="register_submit.php">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <label for="username">Admin email</label>
      <div class="input-icon">
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
        <input type="email" id="username" name="username" required autofocus autocomplete="email">
      </div>
      <label for="password">Password</label>
      <div class="input-icon">
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="10" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
        <input type="password" id="password" name="password" required minlength="8" autocomplete="new-password">
      </div>
      <label for="password2">Confirm password</label>
      <div class="input-icon">
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="10" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
        <input type="password" id="password2" name="password2" required minlength="8" autocomplete="new-password">
      </div>
      <p class="hint">At least 8 characters.</p>
      <button type="submit" class="auth-submit">Create account</button>
    </form>
  </div>
</body>
</html>
