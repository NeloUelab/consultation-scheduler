<?php
/* Account Settings (both roles) — profile, change password, logout */
require_once __DIR__ . '/includes/layout.php';
$u = current_user();
if (!$u) {
    redirect('login.php');
}
$table = $u['role'] === 'student' ? 'student' : 'instructor';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $cur = (string) ($_POST['current'] ?? '');
    $new = (string) ($_POST['new'] ?? '');
    $conf = (string) ($_POST['confirm'] ?? '');
    $st = db()->prepare("SELECT password_hash FROM $table WHERE {$table}_id = ?");
    $st->execute([(int) $u['id']]);
    if (!password_verify($cur, (string) $st->fetchColumn())) {
        $errors[] = 'Your current password is incorrect.';
    }
    if (strlen($new) < 8) {
        $errors[] = 'The new password must be at least 8 characters.';
    } elseif ($new !== $conf) {
        $errors[] = 'The new passwords do not match.';
    }
    if (!$errors) {
        db()->prepare("UPDATE $table SET password_hash = ? WHERE {$table}_id = ?")->execute([password_hash($new, PASSWORD_DEFAULT), (int) $u['id']]);
        flash('success', 'Password updated');
        redirect('settings.php');
    }
}
page_start('Account Settings', ['back' => url($u['role'] . '/dashboard.php'), 'screen' => 'Account settings']);
?>
<section class="card profile">
  <span class="avatar avatar-lg" aria-hidden="true"><?= h(initial($u['name'])) ?></span>
  <div><b class="h3"><?= h($u['name']) ?></b><span class="muted small"><?= h($u['email']) ?> · <?= h(ucfirst($u['role'])) ?></span></div>
</section>

<form method="post" class="card form" novalidate>
  <?= csrf_field() ?>
  <h2 class="label">Change password</h2>
  <?php if ($errors): ?><div class="alert alert-error" role="alert"><?= icon('alert', 18) ?><span><?= h(implode(' ', $errors)) ?></span></div><?php endif; ?>
  <label class="field"><span class="label">Current password</span><input class="input" type="password" name="current" autocomplete="current-password" required></label>
  <label class="field"><span class="label">New password</span><input class="input" type="password" name="new" minlength="8" autocomplete="new-password" required></label>
  <label class="field"><span class="label">Confirm new password</span><input class="input" type="password" name="confirm" autocomplete="new-password" required></label>
  <button class="btn btn-primary btn-block" type="submit">Save password</button>
</form>

<form method="post" action="<?= h(url('logout.php')) ?>">
  <?= csrf_field() ?>
  <button class="btn btn-cancel btn-block" type="submit"><?= icon('logout', 18) ?> Logout</button>
</form>
<?php page_end(); ?>
