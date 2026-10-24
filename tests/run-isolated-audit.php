<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
// Each existing test gets its own database, including tests originally using transactions.
$server = new PDO('mysql:host=127.0.0.1;port=3306;charset=utf8mb4', 'root', '', [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$schema = preg_replace('/CREATE DATABASE IF NOT EXISTS barangay_luna_system.*?;/s', '', file_get_contents(__DIR__ . '/../database/schema.sql'));
$schema = str_replace('USE barangay_luna_system;', '', $schema);
$failures = [];
foreach (glob(__DIR__ . '/*-test.php') as $test) {
  if (count($argv) > 1 && !in_array(basename($test), array_slice($argv,1),true)) continue;
  $name = 'luna_audit_' . bin2hex(random_bytes(6));
  try {
    $server->exec("CREATE DATABASE `$name`");
    $server->exec("USE `$name`"); $server->exec($schema);
    $server->exec("INSERT INTO users (user_code,role,full_name,username,password_hash,status) VALUES ('AUDIT-ADMIN','admin','Audit Admin','audit-admin','unused-test-hash','Active'),('AUDIT-STAFF','staff','Audit Staff','audit-staff','unused-test-hash','Active'),('AUDIT-RES','resident','Audit Resident','audit-resident','unused-test-hash','Active')");
    putenv('BARANGAY_DB_NAME=' . $name);
    $pipes = [];
    $process = proc_open([PHP_BINARY, $test], [1=>['pipe','w'],2=>['pipe','w']], $pipes, dirname(__DIR__));
    $output = stream_get_contents($pipes[1]); $errors = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]); $code = proc_close($process);
    echo ($code === 0 ? 'PASS: ' : 'FAIL: ') . basename($test) . "\n";
    if ($code !== 0) { $failures[] = basename($test); echo $output . $errors; }
  } finally {
    $server->exec("DROP DATABASE IF EXISTS `$name`");
  }
}
putenv('BARANGAY_DB_NAME');
echo count($failures) . " failed tests. All audit databases removed; no live data or SMS used.\n";
exit($failures ? 1 : 0);
