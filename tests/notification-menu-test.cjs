const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync('js/dashboard.js', 'utf8');
class Element {
  constructor() { this.children = []; this.attributes = {}; this.dataset = {}; this.style = {}; this.events = {}; this.scrollTop = 0; this.classList = {toggle() {}}; }
  setAttribute(name, value) { this.attributes[name] = value; }
  getAttribute(name) { return this.attributes[name] ?? null; }
  hasAttribute(name) { return name in this.attributes; }
  append(...nodes) { nodes.forEach(node=>{node.parent=this;}); this.children.push(...nodes); }
  appendChild(node) { this.append(node); }
  set innerHTML(value) { this.children = []; }
  contains(node) { return this === node || this.children.some(child => child.contains(node)); }
  querySelectorAll(selector) { const attribute = selector.slice(1, -1); return this.children.flatMap(child => [...(child.hasAttribute(attribute) ? [child] : []), ...child.querySelectorAll(selector)]); }
  querySelector(selector) {return this.querySelectorAll(selector)[0] || null;}
  closest(selector) {return this.hasAttribute(selector.slice(1,-1)) ? this : this.parent?.closest(selector) || null;}
  addEventListener(name, callback) {this.events[name]=callback;}
  focus() { document.activeElement = this; }
}
const list = new Element();
const menu = {dataset: {}, querySelector: () => list};
const document = {activeElement: null, createElement: () => new Element()};
const context = {document, readNotificationSet: () => new Set(), notificationDismissedKey: 'dismissed', notificationReadKey: 'read', notificationUnreadKey: 'unread', notificationViewFromHref: () => 'documents', bindNotificationItem() {}, bindNotificationDismiss() {}};
vm.createContext(context);
vm.runInContext(source.slice(source.indexOf('  function renderNotificationItems('), source.indexOf('  async function refreshNotifications()')), context);
const items = Array.from({length: 35}, (_, id) => ({id: 'doc-' + id, title: 'Document', status: 'Unread'}));
items.push({id: 'feedback-1', title: 'New feedback', view: 'feedbacks', status: 'Unread'});
list.scrollTop = 120;
context.renderNotificationItems(menu, items);
assert.equal(list.children.length, 36, 'Feedback must remain reachable when there are more than 30 document alerts');
assert.equal(list.scrollTop, 120);
const original = list.children[0];
context.renderNotificationItems(menu, items);
assert.equal(list.children[0], original, 'Unchanged polls preserve notification nodes');
document.activeElement = list.querySelectorAll('[data-notification-id]')[35];
context.renderNotificationItems(menu, [...items, {id: 'new', status: 'Unread'}]);
assert.equal(document.activeElement.getAttribute('data-notification-id'), 'feedback-1', 'New alerts preserve the focused alert');
assert.equal(list.scrollTop, 120);

const optionEvents = {}, removedIds = [];
document.addEventListener=(name, callback)=>{optionEvents[name]=callback;};
document.querySelectorAll=selector=>list.querySelectorAll(selector);
context.dismissNotification=id=>removedIds.push(id);
vm.runInContext(source.slice(source.indexOf('  function bindNotificationDismiss('),source.indexOf('  function syncNotificationMenu(')),context);
let dots=list.querySelectorAll('[data-notification-options-toggle]')[0];
assert.equal(dots.hasAttribute('data-notification-dismiss'),false,'Three dots cannot directly remove an alert');
const optionClick=target=>optionEvents.click({target,preventDefault(){},stopPropagation(){}});
optionClick(dots);
assert.equal(removedIds.length,0,'Opening notification options does not dismiss the notification');
assert.equal(dots.getAttribute('aria-expanded'),'true');
assert.equal(dots.closest('[data-notification-row]').querySelector('[data-notification-options]').hidden,false);
document.activeElement=dots;
context.renderNotificationItems(menu,[...items,{id:'newer',title:'New arrival',status:'Unread'}]);
dots=list.querySelectorAll('[data-notification-options-toggle]')[0];
assert.equal(dots.getAttribute('aria-expanded'),'true','Live arrivals preserve the open options');
assert.equal(document.activeElement,dots,'Live arrivals restore focus to the options button');
const unreadStates = [];
context.setNotificationReadState=(id, unread)=>unreadStates.push([id, unread]);
context.notificationMenus=[];
context.syncNotificationMenu=()=>{};
const unreadAction=dots.closest('[data-notification-row]').querySelector('[data-notification-unread]');
assert.equal(unreadAction.textContent,'Mark as read');
optionClick(unreadAction);
assert.deepEqual(unreadStates,[['doc-0',false]]);
unreadAction.setAttribute('data-mark-unread','true');
optionClick(unreadAction);
assert.deepEqual(unreadStates,[['doc-0',false],['doc-0',true]],'Read and unread actions toggle in both directions');
assert.equal(dots.getAttribute('aria-expanded'),'false','Marking unread closes the menu');
assert.equal(removedIds.length,0,'Marking unread never removes the notification');
const remove=dots.closest('[data-notification-row]').querySelector('[data-notification-dismiss]');
context.bindNotificationDismiss(remove);
remove.events.click({preventDefault(){},stopPropagation(){}});
assert.deepEqual(removedIds,['doc-0'],'Only the explicit Remove notification action dismisses the chosen notification');
optionClick(new Element()); assert.equal(dots.getAttribute('aria-expanded'),'false');
optionClick(dots);optionEvents.keydown({key:'Escape'});assert.equal(dots.getAttribute('aria-expanded'),'false');

let bottom = 80;
const panel = {style: {}};
const anchoredMenu = {classList: {contains: () => true}, querySelector: selector => selector === '[data-notification-toggle]' ? {getBoundingClientRect: () => ({right: 290, bottom})} : panel};
context.document.documentElement = {clientWidth: 320};
context.window = {innerHeight: 640};
vm.runInContext(source.slice(source.indexOf('  function positionNotificationMenu('), source.indexOf('  let notificationPositionFrame')), context);
context.positionNotificationMenu(anchoredMenu);
assert.equal(panel.style.width, '296px');
assert.equal(panel.style.left, '12px');
assert.equal(panel.style.top, '90px');
assert.equal(panel.style.maxHeight, '538px');
bottom = 60; context.positionNotificationMenu(anchoredMenu);
assert.equal(panel.style.top, '70px', 'Panel follows the bell when its screen position changes');
context.document.documentElement.clientWidth = 1440;
context.positionNotificationMenu(anchoredMenu);
assert.equal(panel.style.width, '460px');

const nav = {getAttribute: () => 'feedbacks'};
const badgeCounts = [];
Object.assign(context, {
  notificationSeenKey: 'seen', notificationReadKey: 'read', notificationUnreadKey: 'unread', notificationServerCounts: {feedbacks: 1},
  ensureNotificationBadge: () => ({}), ensureNotificationEmptyState() {}, formatCount: String,
  setSideBadge: (view, count) => badgeCounts.push(count),
});
document.querySelectorAll = selector => selector === '[data-nav-view]' ? [nav] : [];
document.querySelector = () => null;
vm.runInContext(source.slice(source.indexOf('  function syncNotificationMenu('), source.indexOf('  function markNotificationsSeen(')), context);
context.syncNotificationMenu({querySelectorAll: () => [], querySelector: () => null});
assert.equal(badgeCounts.at(-1), 1, 'Feedback badge reflects unread feedback even after the bell was seen');
context.notificationServerCounts.feedbacks = 0;
context.syncNotificationMenu({querySelectorAll: () => [], querySelector: () => null});
assert.equal(badgeCounts.at(-1), 0, 'Reading feedback clears its badge');
console.log('PASS: feedback visibility, stable polling, focus and scroll retention, phone/desktop bell anchoring, and unread badge counts.');

const stateStore = {read: ['doc-0'], unread: []};
const stateContext = {
  notificationReadKey: 'read', notificationUnreadKey: 'unread', notificationKinds: {read: 'read', unread: 'unread'},
  memoryNotificationState: stateStore,
  confirmedNotificationState: {read: new Set(['doc-0']), unread: new Set()},
  readNotificationSet: key => new Set(stateStore[key]),
  saveNotificationSet: (key, values) => {stateStore[key] = [...values];},
  localStorage: {setItem() {}}
};
vm.createContext(stateContext);
vm.runInContext(source.slice(source.indexOf('  function setNotificationReadState('), source.indexOf('  function bindNotificationItem(')), stateContext);
stateContext.setNotificationReadState('doc-0', true);
assert.equal(stateStore.read.includes('doc-0'), false);
assert.equal(stateStore.unread.includes('doc-0'), true);
stateContext.setNotificationReadState('doc-0', false);
assert.equal(stateStore.unread.includes('doc-0'), false);
assert.equal(stateStore.read.includes('doc-0'), true);
console.log('PASS: marking unread clears read state; opening the item reverses it.');
