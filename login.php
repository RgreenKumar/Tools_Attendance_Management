<?php
require __DIR__ . '/bootstrap.php';

use App\Auth;

$auth = new Auth();
if (!$auth->isRegistered()) {
    redirect('register.php'); // very first run: create the admin account
}
if (Auth::isLoggedIn()) {
    redirect(Auth::home());
}

if (($_GET['tab'] ?? '') === 'register') {
    redirect('login.php'); // self-registration was removed: employees are added by the admin
}
$as     = ($_GET['as'] ?? '') === 'admin' ? 'admin' : 'employee';
$error  = $_SESSION['error'] ?? null;
$notice = $_SESSION['notice'] ?? null;
$old    = $_SESSION['old'] ?? [];
unset($_SESSION['error'], $_SESSION['notice'], $_SESSION['old']);

$authTitle = 'Sign in';
require __DIR__ . '/partials/auth_head.php';

$iconUser = '<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>';
$iconLock = '<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="10" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>';
$iconMail = '<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>';
$iconId   = '<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="11" r="2"/><path d="M15 9h3M15 13h3M6 17c.5-2 5.5-2 6 0"/></svg>';
?>
    <?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
    <?php if ($notice): ?><div class="alert ok"><?= e($notice) ?></div><?php endif; ?>

  <?php if ($as === 'employee'): ?>
    <h1>Employee login</h1>
    <p class="lead">Sign in with your email and password. Don't have a password? Ask your admin.</p>
    <form method="post" action="user_login_submit.php">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <label for="email">Email</label>
      <div class="input-icon"><?= $iconMail ?>
        <input type="email" id="email" name="email" required autofocus autocomplete="email" value="<?= e($old['email'] ?? '') ?>">
      </div>
      <label for="password">Password</label>
      <div class="input-icon"><?= $iconLock ?>
        <input type="password" id="password" name="password" required autocomplete="current-password">
      </div>
      <button type="submit" class="auth-submit">Sign in</button>
    </form>
    <p class="auth-switch"><a href="login.php?as=admin">Admin login</a></p>

  <?php else: ?>
    <h1>Admin login</h1>
    <p class="lead">Sign in with your admin email or username and password.</p>
    <form method="post" action="login_submit.php">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <label for="username">Email or username</label>
      <div class="input-icon"><?= $iconUser ?>
        <input type="text" id="username" name="username" required autofocus autocomplete="username">
      </div>
      <label for="password">Password</label>
      <div class="input-icon"><?= $iconLock ?>
        <input type="password" id="password" name="password" required autocomplete="current-password">
      </div>
      <button type="submit" class="auth-submit">Sign in</button>
    </form>
    <p class="auth-switch"><a href="login.php">&larr; Employee login</a></p>

  <?php endif; ?>
<?php require __DIR__ . '/partials/auth_foot.php'; ?>
