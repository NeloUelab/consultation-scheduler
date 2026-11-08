<?php
/* Student 4 · Book a Slot — FR-03 select date / window / time, FR-04 validation, FR-05 edit request */
require_once __DIR__ . '/../includes/layout.php';
$u = require_role('student');
$sid = (int) $u['id'];

$editId = (int) ($_REQUEST['edit'] ?? 0) ?: null;
$pre = ['slot_id' => 0, 'start_time' => '', 'end_time' => ''];
$date = (string) ($_GET['date'] ?? '');
$iid = (int) ($_REQUEST['instructor'] ?? 0);

if ($editId) { // FR-05: a pending request can be edited
    $c = find_consultation($editId);
    if (!$c || (int) $c['student_id'] !== $sid || $c['consultation_status'] !== 'PENDING') {
        flash('error', 'Only your pending requests can be edited.');
        redirect('student/dashboard.php');
    }
    $iid = (int) $c['instructor_id'];
    if ($date === '') {
        $date = $c['consultation_date'];
        $pre = ['slot_id' => (int) $c['slot_id'], 'start_time' => substr($c['start_time'], 0, 5), 'end_time' => substr($c['end_time'], 0, 5)];
    }
}
$draft = $_SESSION['booking_draft'] ?? null;
if (isset($_GET['back']) && $draft) { // "Edit" from the Confirm Booking summary
    $iid = (int) $draft['instructor_id'];
    $editId = $draft['edit_id'] ?: null;
    $date = $draft['date'];
    $pre = ['slot_id' => (int) $draft['slot_id'], 'start_time' => $draft['start_time'], 'end_time' => $draft['end_time']];
}

$st = db()->prepare('SELECT * FROM instructor WHERE instructor_id = ?');
$st->execute([$iid]);
$ins = $st->fetch();
if (!$ins) {
    redirect('student/instructors.php');
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    [$errors, $data] = validate_booking($_POST, $sid, $iid, $editId);
    $pre = ['slot_id' => $data['slot_id'], 'start_time' => $data['start_time'], 'end_time' => $data['end_time']];
    $date = $data['slot']['slot_date'] ?? (string) ($_POST['date'] ?? $date);
    if (!$errors) {
        $_SESSION['booking_draft'] = $pre + ['instructor_id' => $iid, 'date' => $date, 'edit_id' => $editId];
        redirect('student/confirm.php');
    }
}

// Dates that have open windows (for the calendar overlay)
$st = db()->prepare("SELECT slot_date, COUNT(*) n FROM availability_slot WHERE instructor_id = ? AND slot_status = 'OPEN' AND slot_date >= ? GROUP BY slot_date ORDER BY slot_date");
$st->execute([$iid, date('Y-m-d')]);
$dates = $st->fetchAll(PDO::FETCH_KEY_PAIR);
if ($date === '' || (!isset($dates[$date]) && !$pre['slot_id'])) {
    $date = (string) (array_key_first($dates) ?? '');
}

$windows = [];
if ($date !== '') {
    $st = db()->prepare("SELECT * FROM availability_slot WHERE instructor_id = ? AND slot_date = ? AND slot_status = 'OPEN' ORDER BY slot_start_time");
    $st->execute([$iid, $date]);
    $windows = $st->fetchAll();
}
if (!$pre['slot_id'] && count($windows) === 1) {
    $pre['slot_id'] = (int) $windows[0]['slot_id'];
}

/** Free gaps inside a window after removing active bookings (excluding the request being edited). */
function free_gaps(array $w, ?int $editId): array
{
    $gaps = [];
    $cur = $w['slot_start_time'];
    foreach (slot_bookings((int) $w['slot_id']) as $b) {
        if ($editId && (int) $b['consultation_id'] === $editId) {
            continue;
        }
        if ($b['start_time'] > $cur) {
            $gaps[] = [$cur, $b['start_time']];
        }
        $cur = max($cur, $b['end_time']);
    }
    if ($cur < $w['slot_end_time']) {
        $gaps[] = [$cur, $w['slot_end_time']];
    }
    return $gaps;
}

$available = $ins['availability_status'] === 'AVAILABLE';
$qs = 'instructor=' . $iid . ($editId ? '&edit=' . $editId : '');
page_start($editId ? 'Edit Request' : 'Book a Slot', ['center' => true, 'sub' => 'Schedule your consultation session', 'screen' => 'Student 4. Book a slot']);
?>
<form method="post" class="sheet form" novalidate action="<?= h(url('student/book.php?' . $qs)) ?>">
  <?= csrf_field() ?><input type="hidden" name="date" value="<?= h($date) ?>">
  <?php if (!$available): ?><div class="alert alert-warn" role="alert"><?= icon('alert', 18) ?><span><?= h($ins['instructor_name']) ?> is currently <b>Unavailable</b>. You can look at the windows, but requests can’t be sent until they switch back to Available.</span></div><?php endif; ?>
  <?php if ($errors): ?><div class="alert alert-error" role="alert"><?= icon('alert', 18) ?><span><?= h(implode(' ', array_unique($errors))) ?></span></div><?php endif; ?>

  <div class="field"><span class="label">Instructor</span>
    <span class="input-wrap"><span class="input-ic"><?= icon('user', 20) ?></span><span class="input input-static"><?= h($ins['instructor_name']) ?></span></span></div>

  <div class="field"><span class="label">Date</span>
    <button type="button" class="input input-btnlike" data-open="calendar" aria-haspopup="dialog">
      <span><?= $date ? h(fmt_long_day($date)) : 'Select date' ?></span><?= icon('cal', 20) ?></button></div>

  <fieldset class="field">
    <legend class="label">Available window</legend>
    <?php if (!$windows): ?><p class="muted small">No open windows for this instructor yet.</p><?php endif; ?>
    <?php foreach ($windows as $w): $gaps = free_gaps($w, $editId); $full = !$gaps;
        $hr = (int) substr($w['slot_start_time'], 0, 2); ?>
      <label class="window <?= $full ? 'is-full' : '' ?>">
        <input type="radio" name="slot_id" value="<?= (int) $w['slot_id'] ?>" <?= (int) $pre['slot_id'] === (int) $w['slot_id'] ? 'checked' : '' ?> <?= $full ? 'disabled' : '' ?>
          data-start="<?= h(substr($w['slot_start_time'], 0, 5)) ?>" data-end="<?= h(substr($w['slot_end_time'], 0, 5)) ?>">
        <span class="window-body">
          <b><?= h(fmt_range($w['slot_start_time'], $w['slot_end_time'])) ?></b>
          <span class="small"><?= $hr < 12 ? 'Morning' : ($hr < 17 ? 'Afternoon' : 'Evening') ?> session · <?= $full ? 'fully booked' : 'free ' . h(implode(', ', array_map(fn($g) => fmt_range($g[0], $g[1]), $gaps))) ?></span>
        </span>
        <span class="window-ic"><?= icon($hr < 12 ? 'sun' : 'sunset', 20) ?></span>
      </label>
    <?php endforeach; ?>
  </fieldset>

  <fieldset class="field">
    <legend class="label">Your time</legend>
    <div class="two">
      <label class="field"><span class="small muted">Start</span><input class="input <?= isset($errors['start_time']) ? 'is-invalid' : '' ?>" type="time" name="start_time" value="<?= h($pre['start_time']) ?>" step="300" required></label>
      <label class="field"><span class="small muted">End</span><input class="input <?= isset($errors['end_time']) ? 'is-invalid' : '' ?>" type="time" name="end_time" value="<?= h($pre['end_time']) ?>" step="300" required></label>
    </div>
    <p class="hint" id="time-hint"><?= icon('info', 16) ?> Must be within the selected window</p>
  </fieldset>

  <button class="btn btn-primary btn-block" type="submit" <?= $available && $windows ? '' : 'disabled' ?>>Confirm</button>
  <a class="btn btn-soft btn-block" href="<?= h(url($editId ? 'student/dashboard.php' : 'student/instructors.php')) ?>">Back</a>
</form>

<!-- Calendar overlay: only dates with open windows can be picked -->
<div class="scrim" id="calendar" role="dialog" aria-modal="true" aria-labelledby="cal-title" hidden>
  <form class="dialog" method="get" action="<?= h(url('student/book.php')) ?>">
    <input type="hidden" name="instructor" value="<?= $iid ?>"><?php if ($editId): ?><input type="hidden" name="edit" value="<?= $editId ?>"><?php endif; ?>
    <h2 class="h2" id="cal-title">Available slot</h2>
    <p class="muted small">Choose a date · highlighted days have open windows</p>
    <?php
    $months = [];
    foreach (array_keys($dates) as $d) { $months[substr($d, 0, 7)] = true; }
    if (!$months) { $months[date('Y-m')] = true; }
    $mi = 0;
    foreach (array_keys($months) as $ym):
        $first = strtotime($ym . '-01');
        $lead = (int) date('N', $first) - 1;
        $days = (int) date('t', $first); ?>
      <div class="cal" data-month="<?= $mi ?>" <?= $mi ? 'hidden' : '' ?>>
        <div class="cal-head">
          <button type="button" class="icon-btn icon-btn-soft" data-cal="-1" aria-label="Previous month" <?= $mi ? '' : 'disabled' ?>><?= icon('back', 18) ?></button>
          <b><?= date('F Y', $first) ?></b>
          <button type="button" class="icon-btn icon-btn-soft flip" data-cal="1" aria-label="Next month" <?= $mi < count($months) - 1 ? '' : 'disabled' ?>><?= icon('back', 18) ?></button>
        </div>
        <div class="cal-grid" role="radiogroup" aria-label="<?= date('F Y', $first) ?>">
          <?php foreach (['M', 'T', 'W', 'T', 'F', 'S', 'S'] as $dn): ?><span class="cal-dn"><?= $dn ?></span><?php endforeach; ?>
          <?php for ($i = 0; $i < $lead; $i++): ?><span></span><?php endfor; ?>
          <?php for ($dd = 1; $dd <= $days; $dd++): $ds = $ym . '-' . str_pad((string) $dd, 2, '0', STR_PAD_LEFT); $has = isset($dates[$ds]); ?>
            <?php if ($has): ?><label class="cal-day has"><input type="radio" name="date" value="<?= $ds ?>" <?= $ds === $date ? 'checked' : '' ?>><span><?= $dd ?></span></label>
            <?php else: ?><span class="cal-day"><?= $dd ?></span><?php endif; ?>
          <?php endfor; ?>
        </div>
      </div>
    <?php $mi++; endforeach; ?>
    <div class="btn-pair">
      <button class="btn btn-soft grow" type="button" data-close>Close</button>
      <button class="btn btn-primary grow" type="submit">Confirm</button>
    </div>
  </form>
</div>
<?php page_end(); ?>
