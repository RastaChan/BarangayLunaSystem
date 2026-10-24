const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const read = file => fs.readFileSync(path.join(__dirname, '..', file), 'utf8');
const source = read('js/dashboard.js');
assert.match(source, /notificationRefreshMs = 2000/);
assert.match(source, /window.addEventListener\('online', refreshNotifications\)/);
assert.match(source, /window.addEventListener\('focus', refreshNotifications\)/);
assert.match(read('includes/sidebar.php'), /data-notification-count-view="documents"/);
assert.match(read('includes/staff-sidebar.php'), /data-notification-count-view="staff-document-requests"/);
assert.match(read('css/dashboard.css'), /\.sidebar\.is-collapsed \.settings-nav-toggle::after\s*\{\s*display: none;\s*margin-left: 0;/);
assert.match(read('notification-feed.php'), /session_write_close\(\)/);
let resolve;
let calls = 0;
let renders = 0, events = 0;
const context = {
  notificationMenus: [{}], document: {hidden: false, dispatchEvent() {events++;}}, notificationRefreshPending: false,
  notificationIdentity: 'staff:1', notificationServerCounts: {}, persistentMenu: null,
  mergeNotificationState() {}, renderNotificationItems() {renders++;}, setSideBadge() {}, syncNotificationMenu() {},
  AbortController, CustomEvent: class {},
  window: {setTimeout: () => 1, clearTimeout: () => {}},
  fetch: (url, options) => {
    calls++;
    assert.equal(options.cache, 'no-store');
    return new Promise(done => { resolve = done; });
  },
};
vm.createContext(context);
vm.runInContext(source.slice(source.indexOf('  async function refreshNotifications()'), source.indexOf('  let residentInitialViewSeen')), context);
(async () => {
  const first = context.refreshNotifications();
  await context.refreshNotifications();
  assert.equal(calls, 1, 'Slow refresh must not overlap');
  resolve({ok: false});
  await first;
  assert.equal(context.notificationRefreshPending, false, 'Failure releases the polling guard');
  context.document.hidden = true;
  await context.refreshNotifications();
  assert.equal(calls, 1, 'Hidden tabs do not poll');
  context.document.hidden = false;
  const retry = context.refreshNotifications();
  assert.equal(calls, 2, 'Polling resumes after returning');
  resolve({ok: false});
  await retry;
  const wrongAccount = context.refreshNotifications();
  resolve({ok: true, json: async () => ({owner: 'staff:2', items: [], sideCounts: {}})});
  await wrongAccount;
  assert.equal(renders, 0, 'A response from another account must be ignored');
  const success = context.refreshNotifications();
  resolve({ok: true, json: async () => ({owner: 'staff:1', items: [], sideCounts: {'staff-feedbacks': 1}})});
  await success;
  assert.equal(renders, 1);
  assert.equal(events, 1, 'A valid notification refresh also triggers the live feedback overview');
  assert.equal(context.notificationServerCounts['staff-feedbacks'], 1);
  console.log('PASS: 2-second polling, overlap protection, hidden-tab pause, recovery, badge aliases and centered settings icon rule.');
})().catch(error => { console.error(error); process.exitCode = 1; });
