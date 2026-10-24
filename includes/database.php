<?php
/**
 * MySQL connection for Barangay Luna MIS.
 *
 * Laragon usually uses:
 *   host: 127.0.0.1
 *   user: root
 *   password: empty
 *
 * You may override these with environment variables:
 *   BARANGAY_DB_HOST, BARANGAY_DB_PORT, BARANGAY_DB_NAME,
 *   BARANGAY_DB_USER, BARANGAY_DB_PASS
 */

function barangay_db(): PDO {
  static $pdo = null;

  if ($pdo instanceof PDO) {
    return $pdo;
  }

  $host = getenv('BARANGAY_DB_HOST') ?: '127.0.0.1';
  $port = getenv('BARANGAY_DB_PORT') ?: '3306';
  $name = getenv('BARANGAY_DB_NAME') ?: 'barangay_luna_system';
  $user = getenv('BARANGAY_DB_USER') ?: 'root';
  $pass = getenv('BARANGAY_DB_PASS') ?: '';

  $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";

  $pdo = new PDO($dsn, $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
  ]);

  return $pdo;
}
?>
