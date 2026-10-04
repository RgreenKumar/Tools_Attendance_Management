<?php
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/src/guard.php';

use App\ReportStore;

$reports   = (new ReportStore())->all();
$pageTitle = 'History';
require __DIR__ . '/partials/header.php';
?>
  <div class="page-head">
    <div>
      <h1>Report history</h1>
      <p class="lead">Every weekly upload is saved in the database. Open one to review it, download it again, or retry emails that failed.</p>
    </div>
  </div>

  <?php if (!$reports): ?>
    <div class="panel"><p class="muted" style="margin:0">No reports yet. Upload a weekly attendance file and it will appear here.</p></div>
  <?php else: ?>
    <div class="table-scroll">
      <table>
        <thead><tr><th>Period</th><th>Uploaded</th><th>File</th><th>Employees</th><th>Below</th><th>Emails sent</th><th style="width:80px"></th></tr></thead>
        <tbody>
        <?php foreach ($reports as $r): ?>
          <tr>
            <td><strong><?= e($r['week_label']) ?></strong></td>
            <td><?= e(date('d M Y, H:i', strtotime($r['created_at']))) ?></td>
            <td class="muted"><?= e($r['file_name']) ?></td>
            <td><?= (int) $r['employees'] ?></td>
            <td><?= (int) $r['below'] > 0 ? '<span class="badge low">' . (int) $r['below'] . '</span>' : '<span class="badge ok">0</span>' ?></td>
            <td><?= (int) $r['sent'] ?> / <?= (int) $r['employees'] ?></td>
            <td><a class="link" href="report.php?id=<?= (int) $r['id'] ?>">Open</a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
<?php require __DIR__ . '/partials/footer.php'; ?>
