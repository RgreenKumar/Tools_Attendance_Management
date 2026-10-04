<?php
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/src/guard.php';

use App\EmployeeDirectory;

$directory = new EmployeeDirectory();

$q   = trim((string) ($_GET['q'] ?? ''));
$all = $directory->search($q);

$notice = $_SESSION['notice'] ?? null;
$error  = $_SESSION['error'] ?? null;
unset($_SESSION['notice'], $_SESSION['error']);

$logins  = $directory->loginStatus();
$missing = count(array_filter($directory->all(), fn($e) => trim((string) ($e['email'] ?? '')) === ''));

$pageTitle = 'Employees';
require __DIR__ . '/partials/header.php';
?>
  <div class="page-head">
    <div>
      <h1>Employee directory</h1>
      <p class="lead">
        The employee ID and email address used for weekly reports. New IDs are added automatically the first
        time they appear in an upload — add them here yourself any time, or fix an email that bounced.
        Give an employee an email + password and their login is created automatically (no registering).
      </p>
    </div>
    <a class="btn" href="employees_edit.php">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M12 5v14M5 12h14"/></svg>
      Add employee
    </a>
  </div>

  <?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
  <?php if ($notice): ?><div class="alert ok"><?= e($notice) ?></div><?php endif; ?>
  <?php if ($missing > 0 && $q === ''): ?>
    <div class="alert warn"><?= $missing ?> employee<?= $missing === 1 ? '' : 's' ?> still need<?= $missing === 1 ? 's' : '' ?> an email address.</div>
  <?php endif; ?>

  <form class="search-bar" action="employees.php" method="get">
    <input type="text" name="q" value="<?= e($q) ?>" placeholder="Search by employee ID or name…">
    <button type="submit" class="btn secondary">Search</button>
    <?php if ($q !== ''): ?><a class="btn secondary" href="employees.php">Clear</a><?php endif; ?>
  </form>

  <?php if (!$all): ?>
    <div class="panel">
      <p class="muted" style="margin:0">
        <?= $q !== '' ? 'No employee matches "' . e($q) . '".' : 'No employees yet. Add one, or upload a weekly attendance file — employees found in it will appear here.' ?>
      </p>
    </div>
  <?php else: ?>
    <div class="table-scroll">
      <table>
        <thead><tr><th style="width:90px">ID</th><th>Name</th><th>Department</th><th>Email</th><th>Login</th><th style="width:150px">Actions</th></tr></thead>
        <tbody>
        <?php foreach ($all as $id => $emp): ?>
          <tr>
            <td><?= e((string) $id) ?></td>
            <td><?= e($emp['name'] ?? '') ?: '<span class="muted">—</span>' ?></td>
            <td><?= e($emp['dept'] ?? '') ?: '<span class="muted">—</span>' ?></td>
            <td>
              <?php if (trim((string) ($emp['email'] ?? '')) !== ''): ?>
                <?= e($emp['email']) ?>
              <?php else: ?>
                <span class="badge warn">No email</span>
              <?php endif; ?>
            </td>
            <td><?= isset($logins[(string) $id]) ? '<span class="badge ok">Active</span>' : '<span class="badge warn">No login</span>' ?></td>
            <td class="row-actions">
              <a class="link" href="employees_edit.php?id=<?= urlencode((string) $id) ?>">Edit</a>
              <form action="employees_delete.php" method="post" onsubmit="return confirm('Delete employee <?= e((string) $id) ?>?');">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="id" value="<?= e((string) $id) ?>">
                <button type="submit" class="link danger">Delete</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
<?php require __DIR__ . '/partials/footer.php'; ?>
