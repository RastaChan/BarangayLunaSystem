const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const source = fs.readFileSync(path.join(__dirname, '../includes/resident-views/request-document.php'), 'utf8');
const script = source.match(/<script>([\s\S]*?)<\/script>/)[1].replace(/<\?= json_encode\(\$selectedDocumentFee\) \?>/, '12.35');
function control(value = '') {
  return {value, style: {}, listeners: {}, addEventListener(event, fn) { this.listeners[event] = fn; }, setAttribute() {}, querySelector() { return null; }};
}
const copies = control('1'), total = control(), trigger = control();
const choices = [30, 0, ''].map(fee => ({...control(), getAttribute(name) {
  return {'data-value': 'Certificate of Indigency', 'data-fee-amount': String(fee)}[name] || '';
}}));
const input = control('Barangay Certification');
const pickerFields = {'[data-resident-document-type-input]': input, '[data-resident-document-type-trigger]': trigger, '[data-resident-document-type-label]': control(), '[data-resident-document-type-options]': control()};
const formFields = {'[name="copies"]': copies, '#requestTotal': total};
const picker = {...control(), getAttribute: () => '', querySelector: key => pickerFields[key], querySelectorAll: () => choices, closest: () => ({querySelector: key => formFields[key] || control()})};
vm.runInNewContext(script, {document: {querySelector: () => picker, addEventListener() {}}, localStorage: {getItem: () => null, setItem() {}}});
assert.equal(total.value, 'PHP 12.35');
copies.value = '3'; copies.listeners.input();
assert.equal(total.value, 'PHP 37.05');
choices[0].listeners.click();
assert.equal(total.value, 'PHP 90.00');
assert.equal(copies.value, '3');
choices[1].listeners.click();
assert.equal(total.value, 'PHP 0.00');
choices[2].listeners.click();
assert.equal(total.value, 'Not set yet');
choices[0].listeners.click();
copies.value = '0'; copies.listeners.input();
assert.equal(total.value, 'Enter 1 to 100 copies');
console.log('PASS: resident copy totals, type changes, free/unset fees and invalid counts.');
