<?php
/* Student 2 · Dashboard — FR-05 view requests, B-04 cancel with confirmation */
require_once __DIR__ . '/../includes/layout.php';
$u = require_role('student');
$sid = (int) $u['id'];

$base = 'SELECT c.*, s.student_name, i.instructor_name, i.availability_status FROM consultation c
           JOIN student s ON s.student_id = c.student_id JOIN instructor i ON i.instructor_id = c.instructor_id
          WHERE c.student_id = ? ';
$st = db()->prepare($base . "AND c.consultation_status IN ('PENDING','CONFIRMED') AND c.consultation_date >= ? ORDER BY c.consultation_date, c.start_time");
$st->execute([$sid, date('Y-m-d')]);
$active = $st->fetchAll();
$st = db()->prepare($base . "AND c.consultation_status IN ('DECLINED','CANCELLED') ORDER BY c.updated_at DESC LIMIT 5");
$st->execute([$sid]);
$history = $st->fetchAll();

page_start('My Dashboard', ['settings' => url('settings.php'), 'screen' => 'Student 2. Dashboard']);
?>
<section class="card profile">
  <span class="avatar avatar-lg" aria-hidden="true"><?= h(initial($u['name'])) ?></span>
  <div><b class="h3"><?= h($u['name']) ?></b><span class="muted small">Student</span></div>
</section>

<div class="panel-head"><h2 class="label">My consultations</h2><span class="pill"><?= count($active) ?> session<?= count($active) === 1 ? '' : 's' ?></span></div>
<?php foreach ($active as $c) { consultation_card($c, 'student'); } ?>
<?php if (!$active): ?>
  <div class="empty"><?= icon('cal', 28) ?><p><b>No upcoming consultations.</b></p><p class="muted small">Book a slot with an instructor to get started.</p></div>
<?php endif; ?>

<?php if ($history): ?>
  <details class="history"<?= array_filter($history, fn($x) => strtotime($x['updated_at']) > time() - 600) ? ' open' : '' ?>><summary class="label">Recent history (<?= count($history) ?>)</summary>
    <?php foreach ($history as $c) { consultation_card($c, 'student'); } ?>
  </details>
<?php endif; ?>

<a class="btn btn-primary btn-block btn-lift" href="<?= h(url('student/instructors.php')) ?>">Book a consultation</a>
<?php page_end(); ?>
