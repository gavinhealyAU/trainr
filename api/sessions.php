<?php
require_once __DIR__ . '/../config.php';

if (session_status() === PHP_SESSION_NONE) session_start();
$uid = auth_required();
$method = $_SERVER['REQUEST_METHOD'];

// GET — list sessions or get one
if ($method === 'GET') {
  if (isset($_GET['id'])) {
    $stmt = db()->prepare('SELECT * FROM sessions WHERE id = ? AND user_id = ?');
    $stmt->execute([(int)$_GET['id'], $uid]);
    $row = $stmt->fetch();
    if (!$row) json_out(['ok' => false, 'error' => 'Not found'], 404);
    $row['athletes']  = json_decode($row['athletes_json'], true);
    $row['exercises'] = json_decode($row['exercises_json'], true);
    $row['results']   = json_decode($row['results_json'], true);
    json_out(['ok' => true, 'session' => $row]);
  }

  // List — most recent 100
  $stmt = db()->prepare(
    'SELECT id, session_date, type, condition_val, notes, athletes_json, duration_secs, created_at
     FROM sessions WHERE user_id = ? ORDER BY session_date DESC, created_at DESC LIMIT 100'
  );
  $stmt->execute([$uid]);
  $rows = $stmt->fetchAll();
  foreach ($rows as &$r) {
    $r['athletes'] = json_decode($r['athletes_json'], true);
    unset($r['athletes_json']);
  }
  json_out(['ok' => true, 'sessions' => $rows]);
}

// POST — save a session
if ($method === 'POST') {
  $body = get_body();
  $action = $body['action'] ?? 'save';

  if ($action === 'save') {
    $date     = $body['date'] ?? date('Y-m-d');
    $type     = substr($body['type'] ?? 'Sprint', 0, 50);
    $cond     = substr($body['condition'] ?? 'dry', 0, 20);
    $notes    = $body['notes'] ?? '';
    $athletes = json_encode($body['athletes'] ?? []);
    $exercises = json_encode($body['exercises'] ?? []);
    $results  = json_encode($body['results'] ?? []);
    $duration = (int)($body['duration_secs'] ?? 0);

    $stmt = db()->prepare(
      'INSERT INTO sessions (user_id, session_date, type, condition_val, notes, athletes_json, exercises_json, results_json, duration_secs)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([$uid, $date, $type, $cond, $notes, $athletes, $exercises, $results, $duration]);
    json_out(['ok' => true, 'id' => (int)db()->lastInsertId()]);
  }

  if ($action === 'delete') {
    $id = (int)($body['id'] ?? 0);
    db()->prepare('DELETE FROM sessions WHERE id = ? AND user_id = ?')->execute([$id, $uid]);
    json_out(['ok' => true]);
  }
}

json_out(['ok' => false, 'error' => 'Bad request'], 400);
