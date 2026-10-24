<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if (($argv[1] ?? '') === '--render') {
  putenv('BARANGAY_DB_NAME=' . $argv[2]);
  require_once __DIR__ . '/../includes/auth.php';
  session_save_path(sys_get_temp_dir()); barangay_start_session();
  $user = barangay_db()->query("SELECT * FROM users WHERE role='staff' LIMIT 1")->fetch();
  $_SESSION['barangay_user'] = barangay_format_user($user) + ['auth_version'=>2];
  $_SESSION['last_activity'] = time();
  $_GET = ['id'=>(int)$argv[3], 'popup'=>'1'];
  $_SERVER['REQUEST_METHOD']='GET';
  ob_start();
  register_shutdown_function(static function () use ($argv) {
    $html=ob_get_clean(); session_destroy();
    if (!str_contains($html,'data-preview-error="1"') || str_contains($html,'appSidebar') || str_contains($html,'Warning:') || str_contains($html,'Fatal error')) { fwrite(STDERR,'Invalid print error page'); exit(1); }
    if ($argv[3] === '2') {
      $dir=__DIR__.'/../storage/ui-previews'; if (!is_dir($dir)) mkdir($dir,0775,true);
      file_put_contents($dir.'/print-unavailable-fixture.html',$html);
    }
    echo 'PASS: unavailable print entry ' . $argv[3] . " stays in a standalone error page, without portal redirect.\n";
  });
  require __DIR__ . '/../staff-print-issued-document.php';
  exit;
}
$name='luna_print_error_test_' . bin2hex(random_bytes(6));
$server=new PDO('mysql:host=127.0.0.1;port=3306;charset=utf8mb4','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$server->exec("CREATE DATABASE `$name`");
try {
  $server->exec("USE `$name`");
  $schema=preg_replace('/CREATE DATABASE IF NOT EXISTS barangay_luna_system.*?;/s','',file_get_contents(__DIR__.'/../database/schema.sql'));
  $server->exec(str_replace('USE barangay_luna_system;','',$schema));
  $server->exec("INSERT INTO users(user_code,role,full_name,username,password_hash,status) VALUES ('TEST-STAFF','staff','Test Staff','test-staff','unused','Active')");
  $server->exec("INSERT INTO issued_documents(id,document_number,resident_name,document_type,issuance_status,generated_file_path) VALUES (1,'TEST-EMPTY','Test Applicant','Barangay Certification','Ready for Pickup',NULL),(2,'TEST-MISSING','Test Applicant','Barangay Certification','Ready for Pickup','storage/generated-documents/test-file-does-not-exist.docx'),(3,'TEST-REMOVED','Test Applicant','Barangay Certification','Removed','storage/generated-documents/test-file-does-not-exist.docx')");
  foreach ([1,2,3,999] as $id) {
    $pipes=[]; $process=proc_open([PHP_BINARY,__FILE__,'--render',$name,(string)$id],[1=>['pipe','w'],2=>['pipe','w']],$pipes);
    $output=stream_get_contents($pipes[1]); $error=stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]); $code=proc_close($process);
    if ($code || $error) throw new RuntimeException($output . $error);
    echo $output;
  }
} finally { $server->exec("DROP DATABASE `$name`"); }
echo "Isolated database removed. No production documents changed.\n";
