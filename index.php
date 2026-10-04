<?php
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/src/guard.php';

use App\MailSettings;

$error = $_SESSION['error'] ?? null;
$notice = $_SESSION['notice'] ?? null;
unset($_SESSION['error'], $_SESSION['notice']);

$mailReady = (new MailSettings())->isConfigured();

$pageTitle = 'Weekly upload';
require __DIR__ . '/partials/header.php';
?>
  <h1>Weekly attendance upload</h1>
  <p class="lead">Upload this week's attendance export. The week period is read straight from the real calendar dates in the file — no manual label needed. The system totals each employee's hours and automatically emails every employee their own report — a formal alert if they're short, a formal confirmation if they've met it.</p>

  <?php if (!$mailReady): ?>
    <div class="alert warn">Email isn't set up yet, so reports won't be sent until you do. <a href="settings.php">Configure Mail Settings</a> first.</div>
  <?php endif; ?>
  <?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
  <?php if ($notice): ?><div class="alert ok"><?= e($notice) ?></div><?php endif; ?>

  <form class="panel" action="upload.php" method="post" enctype="multipart/form-data"
        data-loading="Calculating hours and sending reports — this can take a moment…">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

    <label for="required_hours">Required hours for the week</label>
    <input type="number" id="required_hours" name="required_hours" min="1" max="168" step="0.5"
           value="<?= e((string) $config['required_weekly_hours']) ?>" required>

    <label for="working_days">Working days in the week</label>
    <input type="number" id="working_days" name="working_days" min="1" max="7" step="1"
           value="<?= e((string) $config['working_days_per_week']) ?>" required inputmode="numeric">
    <p class="hint" id="perDayHint">Each day's target is the weekly hours divided by the working days (for example 45h &divide; 5 days = 9h per day).</p>

    <label for="grace_minutes">Grace time per day, in minutes <span class="muted">(optional, max 60)</span></label>
    <input type="number" id="grace_minutes" name="grace_minutes" min="0" max="60" step="1" placeholder="0" inputmode="numeric">
    <p class="hint">Leave empty for the normal calculation. The grace time is taken off the daily target: with a 9h day and 30 min grace, a day of 8h 30m or more still passes. A day at or above the target is green, a day inside the grace time is <span class="badge grace">orange</span>, and a day below it is red. For the week, the grace is multiplied by the working days (for example 5 days: 45h required, 2h 30m grace, so 42h 30m or more passes).</p>

    <label>Attendance file</label>
    <div class="dropzone" id="dropzone">
      <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M12 3v12m0 0-4-4m4 4 4-4M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/></svg>
      <p class="dropzone-title" id="dropzoneFile">Drag &amp; drop your file here, or click to browse</p>
      <p class="dropzone-sub">.xlsx, .xls or .csv &middot; up to <?= e((string) $config['max_upload_mb']) ?> MB</p>
      <input type="file" id="attendance" name="attendance" accept=".xlsx,.xls,.csv" required class="visually-hidden-file">
    </div>
    <p class="hint">
      Works with a biometric device weekly export (Time Card / Before Noon / After Noon / Overtime punches),
      or a simple sheet with columns <code>Emp ID</code>, <code>Name</code>, <code>Email</code>, <code>Date</code>, <code>In Time</code>, <code>Out Time</code>.
      Device exports have no email column — those are filled in on the <a href="employees.php">Employees</a> page.
    </p>

    <div class="actions">
      <button type="submit">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 2 11 13M22 2l-7 20-4-9-9-4 20-7Z"/></svg>
        Calculate hours
      </button>
    </div>
  </form>
<script>
  // Live "per day" preview under the working-days box.
  (function () {
    var h = document.getElementById('required_hours'), d = document.getElementById('working_days'),
        out = document.getElementById('perDayHint');
    if (!h || !d || !out) return;
    function fmt(mins) { return Math.floor(mins / 60) + 'h ' + ('0' + (mins % 60)).slice(-2) + 'm'; }
    function update() {
      var hours = parseFloat(h.value), days = parseInt(d.value, 10);
      if (hours > 0 && days > 0) {
        out.textContent = 'Each day\'s target = ' + hours + 'h \u00f7 ' + days + ' working days = ' +
          fmt(Math.round(hours * 60 / days)) + ' per day.';
      }
    }
    h.addEventListener('input', update); d.addEventListener('input', update); update();
  })();
</script>
<?php require __DIR__ . '/partials/footer.php'; ?>
