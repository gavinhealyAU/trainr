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

  // Analytics — all sessions with full results, oldest first for charting
  if (isset($_GET['analytics'])) {
    $stmt = db()->prepare(
      'SELECT id, session_date, type, condition_val, athletes_json, exercises_json, results_json, duration_secs
       FROM sessions WHERE user_id = ? ORDER BY session_date ASC, created_at ASC LIMIT 500'
    );
    $stmt->execute([$uid]);
    $rows = $stmt->fetchAll();
    $sessions = array_map(function($r) {
      return [
        'id'           => (int)$r['id'],
        'session_date' => $r['session_date'],
        'type'         => $r['type'],
        'condition'    => $r['condition_val'],
        'athletes'     => json_decode($r['athletes_json'], true) ?: [],
        'exercises'    => json_decode($r['exercises_json'], true) ?: [],
        'results'      => json_decode($r['results_json'], true) ?: [],
        'duration_secs'=> (int)$r['duration_secs'],
      ];
    }, $rows);
    json_out(['ok' => true, 'sessions' => $sessions]);
  }

  // List — most recent 100 (for session log)
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
