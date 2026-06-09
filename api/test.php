<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/plain');

echo "PHP version: " . PHP_VERSION . "\n";
echo "PDO drivers: " . implode(', ', PDO::getAvailableDrivers()) . "\n\n";

$cfgPath = __DIR__ . '/../config.php';
echo "config.php path: $cfgPath\n";
echo "config.php exists: " . (file_exists($cfgPath) ? 'YES' : 'NO') . "\n\n";

if (!file_exists($cfgPath)) { die("STOP: config.php not found at expected path\n"); }

require_once $cfgPath;
echo "config.php loaded OK\n";

echo "Connecting to DB...\n";
try {
  $pdo = db();
  echo "DB connection OK\n";
  $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
  echo "Tables: " . implode(', ', $tables) . "\n";
} catch (Exception $e) {
  echo "DB ERROR: " . $e->getMessage() . "\n";
}
