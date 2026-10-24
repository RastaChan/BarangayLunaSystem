const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
function option(value, district) { return {value, dataset: {district}, cloneNode() { return option(value, district); }}; }
function group() {
  const district = {value: '', addEventListener(event, fn) { this.change = fn; }};
  const purok = {value: '', options: [option('', ''), option('1', '1'), option('2', '2'), option('3', '0')], addEventListener(event, fn) { this.change = fn; }, replaceChildren(...options) { this.options = options; }, dispatchEvent() { this.synced = true; this.change(); }};
  return {district, purok, querySelector: selector => selector.includes('district') ? district : purok};
}
const registration = group();
const existing = group();
vm.runInNewContext(fs.readFileSync('js/resident-locations.js', 'utf8'), {document: {querySelectorAll: () => [registration, existing]}, Event: class {}});
registration.purok.value = '2';
registration.district.value = '1';
registration.district.change();
assert.deepEqual(registration.purok.options.map(item => item.value), ['', '1', '3']);
assert.equal(registration.purok.value, '');
assert.equal(registration.purok.synced, true);
assert.equal(existing.purok.options.length, 4);
registration.purok.value = '3';
registration.district.value = '2';
registration.district.change();
assert.equal(registration.purok.value, '3');
assert.deepEqual(registration.purok.options.map(item => item.value), ['', '2', '3']);
console.log('PASS: district-dependent options, stale selection reset, unassigned options, independent forms and mobile change notification.');
