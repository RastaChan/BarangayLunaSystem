const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const document = {querySelectorAll: () => [], body: {}};
let observer;
const context = {window:{}, document, encodeURIComponent,
  MutationObserver: class {constructor(fn) {observer = fn;} observe() {}}
};
vm.createContext(context);
vm.runInContext(fs.readFileSync('js/vendor/phosphor-icons.js','utf8'), context);
let source = fs.readFileSync('js/system-icons.js','utf8').replace('  decorate(document);', '  globalThis.api = {identify, replace, decorate};\n  decorate(document);');
vm.runInContext(source,context);
function svg(closest = {}, attrs = {}) {
  return {dataset:{}, attrs, classList:{contains:()=>false,add(){}}, children:[],
    closest: selector => closest[selector] || null,
    querySelector:()=>null, getAttribute:key=>attrs[key] ?? null,
    setAttribute(key,value){attrs[key]=value;}, append(node){this.children.push(node);},
    innerHTML:'original'};
}
const bell = svg({'[data-notification-toggle]':{}});
assert.equal(context.api.identify(bell),'bell');
const nav = svg({'[data-nav-view]':{dataset:{navView:'husay-schedule'}}});
assert.equal(context.api.identify(nav),'calendar-clock');
const dataIcon = svg(); dataIcon.dataset.iconName = 'cash';
assert.equal(context.api.identify(dataIcon),'hand-coins');
const slash = {hidden:false,style:{opacity:'0'},setAttribute(key,value){this[key]=value;}};
const eye = svg(); eye.querySelector=selector=>selector==='[data-password-eye-slash]'?slash:null;
const clickHandler = () => {}; eye.onclick=clickHandler;
context.api.replace(eye,'eye');
assert.equal(eye.children[0],slash,'Password slash element and its existing references survive replacement');
assert.equal(eye.onclick,clickHandler,'Icons retain the SVG element and event handlers');
assert.equal(eye.attrs.stroke,'none');
assert.equal(eye.attrs.fill,'currentColor');
assert.equal(eye.attrs.viewBox,'0 0 256 256');
const first = eye.innerHTML; context.api.replace(eye,'eye');
context.api.replace(eye,'file-text');
assert.equal(eye.innerHTML,first,'A later context or label cannot change a resolved icon');
assert.equal(eye.innerHTML,first,'Repeat updates keep the existing icon');
const chart = svg({'.chart-container, .chart, [data-chart]':{}},{viewBox:'0 0 24 24'});
const root = {querySelectorAll:selector=>selector==='svg'?[bell,chart]:[]};
context.api.decorate(root);
assert.equal(bell.dataset.systemIcon,'bell');
assert.equal(chart.innerHTML,'original','Data charts are never replaced with UI icons');
assert.match(context.window.barangayIconUrl('save'),/^url\("data:image\/svg\+xml,/);
for (const name of Object.keys(context.window.barangayIcons)) assert.match(context.window.barangayIcons[name],/<(?:path|rect|circle|line|polyline|polygon|ellipse)\b/);
observer([{addedNodes:[{nodeType:1,closest:()=>null,querySelectorAll:selector=>selector==='svg'?[dataIcon]:[]}]}]);
assert.equal(dataIcon.dataset.systemIcon,'hand-coins','New records receive icons through the observer');
assert.match(context.window.barangayIcons.bell, /opacity=/, 'Icons contain a filled two-tone layer');
console.log('PASS: stable two-tone icon library, mediation navigation, dynamic records, unchanged chart data, and password control references.');
