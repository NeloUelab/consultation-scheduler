<?php
/* Instructor 4 · Status updated (after Confirm) */
require_once __DIR__ . '/../includes/layout.php';
$u = require_role('instructor');
$c = find_consultation((int) ($_GET['id'] ?? 0));
if (!$c || (int) $c['instructor_id'] !== (int) $u['id']) {
    redirect('instructor/consultations.php');
}
page_start('Status Updated', ['center' => true, 'sub' => 'Consultation request has been processed', 'screen' => 'Instructor 4. Status updated']);
?>
<section class="card center stack">
  <span class="avatar avatar-xl" aria-hidden="true"><?= h(initial($c['student_name'])) ?></span>
  <b class="h2">Request updated</b>
  <?= status_chip($c['consultation_status']) ?>
</section>
<section class="card">
  <h2 class="label label-blue">Consultation details</h2>
  <dl class="details">
    <div><dt><?= icon('user', 18) ?> Student</dt><dd><?= h($c['student_name']) ?></dd></div>
    <div><dt><?= icon('checkc', 18) ?> Status</dt><dd><?= h(STATUS_LABEL[$c['consultation_status']]) ?></dd></div>
    <div><dt><?= icon('cal', 18) ?> Date</dt><dd><?= h(strtoupper(fmt_day($c['consultation_date']) . ' • ' . fmt_range($c['start_time'], $c['end_time']))) ?></dd></div>
  </dl>
  <p class="muted small"><?= h($c['student_name']) ?> has been notified.</p>
</section>
<a class="btn btn-primary btn-block btn-lift" href="<?= h(url('instructor/dashboard.php')) ?>">Back to dashboard</a>
<a class="btn btn-soft btn-block" href="<?= h(url('instructor/consultations.php')) ?>">View consultations</a>
<?php page_end(); ?>
