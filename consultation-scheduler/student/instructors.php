<?php
/* Student 3 · Find an Instructor — FR-003 / FR-02 search and real-time availability */
require_once __DIR__ . '/../includes/layout.php';
$u = require_role('student');
$q = trim((string) ($_GET['q'] ?? ''));

$st = db()->prepare(
    "SELECT i.instructor_id, i.instructor_name, i.availability_status,
            (SELECT COUNT(*) FROM availability_slot a WHERE a.instructor_id = i.instructor_id
               AND a.slot_status = 'OPEN' AND a.slot_date >= ?) AS open_slots
       FROM instructor i
      WHERE i.instructor_name LIKE ?
      ORDER BY (i.availability_status = 'AVAILABLE') DESC, i.instructor_name"
);
$st->execute([date('Y-m-d'), '%' . $q . '%']);
$rows = $st->fetchAll();

page_start('Find an Instructor', ['center' => true, 'sub' => 'Book a consultation slot', 'screen' => 'Student 3. Find an instructor']);
?>
<form method="get" class="field" role="search">
  <label class="label" for="q">Search</label>
  <span class="input-wrap"><span class="input-ic"><?= icon('search', 20) ?></span>
    <input class="input" type="search" id="q" name="q" value="<?= h($q) ?>" placeholder="Search instructor name" autocomplete="off"></span>
</form>

<div class="panel-head"><h2 class="label">Instructors</h2><span class="muted small blue"><?= count($rows) ?> found</span></div>
<div class="list" id="instructor-list">
<?php foreach ($rows as $r):
    $avail = $r['availability_status'] === 'AVAILABLE';
    $bookable = $avail && (int) $r['open_slots'] > 0;
    $sub = !$avail ? 'Unavailable right now' : ((int) $r['open_slots'] ? $r['open_slots'] . ' open slot' . ($r['open_slots'] > 1 ? 's' : '') . ' · consultation available' : 'No slots available');
    $tag = $bookable ? 'a' : 'div'; ?>
  <<?= $tag ?> class="card row-card <?= $bookable ? 'is-link' : 'is-muted' ?>" <?= $bookable ? 'href="' . h(url('student/book.php?instructor=' . (int) $r['instructor_id'])) . '"' : 'aria-disabled="true"' ?>>
    <span class="avatar <?= $avail ? '' : 'avatar-gray' ?>" aria-hidden="true"><?= icon('user', 20) ?></span>
    <span class="row-text"><b><?= h($r['instructor_name']) ?></b><span class="muted small"><?= h($sub) ?></span></span>
    <span class="chip <?= $avail ? 'chip-available' : 'chip-unavailable' ?>"><span class="dot"></span><?= $avail ? 'Available' : 'Unavailable' ?></span>
  </<?= $tag ?>>
<?php endforeach; ?>
<?php if (!$rows): ?><div class="empty"><?= icon('search', 28) ?><p><b>No instructor matches “<?= h($q) ?>”.</b></p><p class="muted small">Check the spelling or clear the search.</p></div><?php endif; ?>
</div>
<a class="btn btn-soft btn-block" href="<?= h(url('student/dashboard.php')) ?>">Back to dashboard</a>
<?php page_end(); ?>
