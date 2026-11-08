<?php
require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_name('SCSSESSID');
    session_start();
}

/* ---------- output ---------- */
function h($v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}
function url(string $path = ''): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}
function redirect(string $path): void
{
    header('Location: ' . url($path));
    exit;
}

/* ---------- auth ---------- */
function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}
/** $role: 'student' | 'instructor' */
function require_role(string $role): array
{
    $u = current_user();
    if (!$u) {
        flash('info', 'Please sign in to continue.');
        redirect('login.php');
    }
    if ($u['role'] !== $role) {
        redirect($u['role'] . '/dashboard.php');
    }
    return $u;
}
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}
function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . h(csrf_token()) . '">';
}
function csrf_check(): void
{
    $sent = $_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!hash_equals(csrf_token(), (string) $sent)) {
        http_response_code(419);
        exit('Your session expired. Go back and try again.');
    }
}

/* ---------- flash / toasts ---------- */
function flash(string $type, string $message, array $extra = []): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message] + $extra;
}
function take_flashes(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

/* ---------- notifications (FR-004) ---------- */
function notify(string $role, int $id, ?int $consultationId, string $message): void
{
    db()->prepare('INSERT INTO notification (recipient_role, recipient_id, consultation_id, message, is_read, created_at)
                   VALUES (?, ?, ?, ?, 0, ?)')
        ->execute([strtoupper($role), $id, $consultationId, mb_substr($message, 0, 255), now_str()]);
}

/* ---------- domain ---------- */
function find_consultation(int $id): ?array
{
    $st = db()->prepare(
        'SELECT c.*, s.student_name, s.student_email, i.instructor_name, i.instructor_email,
                a.slot_start_time, a.slot_end_time, a.slot_date
           FROM consultation c
           JOIN student s ON s.student_id = c.student_id
           JOIN instructor i ON i.instructor_id = c.instructor_id
           JOIN availability_slot a ON a.slot_id = c.slot_id
          WHERE c.consultation_id = ?'
    );
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

/** True when [start,end) overlaps an active (PENDING/CONFIRMED) consultation in the slot. */
function slot_conflict(int $slotId, string $start, string $end, ?int $excludeId = null): bool
{
    $sql = "SELECT COUNT(*) FROM consultation WHERE slot_id = ? AND consultation_status IN ('PENDING','CONFIRMED')
            AND start_time < ? AND end_time > ?";
    $p = [$slotId, $end, $start];
    if ($excludeId) {
        $sql .= ' AND consultation_id <> ?';
        $p[] = $excludeId;
    }
    $st = db()->prepare($sql);
    $st->execute($p);
    return (int) $st->fetchColumn() > 0;
}

/** Active bookings in a slot, ordered by time. */
function slot_bookings(int $slotId): array
{
    $st = db()->prepare(
        "SELECT c.consultation_id, c.start_time, c.end_time, c.consultation_status, s.student_name
           FROM consultation c JOIN student s ON s.student_id = c.student_id
          WHERE c.slot_id = ? AND c.consultation_status IN ('PENDING','CONFIRMED') ORDER BY c.start_time"
    );
    $st->execute([$slotId]);
    return $st->fetchAll();
}

const STATUS_LABEL = ['PENDING' => 'Pending', 'CONFIRMED' => 'Confirmed', 'DECLINED' => 'Declined', 'CANCELLED' => 'Cancelled'];

function status_chip(string $s): string
{
    return '<span class="chip chip-' . strtolower(h($s)) . '">' . h(STATUS_LABEL[$s] ?? ucfirst(strtolower($s))) . '</span>';
}

/* ---------- formatting ---------- */
function fmt_t(string $t): string
{
    $ts = strtotime('2000-01-01 ' . $t);
    return date('i', $ts) === '00' ? date('gA', $ts) : date('g:iA', $ts);
}
function fmt_range(string $a, string $b): string
{
    return fmt_t($a) . ' – ' . fmt_t($b);
}
function fmt_day(string $d): string
{
    return date('D, M j', strtotime($d));
}
function fmt_long_day(string $d): string
{
    return date('F j Y', strtotime($d));
}
function rel_time(string $dt): string
{
    $diff = time() - strtotime($dt);
    if ($diff < 60) {
        return 'just now';
    }
    if ($diff < 3600) {
        return floor($diff / 60) . ' min ago';
    }
    if ($diff < 86400) {
        return floor($diff / 3600) . ' h ago';
    }
    return date('M j, g:i A', strtotime($dt));
}
function duration_label(string $a, string $b): string
{
    $m = (strtotime('2000-01-01 ' . $b) - strtotime('2000-01-01 ' . $a)) / 60;
    $hrs = floor($m / 60);
    $min = $m % 60;
    $out = $hrs ? $hrs . ' hour' . ($hrs > 1 ? 's' : '') : '';
    if ($min) {
        $out .= ($out ? ' ' : '') . $min . ' min';
    }
    $part = (int) substr($a, 0, 2) < 12 ? 'Morning' : ((int) substr($a, 0, 2) < 17 ? 'Afternoon' : 'Evening');
    return $out . ' · ' . $part;
}
function initial(string $name): string
{
    return strtoupper(mb_substr(trim($name), 0, 1));
}

/* ---------- icons ---------- */
function icon(string $name, int $size = 20): string
{
    $p = [
        'cal'     => '<rect x="4" y="5" width="16" height="15" rx="2"/><path d="M4 10h16M9 3v4M15 3v4"/>',
        'clock'   => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'user'    => '<circle cx="12" cy="8" r="4"/><path d="M4 20c1.5-4 5-5 8-5s6.5 1 8 5"/>',
        'mail'    => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/>',
        'lock'    => '<rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V8a4 4 0 118 0v3"/>',
        'eye'     => '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
        'cap'     => '<path d="M2 9l10-5 10 5-10 5-10-5z"/><path d="M6 11v5c3 2 9 2 12 0v-5"/>',
        'gear'    => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 00.3 1.8l.1.1a2 2 0 11-2.8 2.8l-.1-.1a1.7 1.7 0 00-1.8-.3 1.7 1.7 0 00-1 1.5V21a2 2 0 11-4 0v-.1a1.7 1.7 0 00-1.1-1.5 1.7 1.7 0 00-1.8.3l-.1.1a2 2 0 11-2.8-2.8l.1-.1a1.7 1.7 0 00.3-1.8 1.7 1.7 0 00-1.5-1H3a2 2 0 110-4h.1a1.7 1.7 0 001.5-1.1 1.7 1.7 0 00-.3-1.8l-.1-.1a2 2 0 112.8-2.8l.1.1a1.7 1.7 0 001.8.3H9a1.7 1.7 0 001-1.5V3a2 2 0 114 0v.1a1.7 1.7 0 001 1.5 1.7 1.7 0 001.8-.3l.1-.1a2 2 0 112.8 2.8l-.1.1a1.7 1.7 0 00-.3 1.8V9a1.7 1.7 0 001.5 1H21a2 2 0 110 4h-.1a1.7 1.7 0 00-1.5 1z"/>',
        'back'    => '<path d="M15 18l-6-6 6-6"/>',
        'search'  => '<circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/>',
        'check'   => '<path d="M5 12.5l4.5 4.5L19 7.5"/>',
        'checkc'  => '<circle cx="12" cy="12" r="9"/><path d="M8 12.5l3 3 5-6"/>',
        'info'    => '<circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/>',
        'alert'   => '<circle cx="12" cy="12" r="9"/><path d="M12 7v6M12 16h.01"/>',
        'bell'    => '<path d="M6 16V11a6 6 0 1112 0v5l1.5 2h-15L6 16z"/><path d="M10 20a2 2 0 004 0"/>',
        'plus'    => '<path d="M12 5v14M5 12h14"/>',
        'trash'   => '<path d="M4 7h16M9 7V4h6v3M6 7l1 13h10l1-13"/>',
        'x'       => '<path d="M6 6l12 12M18 6L6 18"/>',
        'logout'  => '<path d="M15 4h4v16h-4M10 8l-4 4 4 4M6 12h10"/>',
        'sun'     => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>',
        'sunset'  => '<path d="M17 18a5 5 0 00-10 0M12 2v7M4.2 10.2l1.4 1.4M1 18h2M21 18h2M18.4 11.6l1.4-1.4M23 22H1M8 6l4-4 4 4"/>',
    ][$name] ?? '';
    return '<svg class="ic" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $p . '</svg>';
}

/* ---------- booking validation (FR-03 / FR-04) ---------- */
/**
 * Validates a student's booking entry. $in keys: slot_id, start_time, end_time (HH:MM).
 * Returns [errors[], data[]] where data carries the joined slot + instructor row.
 */
function validate_booking(array $in, int $studentId, int $instructorId, ?int $editId = null): array
{
    $errors = [];
    $slotId = (int) ($in['slot_id'] ?? 0);
    $start = substr(trim((string) ($in['start_time'] ?? '')), 0, 5);
    $end = substr(trim((string) ($in['end_time'] ?? '')), 0, 5);
    $st = db()->prepare('SELECT a.*, i.instructor_name, i.availability_status FROM availability_slot a
                           JOIN instructor i ON i.instructor_id = a.instructor_id
                          WHERE a.slot_id = ? AND a.instructor_id = ?');
    $st->execute([$slotId, $instructorId]);
    $slot = $st->fetch();

    if (!$slot) {
        $errors['slot_id'] = 'Choose a date and one of the available windows.';
    } elseif ($slot['slot_status'] !== 'OPEN' || $slot['slot_date'] < date('Y-m-d')) {
        $errors['slot_id'] = 'That window is no longer open. Pick another one.';
    } elseif ($slot['availability_status'] !== 'AVAILABLE') {
        $errors['slot_id'] = $slot['instructor_name'] . ' is currently Unavailable, so new requests cannot be sent.';
    }
    if (!preg_match('/^\d{2}:\d{2}$/', $start)) {
        $errors['start_time'] = 'Enter your start time.';
    }
    if (!preg_match('/^\d{2}:\d{2}$/', $end)) {
        $errors['end_time'] = 'Enter your end time.';
    }
    if ($slot && !$errors) {
        $ws = substr($slot['slot_start_time'], 0, 5);
        $we = substr($slot['slot_end_time'], 0, 5);
        if ($end <= $start) {
            $errors['end_time'] = 'End time must be after the start time.';
        } elseif ($start < $ws || $end > $we) {
            $errors['start_time'] = 'Your time must be within the selected window (' . fmt_range($ws, $we) . ').';
        } elseif ((strtotime("2000-01-01 $end") - strtotime("2000-01-01 $start")) < 900) {
            $errors['end_time'] = 'A consultation must be at least 15 minutes long.';
        } elseif (strtotime($slot['slot_date'] . ' ' . $start) <= time()) {
            $errors['start_time'] = 'That start time has already passed. Choose a later time.';
        } else {
            $sql = "SELECT COUNT(*) FROM consultation WHERE student_id = ? AND consultation_date = ?
                     AND consultation_status IN ('PENDING','CONFIRMED') AND start_time < ? AND end_time > ?";
            $p = [$studentId, $slot['slot_date'], $end . ':00', $start . ':00'];
            if ($editId) {
                $sql .= ' AND consultation_id <> ?';
                $p[] = $editId;
            }
            $q = db()->prepare($sql);
            $q->execute($p);
            if ((int) $q->fetchColumn()) {
                $errors['start_time'] = 'You already have another consultation at that time.';
            } elseif (slot_conflict($slotId, $start . ':00', $end . ':00', $editId)) {
                $errors['start_time'] = 'Part of that time is already booked by another student. Choose a free time.';
            }
        }
    }
    return [$errors, ['slot' => $slot, 'slot_id' => $slotId, 'start_time' => $start, 'end_time' => $end, 'instructor_id' => $instructorId]];
}
