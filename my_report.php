<?php
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/src/user_guard.php';

// A normal user only ever sees their OWN employee ID — it comes from the session, never from the URL.
$empId = (string) $_SESSION['emp_id'];
$store = new App\ReportStore();
$weeks = $store->forEmployee($empId);

$selected = (int) ($_GET['report'] ?? ($weeks[0]['report_id'] ?? 0));
$entry    = $selected > 0 ? $store->entry($selected, $empId) : null;

$pageTitle = 'My hours';
require __DIR__ . '/partials/header.php';
?>
  <div class="page-head no-print">
    <div>
      <h1>My hours</h1>
      <p class="lead">Your working hours for each day, one report per uploaded week.</p>
    </div>
    <?php if ($entry): ?>
      <div class="head-actions"><button type="button" class="btn secondary" onclick="window.print()">Print / Save as PDF</button></div>
    <?php endif; ?>
  </div>

  <?php if (!$weeks): ?>
    <div class="panel"><p class="muted" style="margin:0">No attendance has been recorded for you yet. Your hours will appear here after your admin uploads the weekly file.</p></div>
  <?php else: ?>
    <form method="get" class="week-picker no-print">
      <label for="report">Week</label>
      <select id="report" name="report" onchange="this.form.submit()">
        <?php foreach ($weeks as $w): ?>
          <option value="<?= (int) $w['report_id'] ?>" <?= (int) $w['report_id'] === $selected ? 'selected' : '' ?>>
            <?= e($w['week_label']) ?> — <?= e(fmt_minutes((int) $w['minutes'])) ?><?= $w['low'] ? ' (below)' : ((int) $w['minutes'] < (int) $w['required'] ? ' (grace)' : '') ?>
          </option>
        <?php endforeach; ?>
      </select>
      <noscript><button type="submit" class="btn secondary">Show</button></noscript>
    </form>

    <?php if ($entry): ?>
      <?php require __DIR__ . '/partials/individual_report.php'; ?>
    <?php else: ?>
      <div class="alert error">That report isn't available.</div>
    <?php endif; ?>
  <?php endif; ?>
<?php require __DIR__ . '/partials/footer.php'; ?>
