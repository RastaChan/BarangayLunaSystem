<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$source=file_get_contents(__DIR__.'/../admin-actions.php');
$start=strpos($source,"    case 'reset_user_password':");
$end=strpos($source,"    case 'update_qr_status':",$start);
$branch=substr($source,$start,$end-$start);
$testAccounts=['STF-TEST'=>['role'=>'staff','hash'=>'old'],'RES-TEST'=>['role'=>'resident','hash'=>'old'],'ADM-TEST'=>['role'=>'admin','hash'=>'unchanged']];
$logged=[];
function barangay_validate_password(string $value): void {if(strlen($value)<8) throw new InvalidArgumentException('short');}
function barangay_db(): object {return new class {
  public function prepare(string $sql): object {
    if(!str_contains($sql,"role IN ('staff', 'resident')")) throw new LogicException('Reset must limit target roles');
    return new class {
      private int $changed=0;
      public function execute(array $values): void {
        $id=$values[':id'];
        if(isset($GLOBALS['testAccounts'][$id]) && in_array($GLOBALS['testAccounts'][$id]['role'],['staff','resident'],true)) {
          $GLOBALS['testAccounts'][$id]['hash']=$values[':password_hash']; $this->changed=1;
        }
      }
      public function rowCount(): int {return $this->changed;}
    };
  }
};}
function admin_log($user,$action,$module): void {$GLOBALS['logged'][]=$action;}
function admin_redirect(string $view,string $status): never {throw new RuntimeException($view.':'.$status);}
$user=['role'=>'admin'];
$_SESSION=['admin_password_reset_csrf'=>'reset-test-token'];
foreach(['STF-TEST'=>'user-management','RES-TEST'=>'residents','ADM-TEST'=>'residents','UNKNOWN'=>'residents'] as $id=>$redirect) {
  $_POST=['id'=>$id,'password'=>'TestPassword123!','csrf'=>'reset-test-token'];
  $expected=in_array($id,['STF-TEST','RES-TEST'],true) ? 'updated':'invalid';
  try {eval("switch('reset_user_password'){".$branch.'}');} catch(RuntimeException $e) {if($e->getMessage()!==$redirect.':'.$expected) throw $e;}
  if($expected==='updated' && !password_verify($_POST['password'],$testAccounts[$id]['hash'])) throw new LogicException('Password was not hashed correctly');
}
if($testAccounts['ADM-TEST']['hash']!=='unchanged' || count($logged)!==2) throw new LogicException('Invalid target modified or audited as success');
$_POST=['id'=>'RES-TEST','password'=>'OtherPassword123!','csrf'=>'wrong-token'];
try {eval("switch('reset_user_password'){".$branch.'}'); throw new LogicException('CSRF bypass');} catch(InvalidArgumentException $e) {}
if(!password_verify('TestPassword123!',$testAccounts['RES-TEST']['hash'])) throw new LogicException('Rejected request changed password');
echo "PASS: staff and resident resets hash passwords, return to their section, reject administrator/unknown targets, and audit successful resets only. No real accounts changed.\n";
