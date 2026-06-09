<?php
require_once __DIR__ . '/../config.php';

if (session_status() === PHP_SESSION_NONE) session_start();
$uid = auth_required();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
  $stmt = db()->prepare(
    'SELECT id, name, cat, icon, ex_type, default_reps, default_sets, default_dur, dur_unit, attempts
     FROM custom_exercises WHERE user_id = ? ORDER BY sort_order, id'
  );
  $stmt->execute([$uid]);
  $rows = $stmt->fetchAll();
  foreach ($rows as &$r) {
    $r['id']           = (int)$r['id'];
    $r['default_reps'] = (int)$r['default_reps'];
    $r['default_sets'] = (int)$r['default_sets'];
    $r['default_dur']  = (int)$r['default_dur'];
    $r['attempts']     = (int)$r['attempts'];
  }
  json_out(['ok' => true, 'exercises' => $rows]);
}

if ($method === 'POST') {
  $body   = get_body();
  $action = $body['action'] ?? '';

  if ($action === 'save') {
    $id      = isset($body['id']) ? (int)$body['id'] : null;
    $name    = trim(substr($body['name'] ?? '', 0, 100));
    $cat     = trim(substr($body['cat']  ?? 'Custom', 0, 50)) ?: 'Custom';
    $icon    = trim(substr($body['icon'] ?? '⭐', 0, 10)) ?: '⭐';
    $allowed = ['reps', 'timed', 'sprint', 'none'];
    $ex_type = in_array($body['ex_type'] ?? '', $allowed) ? $body['ex_type'] : 'reps';
    $dreps   = max(1, (int)($body['default_reps'] ?? 10));
    $dsets   = max(1, (int)($body['default_sets'] ?? 3));
    $ddur    = max(1, (int)($body['default_dur']  ?? 30));
    $dunit   = in_array($body['dur_unit'] ?? '', ['s','mins']) ? $body['dur_unit'] : 's';
    $datts   = max(1, (int)($body['attempts'] ?? 3));

    if (!$name) json_out(['ok' => false, 'error' => 'Name is required'], 400);

    if ($id) {
      $stmt = db()->prepare(
        'UPDATE custom_exercises SET name=?,cat=?,icon=?,ex_type=?,default_reps=?,default_sets=?,default_dur=?,dur_unit=?,attempts=?
         WHERE id=? AND user_id=?'
      );
      $stmt->execute([$name,$cat,$icon,$ex_type,$dreps,$dsets,$ddur,$dunit,$datts,$id,$uid]);
      json_out(['ok' => true, 'id' => $id]);
    } else {
      $stmt = db()->prepare(
        'INSERT INTO custom_exercises (user_id,name,cat,icon,ex_type,default_reps,default_sets,default_dur,dur_unit,attempts)
         VALUES (?,?,?,?,?,?,?,?,?,?)'
      );
      $stmt->execute([$uid,$name,$cat,$icon,$ex_type,$dreps,$dsets,$ddur,$dunit,$datts]);
      json_out(['ok' => true, 'id' => (int)db()->lastInsertId()]);
    }
  }

  if ($action === 'delete') {
    $id = (int)($body['id'] ?? 0);
    if ($id) db()->prepare('DELETE FROM custom_exercises WHERE id=? AND user_id=?')->execute([$id,$uid]);
    json_out(['ok' => true]);
  }
}

json_out(['ok' => false, 'error' => 'Bad request'], 400);
