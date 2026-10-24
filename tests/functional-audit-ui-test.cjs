const assert = require('node:assert/strict');
const fs = require('node:fs');
const read = name => fs.readFileSync(require('node:path').join(__dirname, '..', name), 'utf8');
const dashboard = read('js/dashboard.js');
assert(!dashboard.includes('(demo)'));
assert(dashboard.includes("fetch(form.action, {method: 'POST'"));
assert(dashboard.includes("row.dataset.searchText"));
const profile = read('includes/profile-dropdown.php');
assert(profile.includes('action="account-security-actions.php"'));
assert(profile.includes('name="csrf"'));
const search = read('includes/registration-record-cards.php');
assert(search.includes('name="registration_search"'));
assert(search.includes('id="registrationRecords"'));
assert(search.includes('data-registration-results'));
for (const name of ['request-document', 'submit-complaint']) {
  assert(read(`includes/resident-views/${name}.php`).includes('enctype="multipart/form-data"'));
}
for (const name of ['includes/views/complaints.php', 'includes/staff-views/staff-complaints.php']) {
  const source = read(name);
  assert(source.includes('complaint-actions.php'));
  assert(source.includes('barangay_attachment_files'));
  assert(read('includes/complaint-actions.php').includes('data-view-record'));
  assert(dashboard.includes("remarks.name = 'remarks'"));
}
assert(read('includes/staff-views/staff-complaints.php').includes('id="viewRecordModal"'));
assert(read('record-attachment.php').includes('barangay_attachment_authorized'));
console.log('PASS: real password submission, searchable residents, multipart uploads, complaint views/remarks and protected downloads are wired.');
