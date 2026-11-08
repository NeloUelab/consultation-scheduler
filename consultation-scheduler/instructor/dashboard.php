<?php
/* Instructor 2 · Dashboard — FR-001 availability status (B-01 toggle), FR-006 slots */
require_once __DIR__ . '/../includes/layout.php';
$u = require_role('instructor');
$iid = (int) $u['id'];

$st = db()->prepare('SELECT * FROM instructor WHERE instructor_id = ?');
$st->execute([$iid]);
$me = $st->fetch();
$available = $me['availability_status'] === 'AVAILABLE';

$st = db()->prepare("SELECT * FROM availability_slot WHERE instructor_id = ? AND slot_date >= ? AND slot_status = 'OPEN' ORDER BY slot_date, slot_start_time");
$st->execute([$iid, date('Y-m-d')]);
$slots = $st->fetchAll();

$st = db()->prepare("SELECT COUNT(*) FROM consultation WHERE instructor_id = ? AND consultation_status = 'PENDING'");
$st->execute([$iid]);
$pending = (int) $st->fetchColumn();

$slotErr = $_SESSION['slot_error'] ?? null;
$slotOld = $_SESSION['slot_old'] ?? ['slot_date' => '', 'slot_start_time' => '', 'slot_end_time' => ''];
unset($_SESSION['slot_error'], $_SESSION['slot_old']);

page_start('My Dashboard', ['settings' => url('settings.php'), 'screen' => 'Instructor 2. Dashboard']);
?>
<section class="card profile">
  <span class="avatar avatar-lg" aria-hidden="true"><?= h(initial($me['instructor_name'])) ?></span>
  <div><b class="h3"><?= h($me['instructor_name']) ?></b><span class="muted small">Instructor</span></div>
</section>

<!-- B-01: status card with on/off switch (not a tab switcher) -->
<section aria-labelledby="av-h">
  <h2 class="label label-dark" id="av-h">Availability status</h2>
  <form method="post" action="<?= h(url('instructor/action.php')) ?>" class="card status-card <?= $available ? 'is-on' : 'is-off' ?>" id="status-form">
    <?= csrf_field() ?><input type="hidden" name="action" value="toggle_status">
    <span class="status-dot" aria-hidden="true"></span>
    <div class="status-text">
      <b class="h3">Your status: <?= $available ? 'Available' : 'Unavailable' ?></b>
      <span class="muted small"><?= $available ? 'Students can only book your slots when you’re Available.' : 'Students can’t book your slots until you switch back to Available.' ?></span>
    </div>
    <button type="submit" class="switch" role="switch" aria-checked="<?= $available ? 'true' : 'false' ?>" aria-labelledby="av-h">
      <span class="switch-knob"></span><span class="sr-only"><?= $available ? 'Turn off availability' : 'Turn on availability' ?></span>
    </button>
  </form>
</section>

<section class="panel" aria-labelledby="slots-h">
  <div class="panel-head"><h2 class="label" id="slots-h">My slots</h2><span class="pill"><?= count($slots) ?> slot<?= count($slots) === 1 ? '' : 's' ?></span></div>
  <?php if (!$slots): ?><p class="muted small center">No upcoming slots. Add one so students can book you.</p><?php endif; ?>
  <?php foreach ($slots as $s): $books = slot_bookings((int) $s['slot_id']); ?>
    <div class="slot">
      <span class="slot-ic"><?= icon('cal', 20) ?></span>
      <div class="slot-body">
        <b><?= h(fmt_day($s['slot_date'])) ?> • <?= h(fmt_range($s['slot_start_time'], $s['slot_end_time'])) ?></b>
        <span class="muted small"><?= h(duration_label($s['slot_start_time'], $s['slot_end_time'])) ?></span>
        <?php foreach ($books as $b): ?>
          <span class="booked"><?= h(fmt_range($b['start_time'], $b['end_time'])) ?> · <?= h($b['student_name']) ?> · <?= h(STATUS_LABEL[$b['consultation_status']]) ?></span>
        <?php endforeach; ?>
        <?php if (!$books): ?><span class="free">Open · no bookings yet</span><?php endif; ?>
      </div>
      <?php if (!$books): ?>
        <form method="post" action="<?= h(url('instructor/action.php')) ?>">
          <?= csrf_field() ?><input type="hidden" name="action" value="delete_slot"><input type="hidden" name="slot_id" value="<?= (int) $s['slot_id'] ?>">
          <button class="icon-btn icon-btn-soft" type="submit" aria-label="Remove slot <?= h(fmt_day($s['slot_date'])) ?>"><?= icon('trash', 18) ?></button>
        </form>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
  <button class="btn btn-primary btn-block" type="button" data-open="add-slot"><?= icon('plus', 18) ?> Add slot</button>
</section>

<a class="btn btn-primary btn-block btn-lift" href="<?= h(url('instructor/consultations.php')) ?>">View consultations<?php if ($pending): ?> <span class="count"><?= $pending ?></span><?php endif; ?></a>

<!-- Add slot overlay -->
<div class="scrim" id="add-slot" role="dialog" aria-modal="true" aria-labelledby="as-title" <?= $slotErr ? '' : 'hidden' ?>>
  <form class="dialog" method="post" action="<?= h(url('instructor/action.php')) ?>" novalidate>
    <?= csrf_field() ?><input type="hidden" name="action" value="add_slot">
    <h2 class="h2" id="as-title">Add a slot</h2>
    <p class="muted small">Students can book any time inside this window while you are Available.</p>
    <?php if ($slotErr): ?><div class="alert alert-error" role="alert"><?= icon('alert', 18) ?><span><?= h($slotErr) ?></span></div><?php endif; ?>
    <label class="field"><span class="label">Date</span><input class="input" type="date" name="slot_date" min="<?= date('Y-m-d') ?>" value="<?= h($slotOld['slot_date']) ?>" required></label>
    <div class="two">
      <label class="field"><span class="label">Start</span><input class="input" type="time" name="slot_start_time" value="<?= h($slotOld['slot_start_time']) ?>" required></label>
      <label class="field"><span class="label">End</span><input class="input" type="time" name="slot_end_time" value="<?= h($slotOld['slot_end_time']) ?>" required></label>
    </div>
    <button class="btn btn-primary btn-block" type="submit">Confirm</button>
    <button class="btn btn-soft btn-block" type="button" data-close>Close</button>
  </form>
</div>
<?php page_end(); ?>
