<?php
/**
 * One employee's report for one period, day by day.
 * Expects: $entry = ['report' => [...], 'emp' => [...]] (from ReportStore::entry()).
 */
$emp    = $entry['emp'];
$rep    = $entry['report'];
$daily  = $emp['daily'];
$hasInOut = (bool) array_filter($daily, fn($d) => ($d['in'] ?? '') !== '' || ($d['out'] ?? '') !== '');
$worked = $emp['days'] > 0 ? intdiv($emp['minutes'], $emp['days']) : 0;
$diff   = $emp['minutes'] - $emp['required'];
[$dailyReq, $graceDay] = day_targets($emp);
$status   = emp_status($emp);
$periodDays = (int) ($emp['period_days'] ?? 0);
?>
  <div class="panel individual">
    <div class="individual-head">
      <div>
        <h2 style="margin:0"><?= e($emp['name']) ?: '(no name)' ?></h2>
        <p class="muted small" style="margin:2px 0 0">
          ID <?= e($emp['emp_id']) ?><?= !empty($emp['dept']) ? ' · ' . e($emp['dept']) : '' ?> · <?= e($rep['week_label']) ?>
        </p>
      </div>
      <span class="badge <?= ['low' => 'low', 'grace' => 'grace', 'ok' => 'ok'][$status] ?>"><?= ['low' => 'Below requirement', 'grace' => 'Met with grace time', 'ok' => 'Requirement met'][$status] ?></span>
    </div>

    <div class="stats">
      <div class="stat"><b><?= e(fmt_minutes($emp['minutes'])) ?></b><span>Total hours</span></div>
      <div class="stat"><b><?= e(fmt_minutes($emp['required'])) ?></b><span>Required<?= $periodDays > 0 ? ' (' . e(fmt_minutes($dailyReq)) . ' &times; ' . $periodDays . ' working days)' : '' ?></span></div>
      <div class="stat <?= ['low' => 'low-stat', 'grace' => 'grace-stat', 'ok' => 'ok-stat'][$status] ?>">
        <b><?= $diff < 0 ? '−' . e(fmt_minutes(-$diff)) : '+' . e(fmt_minutes($diff)) ?></b>
        <span><?= $diff < 0 ? 'Short by' : 'Above requirement' ?></span>
      </div>
      <div class="stat"><b><?= (int) $emp['days'] ?></b><span>Days worked</span></div>
      <div class="stat"><b><?= e(fmt_minutes($worked)) ?></b><span>Average per day worked</span></div>
    </div>

    <?php if ((int) ($emp['grace_minutes'] ?? 0) > 0): ?>
      <div class="alert <?= $status === 'grace' ? 'grace' : 'warn' ?>" style="margin-top:14px">
        Grace time allowed: <strong><?= $graceDay ?> min per day</strong> (<?= e(fmt_minutes(weekly_grace($graceDay, $periodDays))) ?> over <?= $periodDays > 0 ? $periodDays . ' working days' : 'the week' ?>).
        <?php if ($status === 'grace'): ?>
          Total hours are short by <?= e(fmt_minutes($emp['shortfall'])) ?>, which is within the grace allowance, so the requirement is counted as met.
        <?php elseif ($status === 'low'): ?>
          Total hours are short by <?= e(fmt_minutes($emp['shortfall'])) ?>, which is more than the grace allowance.
        <?php else: ?>
          Not needed: the requirement was met in full.
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <h3 style="margin:18px 0 4px">Hours per day</h3>
    <p class="muted small" style="margin:0 0 8px">Daily target <?= e(fmt_minutes($dailyReq)) ?> &middot; <span class="badge ok">Green</span> met<?php if ($graceDay > 0): ?> &middot; <span class="badge grace">Orange</span> within grace (<?= e(fmt_minutes($dailyReq - $graceDay)) ?> or more)<?php endif; ?> &middot; <span class="badge low">Red</span> below</p>
    <div class="table-scroll">
      <table class="day-table">
        <thead>
          <tr>
            <th>Date</th>
            <?php if ($hasInOut): ?><th>In</th><th>Out</th><?php endif; ?>
            <th>Hours worked</th>
            <th>Note</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($daily as $d): ?>
          <tr class="<?= $d['minutes'] === 0 ? 'day-zero' : day_status((int) $d['minutes'], $dailyReq, $graceDay) ?>">
            <td><?= e($d['date']) ?></td>
            <?php if ($hasInOut): ?><td><?= e($d['in'] ?? '') ?: '–' ?></td><td><?= e($d['out'] ?? '') ?: '–' ?></td><?php endif; ?>
            <td><strong><?= e(fmt_minutes($d['minutes'])) ?></strong></td>
            <td><?= e($d['note']) ?: '<span class="muted">Present</span>' ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr>
            <td colspan="<?= $hasInOut ? 3 : 1 ?>"><strong>Total</strong></td>
            <td><strong><?= e(fmt_minutes($emp['minutes'])) ?></strong></td>
            <td></td>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>
