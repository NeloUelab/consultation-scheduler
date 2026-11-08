<?php
/* B-02 · Forgot password: request a 6-digit reset code */
require_once __DIR__ . '/includes/layout.php';

$role = ($_POST['role'] ?? $_GET['role'] ?? 'student') === 'instructor' ? 'instructor' : 'student';
$email = trim((string) ($_POST['email'] ?? ''));
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Enter the email address you registered with.';
    } else {
        $sql = $role === 'student' ? 'SELECT student_id FROM student WHERE student_email = ?' : 'SELECT instructor_id FROM instructor WHERE instructor_email = ?';
        $st = db()->prepare($sql);
        $st->execute([$email]);
        $_SESSION['reset'] = ['email' => $email, 'role' => $role];
        if ($st->fetchColumn()) {
            $code = (string) random_int(100000, 999999);
            db()->prepare('UPDATE password_reset SET used = 1 WHERE user_email = ? AND user_role = ? AND used = 0')->execute([$email, $role]);
            db()->prepare('INSERT INTO password_reset (user_role, user_email, code_hash, expires_at, used, created_at) VALUES (?, ?, ?, ?, 0, ?)')
                ->execute([$role, $email, password_hash($code, PASSWORD_DEFAULT), date('Y-m-d H:i:s', time() + 900), now_str()]);
            // Prototype only: there is no mail server on XAMPP, so the "email" is shown on the next screen.
            $_SESSION['reset']['demo_code'] = $code;
        }
        redirect('reset.php');
    }
}

page_start('Forgot password?', ['center' => true, 'sub' => 'Enter your registered email and we’ll send you a reset code.', 'screen' => 'Auth 1. Forgot password']);
?>
<form method="post" class="sheet form" novalidate>
  <?= csrf_field() ?>
  <?php if ($error): ?><div class="alert alert-error" role="alert"><?= icon('alert', 18) ?><span><?= h($error) ?></span></div><?php endif; ?>
  <fieldset class="field">
    <legend class="label">I am</legend>
    <div class="seg">
      <label><input type="radio" name="role" value="student" <?= $role === 'student' ? 'checked' : '' ?>><span>Student</span></label>
      <label><input type="radio" name="role" value="instructor" <?= $role === 'instructor' ? 'checked' : '' ?>><span>Instructor</span></label>
    </div>
  </fieldset>
  <label class="field">
    <span class="label">Email</span>
    <span class="input-wrap"><span class="input-ic"><?= icon('mail', 20) ?></span>
      <input class="input" type="email" name="email" value="<?= h($email) ?>" placeholder="Enter your email address" autocomplete="email" required></span>
  </label>
  <button class="btn btn-primary btn-block" type="submit" data-busy-text="Sending…">Send reset code</button>
  <div class="link-row center"><a class="link" href="<?= h(url('login.php?role=' . $role)) ?>">← Back to sign in</a></div>
</form>
<?php page_end(); ?>
