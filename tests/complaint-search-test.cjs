const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const input = {value: '', events: {}, addEventListener(name, handler) {this.events[name] = handler;}};
const empty = {hidden: true};
const rows = [
  {dataset: {complaintSearch: 'CMP-001 Maria Deloy Loud noise Open'}, textContent: 'Open In Progress Resolved Closed'},
  {dataset: {complaintSearch: 'CMP-002 Juan Deloy Street light Resolved'}, textContent: 'Open In Progress Resolved Closed'},
];
const list = {querySelectorAll: () => rows};
const root = {dataset: {}, querySelector: selector => selector === 'tbody' ? list : selector.includes('input') ? input : empty};
let refresh;
vm.runInNewContext(fs.readFileSync('js/complaint-list.js', 'utf8'), {
  document: {querySelectorAll: () => [root]}, MutationObserver: class {constructor(handler) {refresh = handler;} observe() {}},
});
input.value = '  MARIA  '; input.events.input();
assert.equal(rows[0].hidden, false); assert.equal(rows[1].hidden, true);
input.value = 'Resolved'; input.events.input();
assert.equal(rows[0].hidden, true, 'Status searches must ignore the other options in a status dropdown');
assert.equal(rows[1].hidden, false);
input.value = 'deloy light'; input.events.input(); assert.equal(rows[1].hidden, false);
input.value = 'CMP-001'; input.events.input(); assert.equal(rows[0].hidden, false);
input.value = 'unknown'; input.events.input(); assert.equal(empty.hidden, false);
rows.push({dataset: {complaintSearch: 'CMP-003 Unknown issue Open'}}); refresh();
assert.equal(rows[2].hidden, false); assert.equal(empty.hidden, true);
input.value = ''; input.events.search(); assert.ok(rows.every(row => !row.hidden));
rows.length = 0; refresh(); assert.equal(empty.hidden, true, 'An empty list keeps the existing no-complaints message');
console.log('PASS: complaint ID/name/subject/status searches, case and whitespace, dropdown exclusion, no matches, clearing, and updated rows.');
