const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const source = fs.readFileSync('js/dashboard.js', 'utf8');
const navigation = source.slice(source.indexOf('  // Close transient menus before'), source.indexOf('  if (notificationMenus.length)', source.indexOf('  // Close transient menus before')));
const windowEvents = new Map();
const linkEvents = new Map();
let menus = {sidebar: true, profile: true, notifications: true, select: true};
let marked = '';
let selectCloseArgs;
const documentEvents = new Map();
const link = {matches: () => true, getAttribute: () => 'my-document-requests', addEventListener: (name, callback) => linkEvents.set(name, callback)};
const document = {
  querySelectorAll: () => [link],
  addEventListener: (name, callback) => documentEvents.set(name, callback),
  dispatchEvent: event => documentEvents.get(event.type)?.(event),
};
const context = vm.createContext({document, window: {addEventListener: (name, callback) => windowEvents.set(name, callback)},
  CustomEvent: class {constructor(type) {this.type = type;}},
  closeSidebar: () => menus.sidebar = false,
  closeProfileMenus: () => menus.profile = false,
  closeNotificationMenus: () => menus.notifications = false,
  markNotificationsSeenForView: view => marked = view,
  close: (...args) => {selectCloseArgs = args; menus.select = false;},
});
const selectSource = fs.readFileSync('js/mobile-select.js', 'utf8');
const selectHooks = selectSource.slice(selectSource.indexOf("  document.addEventListener('portal-navigation-start'"), selectSource.indexOf('  window.visualViewport', selectSource.indexOf("  document.addEventListener('portal-navigation-start'")));
vm.runInContext(selectHooks, context);
// Both scripts also register pagehide/pageshow; capture each listener.
const selectHide = windowEvents.get('pagehide');
const selectShow = windowEvents.get('pageshow');
vm.runInContext(navigation, context);
linkEvents.get('click')();
assert.deepEqual(menus, {sidebar: false, profile: false, notifications: false, select: false});
assert.deepEqual(selectCloseArgs, [false, true], 'Popover closes immediately without a lingering exit animation');
assert.equal(marked, 'my-document-requests', 'Notification acknowledgement still uses the chosen section');
menus = {sidebar: true, profile: true, notifications: true, select: true};
windowEvents.get('pagehide')(); selectHide();
assert.ok(Object.values(menus).every(value => !value), 'Leaving the page clears all navigation overlays');
menus = {sidebar: true, profile: true, notifications: true, select: true};
windowEvents.get('pageshow')({persisted: true}); selectShow({persisted: true});
assert.ok(Object.values(menus).every(value => !value), 'Back/forward cache does not restore an open covering menu');
console.log('PASS: mobile section navigation and cached-page restore clear menus and popovers.');
const classSet = () => {
  const items = new Set();
  return {toggle(name,on) {on ? items.add(name) : items.delete(name);},contains: name => items.has(name)};
};
const sidebar = {style:{},classList:classSet(),querySelectorAll:()=>[],getBoundingClientRect:()=>({})};
const sidebarToggle = {attributes:{},setAttribute(name,value) {this.attributes[name]=value;}};
const body = {classList:classSet()}, main = {style:{}};
const desktop = {matches:true,addEventListener(name,callback) {this.change=callback;}};
const saved = new Map();
const sidebarContext = vm.createContext({sidebar,sidebarToggle,document:{body,querySelector:()=>main},window:{matchMedia:()=>desktop},localStorage:{getItem:key=>saved.get(key),setItem:(key,value)=>saved.set(key,value)},closeSidebar() {}});
const sidebarControls=source.slice(source.indexOf('  /* The header menu button controls'),source.indexOf('  /* Cancel from the current height'));
vm.runInContext(sidebarControls,sidebarContext);
assert.equal(sidebarToggle.attributes['aria-controls'],'appSidebar');
sidebarContext.toggleSidebarWidth();
assert.equal(sidebar.classList.contains('is-collapsed'),true);
assert.equal(body.classList.contains('sidebar-is-collapsed'),true);
assert.equal(sidebarToggle.attributes['aria-label'],'Expand sidebar');
assert.equal(saved.get('barangaySidebarCollapsed'),'1');
desktop.matches=false;desktop.change();
assert.equal(sidebar.classList.contains('is-collapsed'),false,'Phones use the complete slide-out menu');
assert.equal(sidebarToggle.attributes['aria-label'],'Open menu');
desktop.matches=true;desktop.change();
assert.equal(sidebar.classList.contains('is-collapsed'),true,'Desktop retains the chosen collapsed state');
sidebarContext.toggleSidebarWidth();
assert.equal(sidebar.classList.contains('is-collapsed'),false);
assert.equal(sidebarToggle.attributes['aria-expanded'],'true');
for (const file of ['sidebar.php','staff-sidebar.php','resident-sidebar.php']) assert.equal(fs.readFileSync('includes/'+file,'utf8').includes('id="sidebarCollapseBtn"'),false,'The extra arrow button is removed from '+file);
console.log('PASS: header menu controls desktop collapse without an arrow button, retains preferences, and restores the full menu on phones.');
