const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

const source = fs.readFileSync(require('node:path').join(__dirname, '../js/dashboard.js'), 'utf8').replace(/\r\n/g, '\n');
const start = source.indexOf("  document.addEventListener('click', async event => {\n      const btn = event.target.closest('[data-view-feedback]');");
const end = source.indexOf('  /* CRUD edit', start);
assert.ok(start >= 0 && end > start);
const fields = new Map();
const modal = {
  dataset: {readToken: 'test-token'},
  querySelector(selector) {
    if (!fields.has(selector)) fields.set(selector, {
      textContent: '', removeAttribute(name) { delete this[name]; }
    });
    return fields.get(selector);
  },
  set textContent(value) { throw new Error('Popup content was replaced by ' + value); }
};
const buttons = ['FDB-004', 'FDB-003', 'FDB-002'].map(id => ({
  dataset: {},
  value: JSON.stringify({id, resident: id, message: 'Message for ' + id, status: 'Unread'}),
  addEventListener(event, handler) { this.click = handler; },
  getAttribute() { return this.value; },
  setAttribute(name, value) { this.value = value; },
  closest() { return null; }
}));
const pending = [];
let openCount = 0;
let viewHandler, refreshCount = 0;
vm.runInNewContext(source.slice(start, end), {
  document: {
    addEventListener(name, handler) { if (name === 'click') viewHandler = handler; },
    querySelectorAll: () => buttons,
    getElementById: () => modal,
    querySelector(selector) {
      if (selector === '[data-feedback-id]' && 'feedbackId' in modal.dataset) return modal;
      return modal.querySelector(selector);
    }
  },
  openModal: () => openCount++,
  window: {location: {href: 'http://localhost/dashboard.php?view=feedbacks'}},
  URL,
  refreshNotifications: () => refreshCount++,
  showActionToast() {},
  fetch: () => new Promise(resolve => pending.push(() => resolve({ok: true, json: async () => ({ok: true})}))),
  URLSearchParams, AbortSignal
});
function wireButton(button) { button.click = () => viewHandler({target: {closest: () => button}}); return button; }
buttons.forEach(wireButton);

(async () => {
  for (const button of [...buttons, buttons[0], buttons[1]]) {
    const work = button.click();
    pending.shift()();
    await work;
    const data = JSON.parse(button.value);
    assert.equal(fields.get('[data-feedback-id]').textContent, data.id);
    assert.equal(fields.get('[data-feedback-message]').textContent, data.message);
    assert.equal(modal.dataset.activeFeedbackId, data.id);
    assert.ok(!('feedbackId' in modal.dataset));
  }
  assert.equal(openCount, 5);
  // A delayed response for the previous record must not replace the current view.
  const first = buttons[0].click();
  const second = buttons[1].click();
  pending.shift()();
  await first;
  assert.equal(fields.get('[data-feedback-id]').textContent, 'FDB-003');
  pending.shift()();
  await second;
  const fresh = wireButton({...buttons[0], dataset: {}, value: JSON.stringify({id: 'FDB-LIVE', message: 'New live feedback', status: 'Unread'})});
  const liveView = fresh.click(); pending.shift()(); await liveView;
  assert.equal(fields.get('[data-feedback-id]').textContent, 'FDB-LIVE', 'Rows inserted after page load can be opened');
  assert.equal(JSON.parse(fresh.value).status, 'Read');
  assert.equal(refreshCount, 8, 'Reading feedback immediately refreshes notification counts');
  console.log('PASS: repeated feedback views and delayed responses preserve the popup.');
})().catch(error => { console.error(error); process.exitCode = 1; });
