<?php
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/src/guard.php';

$reportId = (int) ($_GET['id'] ?? $_SESSION['report_id'] ?? 0);
$report   = $reportId > 0 ? (new App\ReportStore())->find($reportId) : null;
if (!$report) {
    redirect('index.php');
}
$notice = $_SESSION['notice'] ?? null;
$error  = $_SESSION['error'] ?? null;
unset($_SESSION['notice'], $_SESSION['error']);

$emps     = $report['employees'];
$lowCount   = count(array_filter($emps, fn($e) => $e['low']));
$graceCount = count(array_filter($emps, fn($e) => !empty($e['graced'])));
$graceMin   = (int) ($report['grace_minutes'] ?? 0);
$mailLog  = $report['mail_log'] ?? [];

$sentCount    = count(array_filter($mailLog, fn($m) => $m['status'] === 'sent'));
$failedCount  = count(array_filter($mailLog, fn($m) => $m['status'] === 'failed'));
$skippedCount = count(array_filter($mailLog, fn($m) => $m['status'] === 'skipped'));
$needsRetry   = $failedCount + $skippedCount;

$dayCols = day_columns($emps);
[$dailyReq] = day_targets(['daily_required' => $report['daily_minutes'], 'grace_minutes' => $graceMin, 'required' => $report['required_minutes']]);

$pageTitle = 'Hours report';
require __DIR__ . '/partials/header.php';
?>
  <div class="page-head">
    <div>
      <h1>Hours report: <?= e($report['week_label']) ?></h1>
      <p class="lead">From <?= e($report['file_name']) ?> &middot; required <?= (int) $report['daily_minutes'] > 0 ? e(fmt_minutes((int) $report['daily_minutes'])) . ' per day &times; ' . (int) $report['period_days'] . ' working days = ' : '' ?><?= e(fmt_minutes($report['required_minutes'])) ?> in total<?= $graceMin > 0 ? ', grace time ' . $graceMin . ' min per day' : '' ?>. Reports were emailed automatically to every employee with an address on file.</p>
    </div>
    <a class="btn secondary" href="report_download.php?id=<?= (int) $report['id'] ?>">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3v12m0 0-4-4m4 4 4-4M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/></svg>
      Download report (.xlsx)
    </a>
  </div>

  <?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
  <?php if ($notice): ?><div class="alert ok"><?= e($notice) ?></div><?php endif; ?>

  <?php if ($report['warnings']): ?>
    <details class="panel" style="padding:14px 18px;margin-bottom:18px">
      <summary><?= count($report['warnings']) ?> data warning<?= count($report['warnings']) === 1 ? '' : 's' ?> (missing punches etc.)</summary>
      <ul class="alert-list" style="margin-top:10px">
        <?php foreach (array_slice($report['warnings'], 0, 30) as $w): ?>
          <li class="alert warn"><?= e($w) ?></li>
        <?php endforeach; ?>
        <?php if (count($report['warnings']) > 30): ?>
          <li class="muted small">…and <?= count($report['warnings']) - 30 ?> more.</li>
        <?php endif; ?>
      </ul>
    </details>
  <?php endif; ?>

  <div class="stats">
    <div class="stat"><b><?= count($emps) ?></b><span>Employees in file</span></div>
    <div class="stat"><b><?= count($emps) - $lowCount - $graceCount ?></b><span>Met the requirement</span></div>
    <?php if ($graceMin > 0): ?>
      <div class="stat grace-stat"><b><?= $graceCount ?></b><span>Met with grace time (<?= $graceMin ?> min/day)</span></div>
    <?php endif; ?>
    <div class="stat low-stat"><b><?= $lowCount ?></b><span>Below the requirement</span></div>
    <div class="stat ok-stat"><b><?= $sentCount ?></b><span>Emails sent</span></div>
    <?php if ($needsRetry > 0): ?>
      <div class="stat warn-stat"><b><?= $needsRetry ?></b><span>Not delivered</span></div>
    <?php endif; ?>
  </div>

  <?php if ($needsRetry > 0): ?>
    <form action="send.php" method="post" style="margin:16px 0" data-loading="Retrying undelivered emails…">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="id" value="<?= (int) $report['id'] ?>">
      <button type="submit" class="btn secondary">Retry <?= $needsRetry ?> undelivered email<?= $needsRetry === 1 ? '' : 's' ?></button>
    </form>
  <?php endif; ?>

  <?php if ($dayCols): ?>
  <h2>Hours per day</h2>
  <p class="muted small" style="margin-top:-6px">Every employee, every day of the period. Click a name for that person's individual report. <span class="badge ok">Green</span> = met the daily target (<?= e(fmt_minutes($dailyReq)) ?>),<?php if ($graceMin > 0): ?> <span class="badge grace">Orange</span> = within the <?= $graceMin ?> min grace time (<?= e(fmt_minutes($dailyReq - $graceMin)) ?> or more),<?php endif; ?> <span class="badge low">Red</span> = below <?= e(fmt_minutes($dailyReq - $graceMin)) ?>.</p>
  <div class="table-scroll">
    <table class="day-matrix">
      <thead>
        <tr>
          <th>Employee</th>
          <?php foreach ($dayCols as $label): ?><th><?= e($label) ?></th><?php endforeach; ?>
          <th>Total</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($emps as $id => $emp): ?>
        <?php
          $byDay = [];
          foreach ($emp['daily'] as $d) { $byDay[day_key($d)] = $d; }
        ?>
        <tr class="<?= ['low' => 'low', 'grace' => 'graced', 'ok' => ''][emp_status($emp)] ?>">
          <td><a class="link" href="employee_report.php?report=<?= (int) $report['id'] ?>&amp;emp=<?= urlencode((string) $id) ?>"><?= e($emp['name']) ?: e($emp['emp_id']) ?></a>
              <br><small class="muted"><?= e($emp['emp_id']) ?></small></td>
          <?php foreach ($dayCols as $key => $label): ?>
            <?php $d = $byDay[$key] ?? null; ?>
            <td class="<?= (!$d || $d['minutes'] === 0) ? 'day-zero' : day_status((int) $d['minutes'], $dailyReq, $graceMin) ?>">
              <?php if (!$d): ?>–
              <?php elseif ($d['minutes'] === 0): ?><span title="<?= e($d['note']) ?>">0h</span>
              <?php else: ?><?= e(fmt_minutes($d['minutes'])) ?><?php endif; ?>
            </td>
          <?php endforeach; ?>
          <td><strong><?= e(fmt_minutes($emp['minutes'])) ?></strong></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

  <h2>Employees</h2>
  <div class="table-scroll">
    <table>
      <thead>
        <tr><th>ID</th><th>Name</th><th>Days worked</th><th>Total hours</th><th>Required</th><th>Short by</th><th>Status</th><th>Email</th></tr>
      </thead>
      <tbody>
      <?php foreach ($emps as $id => $emp): ?>
        <?php $mail = $mailLog[$id] ?? null; ?>
        <tr class="<?= ['low' => 'low', 'grace' => 'graced', 'ok' => ''][emp_status($emp)] ?>">
          <td><?= e($emp['emp_id']) ?></td>
          <td>
            <?= e($emp['name']) ?: '<span class="muted">(no name)</span>' ?>
            <?php if (!empty($emp['dept'])): ?><br><small class="muted"><?= e($emp['dept']) ?></small><?php endif; ?>
            <br><a class="link small" href="employee_report.php?report=<?= (int) $report['id'] ?>&amp;emp=<?= urlencode((string) $id) ?>">Individual report</a>
          </td>
          <td><?= $emp['days'] ?></td>
          <td><?= e(fmt_minutes($emp['minutes'])) ?></td>
          <td><?= e(fmt_minutes($emp['required'])) ?></td>
          <td><?= $emp['shortfall'] > 0 ? e(fmt_minutes($emp['shortfall'])) : '–' ?></td>
          <td><?php $st = emp_status($emp); ?><span class="badge <?= ['low' => 'low', 'grace' => 'grace', 'ok' => 'ok'][$st] ?>"><?= ['low' => 'Below', 'grace' => 'Grace', 'ok' => 'OK'][$st] ?></span></td>
          <td>
            <?php if (!$mail): ?>
              <span class="muted small">—</span>
            <?php elseif ($mail['status'] === 'sent'): ?>
              <span class="badge ok">Sent</span>
            <?php elseif ($mail['status'] === 'failed'): ?>
              <span class="badge low" title="<?= e($mail['detail']) ?>">Failed</span>
            <?php elseif (str_contains($mail['detail'], 'Mail Settings')): ?>
              <span class="badge warn" title="<?= e($mail['detail']) ?>">Email not set up — <a href="settings.php">configure</a></span>
            <?php else: ?>
              <span class="badge warn" title="<?= e($mail['detail']) ?>">No email — <a href="employees_edit.php?id=<?= urlencode((string) $id) ?>">add one</a></span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <div class="actions">
    <a class="btn" href="index.php">Upload another file</a>
  </div>
<?php require __DIR__ . '/partials/footer.php'; ?>
