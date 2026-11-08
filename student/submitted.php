<?php
/* Student 6 · Consultation request sent */
require_once __DIR__ . '/../includes/layout.php';
$u = require_role('student');
$c = find_consultation((int) ($_GET['id'] ?? 0));
if (!$c || (int) $c['student_id'] !== (int) $u['id']) {
    redirect('student/dashboard.php');
}
$updated = isset($_GET['updated']);
page_start('Consultation Request', ['center' => true, 'screen' => 'Student 6. Request sent']);
?>
<section class="center stack">
  <span class="ring" aria-hidden="true"><?= icon('clock', 34) ?></span>
  <b class="h2"><?= $updated ? 'Request updated' : 'Request sent' ?></b>
  <p class="muted">Your consultation request has been <?= $updated ? 'updated' : 'submitted' ?> and is awaiting instructor approval.</p>
  <span class="badge badge-blue"><span class="dot"></span>Pending approval</span>
</section>
<section class="card">
  <h2 class="label label-blue">Booking summary</h2>
  <dl class="details">
    <div><dt><?= icon('user', 18) ?> Instructor</dt><dd><?= h($c['instructor_name']) ?></dd></div>
    <div><dt><?= icon('cal', 18) ?> Date</dt><dd><?= h(fmt_long_day($c['consultation_date'])) ?></dd></div>
    <div><dt><?= icon('clock', 18) ?> Time</dt><dd><?= h(fmt_range($c['start_time'], $c['end_time'])) ?></dd></div>
    <div><dt><?= icon('info', 18) ?> Status</dt><dd><?= status_chip($c['consultation_status']) ?></dd></div>
  </dl>
  <p class="muted small">You’ll get a notification here as soon as <?= h($c['instructor_name']) ?> responds.</p>
</section>
<a class="btn btn-primary btn-block btn-lift" href="<?= h(url('student/dashboard.php')) ?>">Back to dashboard</a>
<?php page_end(); ?>
