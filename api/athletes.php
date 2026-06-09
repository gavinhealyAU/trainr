<?php
require_once __DIR__ . '/../config.php';

if (session_status() === PHP_SESSION_NONE) session_start();
$uid = auth_required();
$method = $_SERVER['REQUEST_METHOD'];

// GET — return all athletes for user
if ($method === 'GET') {
  $stmt = db()->prepare('SELECT name, age FROM athletes WHERE user_id = ? ORDER BY sort_order, id');
  $stmt->execute([$uid]);
  json_out(['ok' => true, 'athletes' => $stmt->fetchAll()]);
}

// POST — sync full athletes array (replace all)
if ($method === 'POST') {
  $body = get_body();
  $incoming = $body['athletes'] ?? [];
  if (!is_array($incoming)) json_out(['ok' => false, 'error' => 'Invalid data'], 400);

  $pdo = db();
  $pdo->beginTransaction();
  try {
    // Soft sync: upsert each athlete, remove ones not in the list
    $names = array_values(array_filter(array_map(function($a) { return trim($a['name'] ?? ''); }, $incoming)));

    if ($names) {
      // Delete athletes not in the new list
      $placeholders = implode(',', array_fill(0, count($names), '?'));
      $del = $pdo->prepare("DELETE FROM athletes WHERE user_id = ? AND name NOT IN ($placeholders)");
      $del->execute(array_merge([$uid], $names));
    } else {
      $pdo->prepare('DELETE FROM athletes WHERE user_id = ?')->execute([$uid]);
    }

    foreach ($incoming as $i => $a) {
      $name = trim($a['name'] ?? '');
      $age  = (int)($a['age'] ?? 0);
      if (!$name) continue;
      $pdo->prepare(
        'INSERT INTO athletes (user_id, name, age, sort_order) VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE age = VALUES(age), sort_order = VALUES(sort_order)'
      )->execute([$uid, $name, $age, $i]);
    }

    $pdo->commit();
    json_out(['ok' => true]);
  } catch (Exception $e) {
    $pdo->rollBack();
    json_out(['ok' => false, 'error' => 'Save failed'], 500);
  }
}

json_out(['ok' => false, 'error' => 'Bad request'], 400);
