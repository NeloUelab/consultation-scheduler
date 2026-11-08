<?php
/* Login (Student 1 / Instructor 1) — FR-01; "Forgot password?" link added for B-02 */
require_once __DIR__ . '/includes/layout.php';

if ($u = current_user()) {
    redirect($u['role'] . '/dashboard.php');
}
$role = ($_POST['role'] ?? $_GET['role'] ?? 'student') === 'instructor' ? 'instructor' : 'student';
$email = trim((string) ($_POST['email'] ?? ''));
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $pass = (string) ($_POST['password'] ?? '');
    if ($email === '' || $pass === '') {
        $error = 'Enter your email address and password.';
    } else {
        $sql = $role === 'student'
            ? 'SELECT student_id AS id, student_name AS name, student_email AS email, password_hash FROM student WHERE student_email = ?'
            : 'SELECT instructor_id AS id, instructor_name AS name, instructor_email AS email, password_hash FROM instructor WHERE instructor_email = ?';
        $st = db()->prepare($sql);
        $st->execute([$email]);
        $row = $st->fetch();
        if ($row && password_verify($pass, $row['password_hash'])) {
            session_regenerate_id(true);
            unset($row['password_hash']);
            $_SESSION['user'] = $row + ['role' => $role];
            redirect($role . '/dashboard.php');
        }
        $error = 'That email and password do not match a ' . $role . ' account. Check the "I am" choice, or use Forgot password.';
    }
}

page_start('Welcome', ['center' => true, 'sub' => 'Sign in to continue to your account', 'screen' => $role === 'student' ? 'Student 1. Login' : 'Instructor 1. Login']);
?>
<form method="post" class="sheet form" novalidate>
  <?= csrf_field() ?>
  <?php if (!empty($_SESSION['login_banner'])): ?><div class="alert alert-success" role="status"><?= icon('check', 18) ?><span><?= h($_SESSION['login_banner']) ?></span></div><?php unset($_SESSION['login_banner']); endif; ?>
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
      <input class="input" type="email" name="email" value="<?= h($email) ?>" placeholder="Enter your email address" autocomplete="username" required></span>
  </label>
  <label class="field">
    <span class="label">Password</span>
    <span class="input-wrap"><span class="input-ic"><?= icon('lock', 20) ?></span>
      <input class="input" type="password" name="password" id="pw" placeholder="Enter your password" autocomplete="current-password" required>
      <button type="button" class="input-btn" data-toggle-pw="pw" aria-label="Show password"><?= icon('eye', 20) ?></button></span>
  </label>
  <div class="link-row"><a class="link" href="<?= h(url('forgot.php')) ?>" data-carry-role>Forgot password?</a></div>
  <button class="btn btn-primary btn-block" type="submit">Login</button>
  <details class="demo">
    <summary>Demo accounts</summary>
    <p class="small">Student: <code>john.doe@example.com</code> / <code>student123</code><br>Instructor: <code>juan.delacruz@example.com</code> / <code>instructor123</code></p>
  </details>
</form>
<?php page_end(); ?>
