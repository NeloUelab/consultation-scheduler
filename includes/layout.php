<?php
require_once __DIR__ . '/functions.php';

/**
 * $o: back (url), settings (url), sub (subtitle), center (bool header centered with logo), screen (label)
 */
function page_start(string $title, array $o = []): void
{
    $u = current_user();
    $flashes = take_flashes();
    ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#2563EB">
<title><?= h($title) ?> · <?= h(APP_NAME) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
<link rel="stylesheet" href="<?= h(url('assets/css/app.css')) ?>?v=1">
</head>
<body data-base="<?= h(BASE_URL) ?>" data-csrf="<?= h(csrf_token()) ?>" data-poll="<?= $u ? '1' : '0' ?>" data-poll-ms="<?= (int) POLL_INTERVAL_MS ?>">
<a class="skip" href="#main">Skip to content</a>
<main id="main" class="app">
  <div class="glow glow-a" aria-hidden="true"></div><div class="glow glow-b" aria-hidden="true"></div>
  <div class="screen">
    <?php if (!empty($o['screen'])): ?><p class="screen-tag"><?= h($o['screen']) ?></p><?php endif; ?>
    <?php if (!empty($o['center'])): ?>
      <header class="hero">
        <div class="logo-mark" aria-hidden="true"><?= icon('cap', 30) ?></div>
        <h1 class="h1"><?= h($title) ?></h1>
        <?php if (!empty($o['sub'])): ?><p class="muted"><?= h($o['sub']) ?></p><?php endif; ?>
      </header>
    <?php else: ?>
      <header class="topbar">
        <?php if (!empty($o['back'])): ?><a class="icon-btn icon-btn-soft" href="<?= h($o['back']) ?>" aria-label="Back"><?= icon('back', 22) ?></a><?php endif; ?>
        <div class="topbar-text"><h1 class="h1"><?= h($title) ?></h1><?php if (!empty($o['sub'])): ?><p class="muted small"><?= h($o['sub']) ?></p><?php endif; ?></div>
        <?php if (!empty($o['settings'])): ?><a class="icon-btn" href="<?= h($o['settings']) ?>" aria-label="Account settings"><?= icon('gear', 24) ?></a><?php endif; ?>
      </header>
    <?php endif; ?>
    <div class="toast-stack" id="toast-stack" aria-live="polite">
    <?php foreach ($flashes as $f): ?>
      <div class="toast toast-<?= h($f['type']) ?>" role="status" data-autohide="<?= !empty($f['undo']) ? 9000 : 6000 ?>">
        <span><?= $f['message'] /* escaped by caller */ ?></span>
        <?php if (!empty($f['undo'])): ?>
          <form method="post" action="<?= h(url('instructor/action.php')) ?>" class="inline">
            <?= csrf_field() ?><input type="hidden" name="action" value="undo_decline"><input type="hidden" name="consultation_id" value="<?= (int) $f['undo'] ?>">
            <button class="toast-btn" type="submit">Undo</button>
          </form>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
    </div>
    <?php
}

function page_end(): void
{
    ?>
  </div>
</main>

<!-- Confirmation dialog shared by Decline (B-03) and Cancel (B-04) -->
<div class="scrim" id="confirm-dialog" role="alertdialog" aria-modal="true" aria-labelledby="cd-title" aria-describedby="cd-body" hidden>
  <form class="dialog" method="post" id="cd-form">
    <?= csrf_field() ?>
    <input type="hidden" name="action" id="cd-action">
    <input type="hidden" name="consultation_id" id="cd-id">
    <h2 class="h2" id="cd-title">Are you sure?</h2>
    <p class="muted" id="cd-body"></p>
    <label class="field" id="cd-reason-wrap" hidden>
      <span class="label">Reason (optional)</span>
      <input class="input" type="text" name="reason" maxlength="255" placeholder="e.g. Faculty meeting at that time">
    </label>
    <button class="btn btn-danger btn-block" type="submit" id="cd-ok">Confirm</button>
    <button class="btn btn-soft btn-block" type="button" data-close id="cd-cancel">Keep</button>
  </form>
</div>

<script src="<?= h(url('assets/js/app.js')) ?>?v=1"></script>
</body>
</html>
    <?php
}

/** Consultation card used on both dashboards. $viewer: 'student' | 'instructor' */
function consultation_card(array $c, string $viewer): void
{
    $other = $viewer === 'student' ? $c['instructor_name'] : $c['student_name'];
    $status = $c['consultation_status'];
    $active = in_array($status, ['PENDING', 'CONFIRMED'], true);
    ?>
  <article class="card consult consult-<?= strtolower(h($status)) ?>">
    <div class="consult-head">
      <?php if ($viewer === 'instructor'): ?><span class="avatar <?= $status === 'CONFIRMED' ? 'avatar-green' : '' ?>" aria-hidden="true"><?= h(initial($other)) ?></span><?php endif; ?>
      <div class="consult-who"><b><?= h($other) ?></b><?php if ($viewer === 'instructor'): ?><span class="muted small">Consultation request</span><?php endif; ?></div>
      <?= status_chip($status) ?>
    </div>
    <div class="consult-meta">
      <span class="meta-row"><span class="meta-ic"><?= icon('cal', 18) ?></span><?= h(fmt_day($c['consultation_date'])) ?> • <?= h(fmt_range($c['start_time'], $c['end_time'])) ?></span>
      <?php if ($status === 'PENDING' && $viewer === 'instructor'): ?><span class="meta-row"><span class="meta-ic"><?= icon('clock', 18) ?></span>Awaiting your response</span><?php endif; ?>
      <?php if ($status === 'PENDING' && $viewer === 'student'): ?><span class="meta-row"><span class="meta-ic"><?= icon('clock', 18) ?></span>Awaiting instructor approval</span><?php endif; ?>
      <?php if ($viewer === 'student' && $active && ($c['availability_status'] ?? 'AVAILABLE') === 'UNAVAILABLE'): ?><span class="note note-amber"><?= icon('alert', 18) ?> <?= h($other) ?> is currently Unavailable</span><?php endif; ?>
      <?php if ($status === 'CONFIRMED'): ?><span class="note note-green"><?= icon('checkc', 18) ?> Session confirmed and scheduled</span><?php endif; ?>
      <?php if ($status === 'DECLINED'): ?><span class="note note-gray"><?= icon('info', 18) ?> Declined<?= $c['decline_reason'] ? ': ' . h($c['decline_reason']) : '' ?> · slot released</span><?php endif; ?>
      <?php if ($status === 'CANCELLED'): ?><span class="note note-gray"><?= icon('info', 18) ?> Cancelled by <?= $c['cancelled_by'] === strtoupper($viewer) ? 'you' : strtolower((string) $c['cancelled_by']) ?> · slot reopened for booking</span><?php endif; ?>
    </div>
    <?php if ($active): ?>
      <?php if ($viewer === 'instructor' && $status === 'PENDING'): ?>
        <div class="btn-pair">
          <form method="post" action="<?= h(url('instructor/action.php')) ?>" class="grow" data-busy="Confirming…">
            <?= csrf_field() ?><input type="hidden" name="action" value="confirm"><input type="hidden" name="consultation_id" value="<?= (int) $c['consultation_id'] ?>">
            <button class="btn btn-primary btn-block" type="submit">Confirm</button>
          </form>
          <button class="btn btn-outline-danger grow" type="button"
            data-confirm="decline" data-id="<?= (int) $c['consultation_id'] ?>"
            data-endpoint="<?= h(url('instructor/action.php')) ?>"
            data-title="Decline this appointment?"
            data-body="<?= h($other . ' · ' . fmt_day($c['consultation_date']) . ' • ' . fmt_range($c['start_time'], $c['end_time']) . '. The student will be notified and the time slot becomes free again.') ?>"
            data-ok="Decline appointment" data-keep="Keep request">Decline</button>
        </div>
      <?php else: ?>
        <?php if ($viewer === 'student' && $status === 'PENDING'): ?>
        <a class="btn btn-soft btn-block" href="<?= h(url('student/book.php?edit=' . (int) $c['consultation_id'])) ?>">Edit request</a>
        <?php endif; ?>
        <button class="btn btn-cancel btn-block" type="button"
          data-confirm="cancel" data-id="<?= (int) $c['consultation_id'] ?>"
          data-endpoint="<?= h(url($viewer . '/action.php')) ?>"
          data-title="Cancel this appointment?"
          data-body="<?= h('Your ' . strtolower(STATUS_LABEL[$status]) . ' session with ' . $other . ' on ' . fmt_day($c['consultation_date']) . ' • ' . fmt_range($c['start_time'], $c['end_time']) . ' will be cancelled. ' . $other . ' will be notified and the time slot becomes available again.') ?>"
          data-ok="Cancel appointment" data-keep="Keep appointment">Cancel</button>
      <?php endif; ?>
    <?php endif; ?>
  </article>
    <?php
}
