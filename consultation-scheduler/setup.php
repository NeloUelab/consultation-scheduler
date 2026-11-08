<?php
/**
 * One-click installer for XAMPP: creates consultation_scheduler_db and loads the demo data
 * from database/consultation_scheduler_db.sql. Running it again resets the demo data.
 */
require_once __DIR__ . '/config/config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_name('SCSSESSID');
    session_start();
}
$base = BASE_URL;
$msg = null;
$ok = false;
$installed = false;

try {
    $server = new PDO('mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';charset=utf8mb4', DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $installed = (bool) $server->query("SHOW DATABASES LIKE 'consultation_scheduler_db'")->fetchColumn();
} catch (PDOException $e) {
    $server = null;
    $msg = 'Cannot reach MySQL at ' . DB_HOST . ':' . DB_PORT . '. Start MySQL in the XAMPP Control Panel, then reload this page. (' . $e->getMessage() . ')';
}

if ($server && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $sql = file_get_contents(__DIR__ . '/database/consultation_scheduler_db.sql');
        $sql = preg_replace('/^\s*--.*$/m', '', $sql);
        $statements = array_filter(array_map('trim', preg_split('/;\s*(\r?\n|$)/', $sql)));
        foreach ($statements as $stmt) {
            $server->exec($stmt);
        }
        $ok = true;
        $installed = true;
        $_SESSION = [];
        $msg = 'Database consultation_scheduler_db created (' . count($statements) . ' statements). Demo data loaded.';
    } catch (PDOException $e) {
        $msg = 'Installation failed: ' . $e->getMessage();
    }
}
$h = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Setup · Student Consultation Scheduler</title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
<link rel="stylesheet" href="<?= $h($base) ?>/assets/css/app.css?v=1"></head>
<body><main class="app"><div class="screen">
  <header class="hero"><h1 class="h1">Setup</h1><p class="muted">Student Consultation Scheduler · XAMPP installer</p></header>
  <?php if ($msg): ?>
    <div class="alert <?= $ok ? 'alert-success' : 'alert-error' ?>" role="status"><span><b><?= $ok ? 'Done. ' : 'Problem. ' ?></b><?= $h($msg) ?></span></div>
  <?php endif; ?>
  <section class="card">
    <h2 class="label">Status</h2>
    <dl class="details">
      <div><dt>MySQL server</dt><dd><?= $server ? 'Connected' : 'Not reachable' ?></dd></div>
      <div><dt>Database</dt><dd><?= $installed ? 'consultation_scheduler_db found' : 'Not installed' ?></dd></div>
      <div><dt>Time zone</dt><dd><?= $h(date_default_timezone_get()) ?> · <?= $h(date('j M Y g:i A')) ?></dd></div>
    </dl>
  </section>
  <?php if ($server): ?>
  <form method="post">
    <?php if ($installed): ?><p class="muted small">Running setup again <b>deletes all bookings</b> and restores the demo data.</p><?php endif; ?>
    <button class="btn btn-primary btn-block" type="submit"><?= $installed ? 'Reset demo database' : 'Install database' ?></button>
  </form>
  <?php endif; ?>
  <?php if ($installed): ?>
  <section class="card">
    <h2 class="label">Demo accounts</h2>
    <dl class="details">
      <div><dt>Student</dt><dd><code>john.doe@example.com</code> / <code>student123</code></dd></div>
      <div><dt>Student 2</dt><dd><code>ana.reyes@example.com</code> / <code>student123</code></dd></div>
      <div><dt>Instructor</dt><dd><code>juan.delacruz@example.com</code> / <code>instructor123</code></dd></div>
    </dl>
  </section>
  <a class="btn btn-soft btn-block" href="<?= $h($base) ?>/login.php">Open the app</a>
  <?php endif; ?>
</div></main></body></html>
