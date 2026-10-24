const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const read = file => fs.readFileSync(path.join(__dirname, '..', file), 'utf8');
assert(!read('login.php').includes('id="otpForm"'));
assert(read('login.php').includes('data-modal-open="passwordHelpModal"'));
assert(!read('js/main.js').includes('dashboard.php?role=admin'));
const resident = read('resident-actions.php').split("case 'change_password':")[1].split('default:')[0];
assert(resident.includes('barangay_change_own_password'));
assert(resident.includes('hash_equals'));
assert(!resident.includes('password_hash('));
for (const file of ['admin-actions.php', 'staff-actions.php']) {
  const handler = read(file).split("case 'send_sms':")[1].split('case ')[0];
  assert(handler.includes("$_SESSION['sms_draft']"));
  assert(!handler.includes('SMSAlertsHelper::sendSMS'));
}
assert(read('includes/sms-alerts-view.php').includes("sms_render_automatic('ready'"));
assert(read('includes/sms-alerts-view.php').includes('d.archived_at IS NULL'));
assert(read('js/sms-workspace.js').includes('row.ready_message'));
assert(read('sms-actions.php').includes('WHERE request_code = :code AND archived_at IS NULL'));
console.log('PASS: legacy recovery, password, SMS handoff, saved templates and archived-request guards.');
