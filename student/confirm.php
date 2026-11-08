<?php
/* Student 5 · Confirm Booking — review summary, Confirm or Edit */
require_once __DIR__ . '/../includes/layout.php';
$u = require_role('student');
$d = $_SESSION['booking_draft'] ?? null;
if (!$d) {
    redirect('student/instructors.php');
}
$st = db()->prepare('SELECT a.*, i.instructor_name FROM availability_slot a JOIN instructor i ON i.instructor_id = a.instructor_id WHERE a.slot_id = ?');
$st->execute([(int) $d['slot_id']]);
$w = $st->fetch();
if (!$w) {
    unset($_SESSION['booking_draft']);
    redirect('student/instructors.php');
}
page_start('Confirm Booking', ['center' => true, 'sub' => 'Review your session details below', 'screen' => 'Student 5. Confirm booking']);
?>
<section class="card">
  <h2 class="label">Session summary</h2>
  <dl class="details">
    <div><dt><?= icon('user', 18) ?> Instructor</dt><dd><?= h($w['instructor_name']) ?></dd></div>
    <div><dt><?= icon('cal', 18) ?> Date</dt><dd><?= h(fmt_long_day($w['slot_date'])) ?></dd></div>
    <div><dt><?= icon('clock', 18) ?> Available window</dt><dd><?= h(fmt_range($w['slot_start_time'], $w['slot_end_time'])) ?></dd></div>
    <div class="hl"><dt><?= icon('checkc', 18) ?> Your time</dt><dd><?= h(fmt_range($d['start_time'], $d['end_time'])) ?></dd></div>
  </dl>
</section>
<p class="hint"><?= icon('info', 16) ?> Review your details before sending your request.<?= $d['edit_id'] ? ' Your existing request will be updated and stays Pending.' : '' ?></p>

<form method="post" action="<?= h(url('student/action.php')) ?>" data-busy="Sending…">
  <?= csrf_field() ?><input type="hidden" name="action" value="book">
  <button class="btn btn-primary btn-block btn-lift" type="submit"><?= $d['edit_id'] ? 'Update request' : 'Confirm' ?></button>
</form>
<a class="btn btn-soft btn-block" href="<?= h(url('student/book.php?back=1')) ?>">Edit</a>
<?php page_end(); ?>
