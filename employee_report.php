<?php
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/src/guard.php';

// Admin view of one employee's report for one period.
$reportId = (int) ($_GET['report'] ?? 0);
$empId    = (string) ($_GET['emp'] ?? '');
$entry    = $reportId > 0 && $empId !== '' ? (new App\ReportStore())->entry($reportId, $empId) : null;
if (!$entry) {
    redirect('history.php');
}

$pageTitle = 'Individual report';
require __DIR__ . '/partials/header.php';
?>
  <div class="page-head no-print">
    <div>
      <h1>Individual report</h1>
      <p class="lead"><?= e($entry['emp']['name']) ?> &middot; <?= e($entry['report']['week_label']) ?></p>
    </div>
    <div class="head-actions">
      <button type="button" class="btn secondary" onclick="window.print()">Print / Save as PDF</button>
      <a class="btn secondary" href="report.php?id=<?= (int) $entry['report']['id'] ?>">&larr; Back to full report</a>
    </div>
  </div>

<?php require __DIR__ . '/partials/individual_report.php'; ?>
<?php require __DIR__ . '/partials/footer.php'; ?>
