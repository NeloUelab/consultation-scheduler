<?php
/* Student actions — FR-03/FR-05 book or edit a request, B-04 cancel, FR-004 notify the instructor */
require_once __DIR__ . '/../includes/functions.php';
$u = require_role('student');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('student/dashboard.php');
}
csrf_check();
$sid = (int) $u['id'];
$pdo = db();
$now = now_str();
$action = (string) ($_POST['action'] ?? '');

if ($action === 'book') {
    $d = $_SESSION['booking_draft'] ?? null;
    if (!$d) {
        redirect('student/instructors.php');
    }
    $editId = $d['edit_id'] ?: null;
    if ($editId) {
        $c = find_consultation((int) $editId);
        if (!$c || (int) $c['student_id'] !== $sid || $c['consultation_status'] !== 'PENDING') {
            unset($_SESSION['booking_draft']);
            flash('error', 'This request can no longer be edited (the instructor already responded).');
            redirect('student/dashboard.php');
        }
    }
    // Validate again on submit: another student may have booked the time meanwhile (FR-04)
    [$errors, $data] = validate_booking($d, $sid, (int) $d['instructor_id'], $editId);
    if ($errors) {
        flash('error', h(implode(' ', array_unique($errors))));
        redirect('student/book.php?back=1');
    }
    $s = $data['slot'];
    $when = fmt_day($s['slot_date']) . ' • ' . fmt_range($data['start_time'], $data['end_time']);
    if ($editId) {
        $pdo->prepare('UPDATE consultation SET slot_id = ?, consultation_date = ?, start_time = ?, end_time = ?, updated_at = ? WHERE consultation_id = ?')
            ->execute([$data['slot_id'], $s['slot_date'], $data['start_time'] . ':00', $data['end_time'] . ':00', $now, $editId]);
        $id = (int) $editId;
        notify('instructor', (int) $d['instructor_id'], $id, $u['name'] . ' changed their request to ' . $when . '.');
    } else {
        $pdo->prepare("INSERT INTO consultation (student_id, instructor_id, slot_id, consultation_date, start_time, end_time, consultation_status, created_at, updated_at)
                       VALUES (?, ?, ?, ?, ?, ?, 'PENDING', ?, ?)")
            ->execute([$sid, (int) $d['instructor_id'], $data['slot_id'], $s['slot_date'], $data['start_time'] . ':00', $data['end_time'] . ':00', $now, $now]);
        $id = (int) $pdo->lastInsertId();
        notify('instructor', (int) $d['instructor_id'], $id, $u['name'] . ' requested a consultation on ' . $when . '.');
    }
    unset($_SESSION['booking_draft']);
    redirect('student/submitted.php?id=' . $id . ($editId ? '&updated=1' : ''));
}

if ($action === 'cancel') { // B-04
    $c = find_consultation((int) ($_POST['consultation_id'] ?? 0));
    if (!$c || (int) $c['student_id'] !== $sid) {
        flash('error', 'That consultation could not be found.');
    } elseif (!in_array($c['consultation_status'], ['PENDING', 'CONFIRMED'], true)) {
        flash('error', 'This consultation is no longer active.');
    } else {
        $pdo->prepare("UPDATE consultation SET consultation_status = 'CANCELLED', cancelled_by = 'STUDENT', updated_at = ? WHERE consultation_id = ?")
            ->execute([$now, $c['consultation_id']]);
        // The instructor's schedule is updated right away: the range is freed and they get a notification
        notify('instructor', (int) $c['instructor_id'], (int) $c['consultation_id'], $u['name'] . ' cancelled their ' . strtolower(STATUS_LABEL[$c['consultation_status']]) . ' consultation on ' . fmt_day($c['consultation_date']) . ' • ' . fmt_range($c['start_time'], $c['end_time']) . '. The time is open again.');
        flash('success', 'Appointment successfully canceled');
    }
    redirect('student/dashboard.php');
}
redirect('student/dashboard.php');
