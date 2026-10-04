<?php
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/src/guard.php';

use App\MailSettings;

$settings = (new MailSettings())->get();

$notice = $_SESSION['notice'] ?? null;
$error  = $_SESSION['error'] ?? null;
unset($_SESSION['notice'], $_SESSION['error']);

$pageTitle = 'Mail Settings';
require __DIR__ . '/partials/header.php';
?>
  <h1>Mail settings</h1>
  <p class="lead">
    The SMTP details used to send every attendance report. For Gmail, turn on 2-Step Verification and create an
    <a href="https://myaccount.google.com/apppasswords" target="_blank" rel="noopener">App Password</a> — your normal
    Gmail password will not work here.
  </p>

  <?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
  <?php if ($notice): ?><div class="alert ok"><?= e($notice) ?></div><?php endif; ?>

  <form class="panel" action="settings_save.php" method="post">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

    <label for="host">SMTP host</label>
    <input type="text" id="host" name="host" value="<?= e($settings['host']) ?>" required placeholder="smtp.gmail.com">

    <label for="port">Port</label>
    <input type="number" id="port" name="port" value="<?= e((string) $settings['port']) ?>" required min="1" max="65535">

    <label for="encryption">Encryption</label>
    <select id="encryption" name="encryption">
      <option value="tls" <?= $settings['encryption'] === 'tls' ? 'selected' : '' ?>>TLS (port 587)</option>
      <option value="ssl" <?= $settings['encryption'] === 'ssl' ? 'selected' : '' ?>>SSL (port 465)</option>
    </select>

    <label for="username">SMTP username</label>
    <input type="text" id="username" name="username" value="<?= e($settings['username']) ?>" required placeholder="hr@rgreentechnologies.com">

    <label for="password">SMTP password</label>
    <input type="password" id="password" name="password" placeholder="<?= $settings['password'] !== '' ? '•••••••• (leave blank to keep it)' : 'App password' ?>" autocomplete="new-password">
    <p class="hint">Leave this blank to keep the password already saved.</p>

    <label for="from_email">From email</label>
    <input type="email" id="from_email" name="from_email" value="<?= e($settings['from_email']) ?>" required placeholder="hr@rgreentechnologies.com">

    <label for="from_name">From name</label>
    <input type="text" id="from_name" name="from_name" value="<?= e($settings['from_name']) ?>" placeholder="RGreen Technologies HR">

    <div class="actions"><button type="submit">Save settings</button></div>
  </form>

  <h2>Test the connection</h2>
  <form class="panel" action="settings_test.php" method="post" data-loading="Sending test email…">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <label for="test_email">Send a test email to</label>
    <input type="email" id="test_email" name="test_email" required placeholder="you@example.com" value="<?= e($settings['username']) ?>">
    <div class="actions"><button type="submit" class="btn secondary">Send test email</button></div>
  </form>
<?php require __DIR__ . '/partials/footer.php'; ?>
