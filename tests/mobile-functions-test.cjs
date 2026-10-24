const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const read = name => fs.readFileSync(path.join(__dirname, '..', name), 'utf8');

const selects = read('js/mobile-select.js');
const modal = {appendChild(node) { node.parentElement = this; }};
const body = {appendChild(node) { node.parentElement = this; }};
const menu = {parentElement: body, querySelector: () => null, showPopover() { throw new Error('unsupported'); }};
const selectContext = {active: null, close() {}, sync() {}, renderOptions() {}, position() {}, menu, document: {body}};
vm.createContext(selectContext);
vm.runInContext(selects.slice(selects.indexOf('  function open(entry)'), selects.indexOf('  function enhance(select)')), selectContext);
selectContext.open({select: {closest: () => modal}, button: {hidden: false, disabled: false, setAttribute() {}}});
assert.equal(menu.parentElement, modal, 'Dropdown stays inside its modal, not the inert body');
assert.equal(menu.hidden, false, 'Dropdown still opens when Popover API is unavailable');
selectContext.open({select: {closest: () => null}, button: {hidden: false, disabled: false, setAttribute() {}}});
assert.equal(menu.parentElement, body, 'Non-modal dropdown returns to body');

function element() { return {events: {}, dataset: {}, hidden: false, disabled: false, addEventListener(name, fn) { this.events[name] = fn; }}; }
const start = element(), stop = element(), video = element(), status = element();
video.play = async () => {};
const events = {}, windowEvents = {};
const nodes = {startStaffQrScanner: start, stopStaffQrScanner: stop, staffQrVideo: video, staffQrCameraStatus: status};
let resolveCamera, requests = 0, stopped = 0;
class Detector { async detect() { return []; } }
const context = {
  document: {hidden: false, querySelector: () => ({dataset: {}}), getElementById: id => nodes[id] || null, addEventListener: (event, fn) => { events[event] = fn; }},
  window: {BarcodeDetector: Detector, addEventListener: (event, fn) => { windowEvents[event] = fn; }},
  BarcodeDetector: Detector,
  navigator: {mediaDevices: {getUserMedia() { requests++; return new Promise(resolve => { resolveCamera = resolve; }); }}},
  setTimeout: () => 1, clearTimeout() {}, console,
};
vm.createContext(context);
vm.runInContext(read('js/staff-qr-scanner.js'), context);
const stream = () => ({getTracks: () => [{stop() { stopped++; }}]});
(async () => {
  const pending = start.events.click();
  await start.events.click();
  assert.equal(requests, 1, 'Repeated taps do not request multiple cameras');
  context.document.hidden = true;
  events.visibilitychange();
  resolveCamera(stream());
  await pending;
  assert.equal(stopped, 1, 'Late camera permission result is stopped after page is hidden');
  assert.equal(start.disabled, false);
  context.document.hidden = false;
  const resume = start.events.click();
  resolveCamera(stream());
  await resume;
  assert.equal(start.disabled, true);
  windowEvents.pagehide();
  assert.equal(stopped, 2, 'Camera stops on mobile pagehide');
  assert.equal(video.srcObject, null);
  assert.match(read('js/dashboard.js'), /ResizeObserver[\s\S]*observe\(profileCropStage\)/);
  assert.match(read('js/dashboard.js'), /if \(profileCropState.url !== selectionUrl\) return/);
  assert.match(read('js/dashboard.js'), /image.onerror = \(\) =>/);
  assert.match(read('js/dashboard.js'), /if \(profilePhotoLoading\) \{\s*event.preventDefault\(\)/);
  console.log('PASS: modal dropdown fallback, duplicate camera taps, hidden-page cancellation, pagehide cleanup and crop resize hook.');
})().catch(error => { console.error(error); process.exitCode = 1; });
