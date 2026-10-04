<?php
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/src/user_guard.php';

use App\Auth;
use App\EmployeeDirectory;

// An employee only ever edits their OWN account — the employee ID comes from the session, never the URL.
$empId   = (string) $_SESSION['emp_id'];
$profile = (new EmployeeDirectory())->find($empId) ?? ['name' => '', 'dept' => '', 'email' => (string) Auth::currentUser()];

$notice = $_SESSION['notice'] ?? null;
$error  = null;
unset($_SESSION['notice']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    // Slow down guessing of the current password: 5 wrong tries -> wait 5 minutes.
    $tries = $_SESSION['pw_fail'] ?? ['n' => 0, 'at' => 0];
    if ($tries['n'] >= 5 && time() - (int) $tries['at'] >= 300) {
        $tries = ['n' => 0, 'at' => 0];
    }

    if ($tries['n'] >= 5) {
        $error = 'Too many wrong attempts. Please wait a few minutes and try again.';
    } else {
        try {
            $auth->changeUserPassword(
                $empId,
                (string) ($_POST['current_password'] ?? ''),
                (string) ($_POST['new_password'] ?? ''),
                (string) ($_POST['confirm_password'] ?? '')
            );
            unset($_SESSION['pw_fail']);
            session_regenerate_id(true);
            $_SESSION['notice'] = 'Your password has been changed.';
            redirect('my_profile.php');
        } catch (RuntimeException $ex) {
            $error = $ex->getMessage();
            if (str_contains($error, 'current password')) {
                $_SESSION['pw_fail'] = ['n' => $tries['n'] + 1, 'at' => time()];
            }
        }
    }
}

$pageTitle = 'My profile';
require __DIR__ . '/partials/header.php';
?>
  <div class="page-head">
    <div>
      <h1>My profile</h1>
      <p class="lead">Your account details and password.</p>
    </div>
  </div>

  <?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
  <?php if ($notice): ?><div class="alert ok"><?= e($notice) ?></div><?php endif; ?>

  <div class="panel" style="max-width:560px">
    <h2 style="margin-top:0">Account details</h2>
    <table class="profile-details">
      <tr><th>Name</th><td><?= e($profile['name']) ?: '<span class="muted">—</span>' ?></td></tr>
      <tr><th>Employee ID</th><td><?= e($empId) ?></td></tr>
      <tr><th>Department</th><td><?= e($profile['dept']) ?: '<span class="muted">—</span>' ?></td></tr>
      <tr><th>Email (sign-in)</th><td><?= e($profile['email']) ?></td></tr>
    </table>
    <p class="hint">To change your name, department or email, please ask your admin.</p>
  </div>

  <form class="panel" method="post" action="my_profile.php" style="max-width:560px" autocomplete="off">
    <h2 style="margin-top:0">Change password</h2>
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

    <label for="current_password">Current password</label>
    <input type="password" id="current_password" name="current_password" required autocomplete="current-password">

    <label for="new_password">New password</label>
    <input type="password" id="new_password" name="new_password" required minlength="8" autocomplete="new-password">

    <label for="confirm_password">Confirm new password</label>
    <input type="password" id="confirm_password" name="confirm_password" required minlength="8" autocomplete="new-password">
    <p class="hint">At least 8 characters.</p>

    <div class="actions">
      <button type="submit">Change password</button>
    </div>
  </form>
<?php require __DIR__ . '/partials/footer.php'; ?>
