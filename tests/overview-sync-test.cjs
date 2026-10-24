const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const source = fs.readFileSync(path.join(__dirname, '../js/dashboard.js'), 'utf8');
const start = source.indexOf('  const overviewOwner =');
const end = source.indexOf('  /* Sidebar mobile */', start);
assert.ok(start >= 0 && end > start);
let poll, requests = 0, modalOpen = false, menuOpen = false, pendingResponse = null, fail = false, nextOwner = '1';
const owner = {dataset: {overviewOwner: '1'}};
const region = {
  dataset: {liveOverview: 'stats'}, innerHTML: 'old', updates: 0,
  contains: () => false,
  replaceChildren(value) { this.innerHTML = value; this.updates++; }
};
const document = {
  hidden: false, activeElement: null, addEventListener() {}, dispatchEvent() {},
  querySelector: selector => selector === '[data-overview-owner]' ? owner : (modalOpen || menuOpen ? {} : null),
  querySelectorAll: () => [region]
};
vm.runInNewContext(source.slice(start, end), {
  document, URL, AbortSignal, CustomEvent: class {}, navigator: {onLine: true},
  window: {
    location: {href: 'http://localhost/dashboard.php?status=saved'},
    getSelection: () => '', addEventListener() {},
    setInterval(fn, delay) { assert.equal(delay, 5000); poll = fn; }
  },
  fetch: async url => {
    requests++;
    if (pendingResponse) await pendingResponse;
    assert.equal(url.searchParams.has('status'), false);
    if (fail) throw new Error('offline');
    return {ok: true, redirected: false, text: async () => 'page'};
  },
  DOMParser: class {
    parseFromString() {
      return {querySelector: selector => selector === '[data-overview-owner]'
        ? {dataset: {overviewOwner: nextOwner}}
        : {innerHTML: 'new', childNodes: ['new']}};
    }
  }
});
(async () => {
  await poll();
  assert.equal(region.innerHTML, 'new');
  await poll();
  assert.equal(region.updates, 1, 'unchanged data must not redraw');
  menuOpen = true;
  await poll();
  assert.equal(requests, 2, 'An open three-dot menu pauses refreshes');
  menuOpen = false;
  let releaseResponse;
  pendingResponse = new Promise(resolve => {releaseResponse = resolve;});
  region.innerHTML = 'menu-row';
  const inFlight = poll();
  menuOpen = true; releaseResponse(); await inFlight;
  assert.equal(region.innerHTML, 'menu-row', 'A response arriving after a menu opens must not replace its row');
  menuOpen = false; pendingResponse = null;
  modalOpen = true;
  await poll();
  assert.equal(requests, 3, 'open dialogs pause polling');
  modalOpen = false;
  document.hidden = true;
  await poll();
  assert.equal(requests, 3, 'hidden tabs pause polling');
  document.hidden = false;
  region.innerHTML = 'retained';
  nextOwner = '2';
  await poll();
  assert.equal(region.innerHTML, 'retained', 'reject another account');
  nextOwner = '1'; fail = true;
  await poll();
  assert.equal(region.innerHTML, 'retained', 'connection failures preserve data');
  fail = false;
  await poll();
  assert.equal(region.innerHTML, 'new', 'polling recovers');
  region.dataset.liveOverview = 'community-feedback';
  region.innerHTML = 'older-feedback';
  const focused = {closest: () => ({dataset: {feedbackCode: 'FDB-1'}}), matches: selector => selector === '[data-view-feedback]'};
  const nextButton = {focus() {document.activeElement = this;}};
  region.contains = node => node === focused;
  region.querySelectorAll = () => [{dataset: {feedbackCode: 'FDB-1'}, querySelector: () => nextButton}];
  document.activeElement = focused;
  await poll();
  assert.equal(region.innerHTML, 'new', 'New feedback still arrives while a View button holds focus');
  assert.equal(document.activeElement, nextButton, 'Feedback refresh restores the focused action');
  console.log('PASS: overview updates, no-op refresh, modal/hidden pauses, account isolation, connection recovery.');
})().catch(error => {console.error(error); process.exitCode = 1;});
