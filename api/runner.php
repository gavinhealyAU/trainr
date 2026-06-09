<?php
require_once __DIR__ . '/../config.php';

if (session_status() === PHP_SESSION_NONE) session_start();
$uid = auth_required();
$method = $_SERVER['REQUEST_METHOD'];

// GET — fetch saved runner state
if ($method === 'GET') {
  $stmt = db()->prepare('SELECT state_json, saved_at FROM runner_state WHERE user_id = ?');
  $stmt->execute([$uid]);
  $row = $stmt->fetch();
  if ($row) {
    $state = json_decode($row['state_json'], true);
    json_out(['ok' => true, 'state' => $state, 'saved_at' => $row['saved_at']]);
  }
  json_out(['ok' => true, 'state' => null]);
}

// POST — save runner state (upsert)
if ($method === 'POST') {
  $raw = file_get_contents('php://input');
  if (!$raw) json_out(['ok' => false, 'error' => 'No data'], 400);

  db()->prepare(
    'INSERT INTO runner_state (user_id, state_json) VALUES (?, ?)
     ON DUPLICATE KEY UPDATE state_json = VALUES(state_json), saved_at = CURRENT_TIMESTAMP'
  )->execute([$uid, $raw]);
  json_out(['ok' => true]);
}

// DELETE — clear runner state after session ends
if ($method === 'DELETE') {
  db()->prepare('DELETE FROM runner_state WHERE user_id = ?')->execute([$uid]);
  json_out(['ok' => true]);
}

json_out(['ok' => false, 'error' => 'Bad request'], 400);
