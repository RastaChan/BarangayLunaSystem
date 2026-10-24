const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync(require('node:path').join(__dirname, '../js/dashboard.js'), 'utf8');
const start = source.indexOf('  /* Modals */');
const end = source.indexOf('  const modalTriggers', start);
let click, opened;
const request = {}, remarks = {};
const modal = {id: 'requestRejectionRemarksModal', querySelector: selector => selector === '[data-rejection-request]' ? request : remarks};
vm.runInNewContext(source.slice(start, end), {
  document: {addEventListener: (type, handler) => { click = handler; }, getElementById: () => modal},
  closeProfileMenus() {}, openModal: id => { opened = id; }
});
const press = dataset => click({target: {closest: () => ({dataset})}});
press({requestId: 'REQ-1', requestType: 'Certificate', remarks: 'Missing ID\nProvide <supporting details>'});
assert.equal(opened, modal.id);
assert.equal(request.textContent, 'REQ-1 · Certificate');
assert.equal(remarks.textContent, 'Missing ID\nProvide <supporting details>');
press({requestId: 'REQ-2', requestType: 'Clearance', remarks: '   '});
assert.match(remarks.textContent, /No rejection remarks were provided/);
assert.equal(request.textContent, 'REQ-2 · Clearance');
press({requestId: 'REQ-3', requestType: 'Certificate', remarks: 'Updated rejection reason'});
assert.equal(remarks.textContent, 'Updated rejection reason');
console.log('PASS: rejection popup uses current request, safe plain text, multiline remarks, empty fallback and delegated clicks after refresh.');
