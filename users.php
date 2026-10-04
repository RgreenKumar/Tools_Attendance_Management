<?php
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/src/guard.php';

$users  = $auth->listUsers();
$notice = $_SESSION['notice'] ?? null;
unset($_SESSION['notice']);

$pageTitle = 'Employee logins';
require __DIR__ . '/partials/header.php';
?>
  <div class="page-head">
    <div>
      <h1>Employee logins</h1>
      <p class="lead">Employees who can sign in to see their own hours. Logins are created automatically when you save an employee with an email and password on the Employees page. They only ever see their own individual report.</p>
    </div>
  </div>

  <?php if ($notice): ?><div class="alert ok"><?= e($notice) ?></div><?php endif; ?>

  <?php if (!$users): ?>
    <div class="panel"><p class="muted" style="margin:0">No employee logins yet. Add an email and a password to an employee on the Employees page.</p></div>
  <?php else: ?>
    <div class="table-scroll">
      <table>
        <thead><tr><th style="width:90px">ID</th><th>Name</th><th>Department</th><th>Email</th><th>Created</th><th style="width:110px">Actions</th></tr></thead>
        <tbody>
        <?php foreach ($users as $u): ?>
          <tr>
            <td><?= e((string) $u['emp_id']) ?></td>
            <td><?= e((string) ($u['name'] ?? '')) ?: '<span class="muted">—</span>' ?></td>
            <td><?= e((string) ($u['dept'] ?? '')) ?: '<span class="muted">—</span>' ?></td>
            <td><?= e((string) $u['email']) ?></td>
            <td><?= e(date('d M Y, H:i', strtotime((string) $u['created_at']))) ?></td>
            <td class="row-actions">
              <form action="users_delete.php" method="post" onsubmit="return confirm('Remove login for <?= e((string) $u['emp_id']) ?>?');">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                <button type="submit" class="link danger">Remove</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
<?php require __DIR__ . '/partials/footer.php'; ?>
