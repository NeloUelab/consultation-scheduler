<?php
/* Instructor actions — FR-001 status, FR-006 slots, FR-002 confirm/decline/cancel, FR-004 notifications */
require_once __DIR__ . '/../includes/functions.php';
$u = require_role('instructor');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('instructor/dashboard.php');
}
csrf_check();
$iid = (int) $u['id'];
$pdo = db();
$now = now_str();
$action = (string) ($_POST['action'] ?? '');

/** Load a consultation that belongs to this instructor or bounce back. */
function own(int $id, int $iid): array
{
    $c = find_consultation($id);
    if (!$c || (int) $c['instructor_id'] !== $iid) {
        flash('error', 'That consultation could not be found.');
        redirect('instructor/consultations.php');
    }
    return $c;
}

switch ($action) {
    case 'toggle_status': // B-01 / FR-001 / FR-004
        $st = $pdo->prepare('SELECT availability_status FROM instructor WHERE instructor_id = ?');
        $st->execute([$iid]);
        $new = $st->fetchColumn() === 'AVAILABLE' ? 'UNAVAILABLE' : 'AVAILABLE';
        $pdo->prepare('UPDATE instructor SET availability_status = ? WHERE instructor_id = ?')->execute([$new, $iid]);
        // Notify every student with an upcoming pending/confirmed consultation (FR-004)
        $st = $pdo->prepare("SELECT DISTINCT student_id FROM consultation WHERE instructor_id = ? AND consultation_status IN ('PENDING','CONFIRMED') AND consultation_date >= ?");
        $st->execute([$iid, date('Y-m-d')]);
        $msg = $u['name'] . ($new === 'AVAILABLE' ? ' is now Available for consultations.' : ' is now Unavailable. Your booked session may need to be rescheduled.');
        foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $sid) {
            notify('student', (int) $sid, null, $msg);
        }
        flash('success', $new === 'AVAILABLE' ? 'You are now available' : 'You are now unavailable');
        redirect('instructor/dashboard.php');

    case 'add_slot': // FR-006
        $d = (string) ($_POST['slot_date'] ?? '');
        $a = (string) ($_POST['slot_start_time'] ?? '');
        $b = (string) ($_POST['slot_end_time'] ?? '');
        $err = null;
        if (!$d || !$a || !$b) { $err = 'Choose a date, start time and end time.'; }
        elseif ($d < date('Y-m-d')) { $err = 'Choose today or a future date.'; }
        elseif (strtotime("$d $a") <= time()) { $err = 'The slot must start in the future.'; }
        elseif ($b <= $a) { $err = 'End time must be after the start time.'; }
        elseif ((strtotime("2000-01-01 $b") - strtotime("2000-01-01 $a")) < 1800) { $err = 'A slot must be at least 30 minutes long.'; }
        else {
            $st = $pdo->prepare("SELECT COUNT(*) FROM availability_slot WHERE instructor_id = ? AND slot_date = ? AND slot_status = 'OPEN' AND slot_start_time < ? AND slot_end_time > ?");
            $st->execute([$iid, $d, $b . ':00', $a . ':00']);
            if ((int) $st->fetchColumn()) { $err = 'This overlaps one of your existing slots on that day.'; }
        }
        if ($err) {
            $_SESSION['slot_error'] = $err;
            $_SESSION['slot_old'] = ['slot_date' => $d, 'slot_start_time' => $a, 'slot_end_time' => $b];
            redirect('instructor/dashboard.php');
        }
        $pdo->prepare("INSERT INTO availability_slot (instructor_id, slot_date, slot_start_time, slot_end_time, slot_status) VALUES (?, ?, ?, ?, 'OPEN')")
            ->execute([$iid, $d, $a . ':00', $b . ':00']);
        flash('success', 'Slot added · ' . h(fmt_day($d) . ' • ' . fmt_range($a, $b)));
        redirect('instructor/dashboard.php');

    case 'delete_slot':
        $sid = (int) ($_POST['slot_id'] ?? 0);
        if (slot_bookings($sid)) {
            flash('error', 'This slot has active bookings and cannot be removed.');
        } else {
            $st = $pdo->prepare("SELECT COUNT(*) FROM consultation WHERE slot_id = ?");
            $st->execute([$sid]);
            if ((int) $st->fetchColumn()) { // keep history rows valid: close instead of delete
                $pdo->prepare("UPDATE availability_slot SET slot_status = 'CLOSED' WHERE slot_id = ? AND instructor_id = ?")->execute([$sid, $iid]);
            } else {
                $pdo->prepare('DELETE FROM availability_slot WHERE slot_id = ? AND instructor_id = ?')->execute([$sid, $iid]);
            }
            flash('success', 'Slot removed');
        }
        redirect('instructor/dashboard.php');

    case 'confirm': // FR-002
        $c = own((int) $_POST['consultation_id'], $iid);
        if ($c['consultation_status'] !== 'PENDING') {
            flash('error', 'This request was already handled.');
            redirect('instructor/consultations.php');
        }
        $pdo->prepare("UPDATE consultation SET consultation_status = 'CONFIRMED', updated_at = ? WHERE consultation_id = ?")->execute([$now, $c['consultation_id']]);
        notify('student', (int) $c['student_id'], (int) $c['consultation_id'], $u['name'] . ' confirmed your consultation on ' . fmt_day($c['consultation_date']) . ' • ' . fmt_range($c['start_time'], $c['end_time']) . '.');
        redirect('instructor/status.php?id=' . $c['consultation_id']);

    case 'decline': // B-03 / FR-002
        $c = own((int) $_POST['consultation_id'], $iid);
        if ($c['consultation_status'] !== 'PENDING') {
            flash('error', 'This request was already handled.');
            redirect('instructor/consultations.php');
        }
        $reason = trim((string) ($_POST['reason'] ?? ''));
        $pdo->prepare("UPDATE consultation SET consultation_status = 'DECLINED', decline_reason = ?, updated_at = ? WHERE consultation_id = ?")
            ->execute([$reason !== '' ? mb_substr($reason, 0, 255) : null, $now, $c['consultation_id']]);
        notify('student', (int) $c['student_id'], (int) $c['consultation_id'], $u['name'] . ' declined your consultation on ' . fmt_day($c['consultation_date']) . ($reason !== '' ? ' — "' . $reason . '"' : '') . '. The slot is free again.');
        flash('success', 'Appointment declined · ' . h($c['student_name']), ['undo' => (int) $c['consultation_id']]);
        redirect('instructor/consultations.php');

    case 'undo_decline': // B-03 Undo (within 2 minutes, only if the time is still free)
        $c = own((int) $_POST['consultation_id'], $iid);
        $fresh = strtotime($c['updated_at']) >= time() - 120;
        if ($c['consultation_status'] !== 'DECLINED' || !$fresh) {
            flash('error', 'This decline can no longer be undone.');
        } elseif (slot_conflict((int) $c['slot_id'], $c['start_time'], $c['end_time'], (int) $c['consultation_id'])) {
            flash('error', 'That time has been booked by someone else, so the decline cannot be undone.');
        } else {
            $pdo->prepare("UPDATE consultation SET consultation_status = 'PENDING', decline_reason = NULL, updated_at = ? WHERE consultation_id = ?")->execute([$now, $c['consultation_id']]);
            notify('student', (int) $c['student_id'], (int) $c['consultation_id'], $u['name'] . ' reopened your request — it is pending again.');
            flash('info', 'Decline undone · request is pending again');
        }
        redirect('instructor/consultations.php');

    case 'cancel': // B-04 / FR-002
        $c = own((int) $_POST['consultation_id'], $iid);
        if (!in_array($c['consultation_status'], ['PENDING', 'CONFIRMED'], true)) {
            flash('error', 'This consultation is no longer active.');
            redirect('instructor/consultations.php');
        }
        $pdo->prepare("UPDATE consultation SET consultation_status = 'CANCELLED', cancelled_by = 'INSTRUCTOR', updated_at = ? WHERE consultation_id = ?")->execute([$now, $c['consultation_id']]);
        notify('student', (int) $c['student_id'], (int) $c['consultation_id'], $u['name'] . ' cancelled your consultation on ' . fmt_day($c['consultation_date']) . ' • ' . fmt_range($c['start_time'], $c['end_time']) . '.');
        flash('success', 'Appointment cancelled · slot is open again');
        redirect('instructor/consultations.php');
}
redirect('instructor/dashboard.php');
