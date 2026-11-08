<?php
require_once __DIR__ . '/../config/config.php';

/** Shared PDO connection to MySQL (XAMPP). */
function db(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    try {
        if (getenv('SCS_SQLITE')) { // developer override for automated tests only
            $pdo = new PDO('sqlite:' . getenv('SCS_SQLITE'), null, null, $options);
            $pdo->exec('PRAGMA foreign_keys = ON');
            return $pdo;
        }
        $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        $pdo->exec("SET time_zone = '+08:00'");
    } catch (PDOException $e) {
        db_unavailable($e->getMessage());
    }
    return $pdo;
}

function db_unavailable(string $msg): void
{
    http_response_code(503);
    if (strpos(str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? ''), '/api/') !== false) {
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => 'Database unavailable']);
        exit;
    }
    $base = BASE_URL;
    $safe = htmlspecialchars($msg, ENT_QUOTES, 'UTF-8');
    echo <<<HTML
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Database not ready</title><link rel="stylesheet" href="{$base}/assets/css/app.css"></head>
<body><main class="app"><div class="screen"><h1 class="h1">Database not ready</h1>
<p class="muted">The app could not connect to MySQL database <code>consultation_scheduler_db</code>.</p>
<ol class="steps"><li>Start <b>Apache</b> and <b>MySQL</b> in the XAMPP Control Panel.</li>
<li>Run the installer: <a href="{$base}/setup.php">setup.php</a> (or import <code>database/consultation_scheduler_db.sql</code> in phpMyAdmin).</li></ol>
<div class="alert alert-error">{$safe}</div></div></main></body></html>
HTML;
    exit;
}

function now_str(): string
{
    return date('Y-m-d H:i:s');
}
