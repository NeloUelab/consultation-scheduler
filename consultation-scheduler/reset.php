<?php
/* B-02 · Reset password with the 6-digit code */
require_once __DIR__ . '/includes/layout.php';

$r = $_SESSION['reset'] ?? null;
if (!$r) {
    redirect('forgot.php');
}
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $code = preg_replace('/\D/', '', (string) ($_POST['code'] ?? ''));
    $p1 = (string) ($_POST['password'] ?? '');
    $p2 = (string) ($_POST['password2'] ?? '');
    if (strlen($code) !== 6) { $errors['code'] = 'Enter the 6-digit code.'; }
    if (strlen($p1) < 8) { $errors['password'] = 'Use at least 8 characters.'; }
    elseif ($p1 !== $p2) { $errors['password2'] = 'The passwords do not match.'; }

    if (!$errors) {
        $st = db()->prepare('SELECT * FROM password_reset WHERE user_email = ? AND user_role = ? AND used = 0 AND expires_at >= ? ORDER BY reset_id DESC LIMIT 1');
        $st->execute([$r['email'], $r['role'], now_str()]);
        $row = $st->fetch();
        if (!$row || !password_verify($code, $row['code_hash'])) {
            $errors['code'] = 'That code is wrong or has expired. Request a new one.';
        } else {
            $hash = password_hash($p1, PASSWORD_BCRYPT);
            $sql = $r['role'] === 'student' ? 'UPDATE student SET password_hash = ? WHERE student_email = ?' : 'UPDATE instructor SET password_hash = ? WHERE instructor_email = ?';
            db()->prepare($sql)->execute([$hash, $r['email']]);
            db()->prepare('UPDATE password_reset SET used = 1 WHERE reset_id = ?')->execute([$row['reset_id']]);
            unset($_SESSION['reset']);
            $_SESSION['login_banner'] = 'Password updated. Sign in with your new password.';
            redirect('login.php?role=' . $r['role']);
        }
    }
}
$masked = preg_replace('/^(.).*(@.*)$/', '$1•••$2', $r['email']);

page_start('Reset password', ['center' => true, 'sub' => 'Enter the 6-digit code we sent and choose a new password.', 'screen' => 'Auth 2. Reset password']);
?>
<form method="post" class="sheet form" novalidate>
  <?= csrf_field() ?>
  <div class="alert alert-info"><?= icon('mail', 18) ?><span>If <b><?= h($masked) ?></b> is registered, we sent a 6-digit code. It expires in 15 minutes.</span></div>
  <?php if (!empty($r['demo_code'])): ?>
    <div class="demo-mail" aria-label="Prototype mailbox">
      <span class="label">Prototype mailbox (no mail server on XAMPP)</span>
      <span>Your reset code is <b class="code"><?= h($r['demo_code']) ?></b></span>
    </div>
  <?php endif; ?>
  <label class="field<?= isset($errors['code']) ? ' has-error' : '' ?>">
    <span class="label">Reset code</span>
    <span class="input-wrap"><span class="input-ic"><?= icon('mail', 20) ?></span>
      <input class="input" type="text" name="code" inputmode="numeric" maxlength="6" placeholder="Enter 6-digit code" autocomplete="one-time-code"></span>
    <span class="field-msg"><?= h($errors['code'] ?? '') ?></span>
  </label>
  <label class="field<?= isset($errors['password']) ? ' has-error' : '' ?>">
    <span class="label">New password</span>
    <span class="input-wrap"><span class="input-ic"><?= icon('lock', 20) ?></span>
      <input class="input" type="password" name="password" id="np1" placeholder="At least 8 characters" autocomplete="new-password">
      <button type="button" class="input-btn" data-toggle-pw="np1" aria-label="Show password"><?= icon('eye', 20) ?></button></span>
    <span class="field-msg"><?= h($errors['password'] ?? '') ?></span>
  </label>
  <label class="field<?= isset($errors['password2']) ? ' has-error' : '' ?>">
    <span class="label">Confirm new password</span>
    <span class="input-wrap"><span class="input-ic"><?= icon('lock', 20) ?></span>
      <input class="input" type="password" name="password2" id="np2" placeholder="Re-enter new password" autocomplete="new-password">
      <button type="button" class="input-btn" data-toggle-pw="np2" aria-label="Show password"><?= icon('eye', 20) ?></button></span>
    <span class="field-msg"><?= h($errors['password2'] ?? '') ?></span>
  </label>
  <button class="btn btn-primary btn-block" type="submit" data-busy-text="Saving…">Reset password</button>
  <div class="link-row center"><a class="link" href="<?= h(url('forgot.php?role=' . $r['role'])) ?>">Send a new code</a> · <a class="link" href="<?= h(url('login.php')) ?>">Back to sign in</a></div>
</form>
<?php page_end(); ?>
