<?php
/* Instructor 3 · Consultation requests — FR-002 confirm / decline (B-03) / cancel (B-04) */
require_once __DIR__ . '/../includes/layout.php';
$u = require_role('instructor');
$iid = (int) $u['id'];

$base = 'SELECT c.*, s.student_name, i.instructor_name FROM consultation c
           JOIN student s ON s.student_id = c.student_id JOIN instructor i ON i.instructor_id = c.instructor_id
          WHERE c.instructor_id = ? ';
$st = db()->prepare($base . "AND c.consultation_status = 'PENDING' ORDER BY c.consultation_date, c.start_time");
$st->execute([$iid]);
$pending = $st->fetchAll();
$st = db()->prepare($base . "AND c.consultation_status = 'CONFIRMED' AND c.consultation_date >= ? ORDER BY c.consultation_date, c.start_time");
$st->execute([$iid, date('Y-m-d')]);
$confirmed = $st->fetchAll();
$st = db()->prepare($base . "AND c.consultation_status IN ('DECLINED','CANCELLED') ORDER BY c.updated_at DESC LIMIT 5");
$st->execute([$iid]);
$history = $st->fetchAll();

page_start('Consultations', ['back' => url('instructor/dashboard.php'), 'screen' => 'Instructor 3. Consultation requests']);
?>
<p><span class="badge <?= $pending ? 'badge-amber' : 'badge-gray' ?>"><span class="dot"></span><?= $pending ? count($pending) . ' Pending Request' . (count($pending) > 1 ? 's' : '') : 'No pending requests' ?></span></p>

<?php foreach ($pending as $c) { consultation_card($c, 'instructor'); } ?>
<?php if ($confirmed): ?><h2 class="label">Confirmed</h2><?php endif; ?>
<?php foreach ($confirmed as $c) { consultation_card($c, 'instructor'); } ?>
<?php if (!$pending && !$confirmed): ?><div class="empty"><?= icon('checkc', 28) ?><p><b>You’re all caught up.</b></p><p class="muted small">New requests appear here as soon as students book.</p></div><?php endif; ?>

<?php if ($history): ?>
  <details class="history"<?= array_filter($history, fn($x) => strtotime($x['updated_at']) > time() - 600) ? ' open' : '' ?>><summary class="label">Recent history (<?= count($history) ?>)</summary>
    <?php foreach ($history as $c) { consultation_card($c, 'instructor'); } ?>
  </details>
<?php endif; ?>

<a class="btn btn-primary btn-block btn-lift" href="<?= h(url('instructor/dashboard.php')) ?>">Back to dashboard</a>
<?php page_end(); ?>
