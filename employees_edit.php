<?php
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/src/guard.php';

use App\EmployeeDirectory;

$directory = new EmployeeDirectory();

$id       = trim((string) ($_GET['id'] ?? $_POST['id'] ?? ''));
$existing = $id !== '' ? $directory->find($id) : null;
$isEdit   = $existing !== null;
$hasLogin = $isEdit && isset($directory->loginStatus()[$id]);

$error = null;
$form  = [
    'id'    => $id,
    'email' => $existing['email'] ?? '',
    'name'  => $existing['name'] ?? '',
    'dept'  => $existing['dept'] ?? '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $form = [
        'id'    => trim((string) ($_POST['id'] ?? '')),
        'email' => trim((string) ($_POST['email'] ?? '')),
        'name'  => trim((string) ($_POST['name'] ?? '')),
        'dept'  => trim((string) ($_POST['dept'] ?? '')),
    ];
    $mode     = (string) ($_POST['mode'] ?? 'create');
    $password = (string) ($_POST['password'] ?? '');

    if ($form['id'] === '') {
        $error = 'Employee ID is required.';
    } elseif ($form['email'] !== '' && !filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
        $error = 'Enter a valid email address, or leave it blank.';
    } else {
        try {
            if ($mode === 'update') {
                $directory->update($form['id'], $form['name'], $form['dept'], $form['email'], $password);
            } else {
                $directory->add($form['id'], $form['name'], $form['dept'], $form['email'], $password);
            }
            $_SESSION['notice'] = 'Employee saved.';
            redirect('employees.php');
        } catch (Throwable $ex) {
            $error = $ex->getMessage();
        }
    }
    $isEdit = $mode === 'update';
}

$pageTitle = $isEdit ? 'Edit employee' : 'Add employee';
require __DIR__ . '/partials/header.php';
?>
  <h1><?= $isEdit ? 'Edit employee' : 'Add employee' ?></h1>
  <p class="lead">The employee ID must match the ID used in your attendance file.</p>

  <?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>

  <form class="panel" method="post" action="employees_edit.php">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="mode" value="<?= $isEdit ? 'update' : 'create' ?>">

    <label for="id">Employee ID</label>
    <input type="text" id="id" name="id" value="<?= e($form['id']) ?>" required autofocus
           <?= $isEdit ? 'readonly' : '' ?>>

    <label for="email">Email</label>
    <input type="email" id="email" name="email" value="<?= e($form['email']) ?>" placeholder="name@company.com">

    <label for="password">Login password <span class="muted">(<?= $hasLogin ? 'leave blank to keep the current one' : 'min 8 characters' ?>)</span></label>
    <input type="password" id="password" name="password" minlength="8" autocomplete="new-password">
    <p class="hint">With an email and a password, this employee gets a login automatically and signs in with them
      (no registration needed). Tell the employee the password yourself.
      <?= $hasLogin ? '<br><span class="badge ok">Login active</span>' : '<br><span class="badge warn">No login yet</span>' ?></p>

    <label for="name">Name <span class="muted">(optional)</span></label>
    <input type="text" id="name" name="name" value="<?= e($form['name']) ?>">

    <label for="dept">Department <span class="muted">(optional)</span></label>
    <input type="text" id="dept" name="dept" value="<?= e($form['dept']) ?>">

    <div class="actions">
      <button type="submit"><?= $isEdit ? 'Save changes' : 'Add employee' ?></button>
      <a class="btn secondary" href="employees.php">Cancel</a>
    </div>
  </form>
<?php require __DIR__ . '/partials/footer.php'; ?>
