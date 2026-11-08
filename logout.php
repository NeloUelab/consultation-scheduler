<?php
require_once __DIR__ . '/includes/functions.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $role = current_user()['role'] ?? 'student';
    $_SESSION = [];
    session_regenerate_id(true);
    flash('success', 'You have signed out.');
    redirect('login.php?role=' . $role);
}
redirect('login.php');
