<?php
require_once __DIR__ . '/../config.php';

if (session_status() === PHP_SESSION_NONE) {
  session_set_cookie_params(['samesite' => 'Lax', 'httponly' => true]);
  session_start();
}

$method = $_SERVER['REQUEST_METHOD'];

// GET — check if already authenticated
if ($method === 'GET') {
  if (!empty($_SESSION['user_id'])) {
    $user = db()->prepare('SELECT id, name, email FROM users WHERE id = ?');
    $user->execute([$_SESSION['user_id']]);
    $row = $user->fetch();
    if ($row) json_out(['ok' => true, 'user' => $row]);
  }
  json_out(['ok' => false, 'user' => null]);
}

if ($method === 'POST') {
  $body = get_body();
  $action = $body['action'] ?? '';

  if ($action === 'login') {
    $email = trim(strtolower($body['email'] ?? ''));
    $pass  = $body['password'] ?? '';
    if (!$email || !$pass) json_out(['ok' => false, 'error' => 'Email and password required'], 400);

    $stmt = db()->prepare('SELECT id, name, email, password_hash FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if (!$user || !password_verify($pass, $user['password_hash'])) {
      json_out(['ok' => false, 'error' => 'Invalid email or password'], 401);
    }
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
    json_out(['ok' => true, 'user' => ['id' => $user['id'], 'name' => $user['name'], 'email' => $user['email']]]);
  }

  if ($action === 'register') {
    $name  = trim($body['name'] ?? '');
    $email = trim(strtolower($body['email'] ?? ''));
    $pass  = $body['password'] ?? '';
    if (!$name || !$email || !$pass) json_out(['ok' => false, 'error' => 'All fields required'], 400);
    if (strlen($pass) < 8) json_out(['ok' => false, 'error' => 'Password must be at least 8 characters'], 400);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) json_out(['ok' => false, 'error' => 'Invalid email address'], 400);

    try {
      $stmt = db()->prepare('INSERT INTO users (name, email, password_hash) VALUES (?, ?, ?)');
      $stmt->execute([$name, $email, password_hash($pass, PASSWORD_DEFAULT)]);
      $id = (int)db()->lastInsertId();
      session_regenerate_id(true);
      $_SESSION['user_id'] = $id;
      json_out(['ok' => true, 'user' => ['id' => $id, 'name' => $name, 'email' => $email]]);
    } catch (PDOException $e) {
      if (str_contains($e->getMessage(), 'Duplicate')) {
        json_out(['ok' => false, 'error' => 'An account with that email already exists'], 409);
      }
      json_out(['ok' => false, 'error' => 'Registration failed'], 500);
    }
  }

  if ($action === 'logout') {
    $_SESSION = [];
    session_destroy();
    json_out(['ok' => true]);
  }
}

json_out(['ok' => false, 'error' => 'Bad request'], 400);
