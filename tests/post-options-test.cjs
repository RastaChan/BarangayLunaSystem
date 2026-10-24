const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync('js/dashboard.js', 'utf8');
const start = source.indexOf('  function setPostOptionsOpen(');
const end = source.indexOf("  document.querySelectorAll('[data-modal-close]')", start);
assert.ok(start >= 0 && end > start);
const handlers = {}, opened = [];
const classes = () => {
  const values = new Set();
  return {toggle(name, on) {on ? values.add(name) : values.delete(name);}, contains: name => values.has(name)};
};
const document = {activeElement: null, addEventListener(name, handler) {handlers[name] = handler;}};
function makeMenu(id) {
  const card = {classList: classes()};
  const panel = {hidden: true};
  const options = {classList: classes(), closest: () => card};
  const toggle = {attributes: {'aria-expanded': 'false'}, setAttribute(name, value) {this.attributes[name] = value;}, getAttribute(name) {return this.attributes[name];}, focus() {document.activeElement = this;}};
  toggle.closest = selector => selector === '[data-post-toggle]' ? toggle : selector === '[data-post-options]' ? options : null;
  const action = kind => ({handlers: {}, addEventListener(name, handler) {this.handlers[name] = handler;}, hasAttribute: () => false,
    getAttribute: () => kind + 'AnnouncementModal' + id, focus() {document.activeElement = this;},
    closest(selector) {return selector === '[data-post-options]' ? options : selector.includes('[data-modal-open]') ? this : null;}});
  const edit = action('edit'), remove = action('delete');
  options.querySelector = selector => selector === '[data-post-toggle]' ? toggle : selector === '[data-post-actions]' ? panel : edit;
  return {options, panel, toggle, edit, remove, card};
}
const menus = [makeMenu(1), makeMenu(2)];
document.querySelectorAll = selector => selector === '[data-modal-open]' ? menus.flatMap(menu => [menu.edit, menu.remove]) : menus.filter(menu => menu.options.classList.contains('is-open')).map(menu => menu.options);
vm.runInNewContext(source.slice(start, end), {document, closeProfileMenus() {}, closeModal() {}, openModal: id => opened.push(id)});
function click(target) {target.handlers?.click?.(); handlers.click({target});}
const [first, second] = menus;
for (let i = 0; i < 5; i++) {
  click(first.toggle); assert.equal(first.panel.hidden, false); assert.equal(first.toggle.attributes['aria-expanded'], 'true');
  click(first.toggle); assert.equal(first.panel.hidden, true); assert.equal(first.toggle.attributes['aria-expanded'], 'false');
}
click(first.toggle); click(second.toggle);
assert.equal(first.panel.hidden, true, 'Only one post menu can remain open');
assert.equal(second.panel.hidden, false);
click({closest: () => null}); assert.equal(second.panel.hidden, true, 'Clicking outside dismisses the menu');
click(first.toggle); click(first.edit);
assert.equal(opened.at(-1), 'editAnnouncementModal1'); assert.equal(first.panel.hidden, true);
click(second.toggle); click(second.remove);
assert.equal(opened.at(-1), 'deleteAnnouncementModal2'); assert.equal(second.panel.hidden, true);
click(first.toggle); handlers.keydown({key: 'Escape'});
assert.equal(first.panel.hidden, true); assert.equal(document.activeElement, first.toggle);
let prevented = false;
handlers.keydown({key: 'ArrowDown', target: first.toggle, preventDefault() {prevented = true;}});
assert.equal(prevented, true); assert.equal(first.panel.hidden, false); assert.equal(document.activeElement, first.edit);
handlers.focusin({target: {closest: () => null}}); assert.equal(first.panel.hidden, true);
click(first.toggle); handlers['portal-navigation-start'](); assert.equal(first.panel.hidden, true);
console.log('PASS: repeated three-dot clicks, outside dismissal, one menu at a time, Edit/Delete dialogs, keyboard operation, focus changes and navigation cleanup.');
