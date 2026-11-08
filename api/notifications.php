<?php
/* FR-004 / NFR-001 — the page polls this every few seconds; unread notifications are returned once and marked read. */
require_once __DIR__ . '/../includes/functions.php';
header('Content-Type: application/json');
header('Cache-Control: no-store');
$u = current_user();
if (!$u) {
    http_response_code(401);
    echo json_encode(['ok' => false]);
    exit;
}
$role = strtoupper($u['role']);
$st = db()->prepare('SELECT notification_id, message, created_at FROM notification
                      WHERE recipient_role = ? AND recipient_id = ? AND is_read = 0 ORDER BY notification_id LIMIT 10');
$st->execute([$role, (int) $u['id']]);
$rows = $st->fetchAll();
if ($rows) {
    $ids = array_map(fn($r) => (int) $r['notification_id'], $rows);
    db()->prepare('UPDATE notification SET is_read = 1 WHERE notification_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')')->execute($ids);
}
echo json_encode(['ok' => true, 'items' => array_map(fn($r) => ['id' => (int) $r['notification_id'], 'message' => $r['message'], 'when' => rel_time($r['created_at'])], $rows)]);
