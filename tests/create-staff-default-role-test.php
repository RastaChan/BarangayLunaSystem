<?php
// Execute only the creation branch with an in-memory statement stub.
$source = file_get_contents(__DIR__ . '/../admin-actions.php');
$start = strpos($source, "    case 'create_staff':");
$end = strpos($source, "    case 'update_staff':", $start);
$branch = substr($source, $start, $end - $start);
$saved = [];
function barangay_validate_password(string $value): void { if (strlen($value) < 8) throw new RuntimeException('Invalid password'); }
function barangay_db(): object {
  return new class {
    public function prepare(string $sql): object {
      if (!str_contains($sql, "'staff'")) throw new RuntimeException('Creation must retain staff access');
      return new class {
        public function execute(array $values): void { $GLOBALS['saved'] = $values; }
      };
    }
  };
}
function admin_next_code(...$args): string { return 'STF-TEST'; }
function admin_log(...$args): void {}
function admin_redirect(string $view, string $status): never { throw new RuntimeException($status); }
$user = []; $redirect = 'user-management';
foreach ([[], ['position'=>'Administrator', 'role'=>'admin']] as $extra) {
  $_POST = $extra + ['full_name'=>'Test Staff','username'=>'test@example.test','mobile'=>'09123456789','password'=>'Testing123!','status'=>'Active'];
  try { eval("switch ('create_staff') { " . $branch . ' }'); }
  catch (RuntimeException $error) { if ($error->getMessage() !== 'created') throw $error; }
  if (($saved[':position'] ?? '') !== 'Barangay Staff') throw new RuntimeException('Staff position is not automatic');
  if (!password_verify('Testing123!', $saved[':password_hash'])) throw new RuntimeException('Password handling changed');
}
echo "PASS: staff creation needs no position and ignores submitted role/title overrides.\n";
