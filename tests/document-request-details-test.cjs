const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync('js/dashboard.js', 'utf8').replace(/\r\n/g, '\n');
const start = source.indexOf("  document.addEventListener('click', event => {\n      const btn = event.target.closest('[data-view-record]');");
const end = source.indexOf("  document.addEventListener('click', async event => {", start);
assert.ok(start >= 0 && end > start);
const element = () => ({children: [], style: {}, closest: () => null, querySelectorAll: () => [], append(...nodes) { this.children.push(...nodes); }, appendChild(node) { this.children.push(node); }});
const container = element(), title = element(), requestsModal = element(), recordModal = {dataset: {}};
let click, opened, closed;
const data = {id: 'REQ-TEST', resident: '<Resident>', type: 'Barangay Certification', copies: 2, fee_amount: 0, unit_fee: 150.5, status: 'Pending', remarks: '<script>plain text</script>', db_id: 99};
const button = {getAttribute: key => key === 'data-title' ? 'Document request' : JSON.stringify(data), closest: () => requestsModal};
vm.runInNewContext(source.slice(start, end), {
  document: {addEventListener: (name, handler) => click = handler, querySelector: selector => selector === '[data-record-view]' ? container : title,
    createElement: element, getElementById: () => recordModal},
  closeModal: modal => closed = modal, openModal: id => opened = id,
});
click({target: {closest: () => button}});
const fields = Object.fromEntries(container.children.map(row => row.children.map(node => node.textContent)));
assert.equal(fields['Request ID'], 'REQ-TEST');
assert.equal(fields['Resident name'], '<Resident>');
assert.equal(fields['Total fee'], 'PHP 0.00', 'Free documents must show zero rather than disappear');
assert.equal(fields['Fee per copy'], 'PHP 150.50');
assert.equal(fields['Staff remarks'], '<script>plain text</script>', 'Remarks remain plain text');
assert.equal(fields['Db Id'], undefined);
assert.equal(opened, 'viewRecordModal');
assert.equal(closed, requestsModal);
assert.equal(recordModal.dataset.returnRequests, 'true', 'Closing details can return to the request list');
console.log('PASS: readable request labels, formatted fees including free documents, safe remarks, hidden internal fields, and return to list.');
