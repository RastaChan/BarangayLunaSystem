const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const cards = [
  {dataset: {staffName:'Zoe Staff', staffCreated:'2026-10-10'}},
  {dataset: {staffName:'Ana Staff', staffCreated:'2026-10-09'}},
  {dataset: {staffName:'Ben Staff', staffCreated:'2026-10-08'}},
];
let ordered = [];
let change;
const sort = {value:'latest', addEventListener: (name, handler) => change = handler};
const list = {querySelectorAll: () => cards, appendChild: card => ordered.push(card)};
vm.runInNewContext(fs.readFileSync('js/staff-directory.js','utf8'), {document:{querySelector: selector => selector === '[data-staff-directory]' ? list : sort}});
for (const [mode, names] of [
  ['oldest',['Ben Staff','Ana Staff','Zoe Staff']],
  ['name',['Ana Staff','Ben Staff','Zoe Staff']],
  ['latest',['Zoe Staff','Ana Staff','Ben Staff']],
]) {
  sort.value = mode; ordered = []; change();
  assert.deepEqual(ordered.map(card => card.dataset.staffName), names);
  assert.ok(ordered.every(card => cards.includes(card)), 'Sorting preserves existing View button listeners');
}
console.log('PASS: staff sorting by latest, oldest and name preserves account cards.');
