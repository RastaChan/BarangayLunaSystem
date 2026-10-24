const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const source = fs.readFileSync('js/dashboard.js','utf8');
const handler = source.slice(source.indexOf('  const modalTriggers = new WeakMap();'),source.indexOf('  function closeModal('));
let focused = false;
const classes = new Set();
const modal = {id:'templateManagementModal', parentElement:{},attributes:{},classList:{contains:key=>classes.has(key),add:key=>classes.add(key)},setAttribute(key,value){this.attributes[key]=value;},querySelector(){return {focus(){focused=true;}};},fields:{templateName:'Unsaved template',content:'Existing draft'}};
const body = {style:{},appendChild(node){node.parentElement=this;}};
const context = {closeModal() {},window:{},document:{body,activeElement:{},getElementById:id=>id===modal.id ? modal : null}};
vm.runInNewContext(handler,context);
context.window.barangayOpenModal(modal.id);
assert.equal(modal.parentElement,body);
assert.equal(modal.attributes['aria-modal'],'true');
assert.equal(modal.attributes['aria-hidden'],'false');
assert.equal(body.style.overflow,'hidden');
assert.equal(focused,true);
assert.equal(modal.fields.templateName,'Unsaved template');
assert.equal(modal.fields.content,'Existing draft');
context.window.barangayOpenModal('missing');
for (const path of ['includes/staff-views/issue-documents.php','js/staff-qr-scanner.js']) {
  assert.ok(!fs.readFileSync(path,'utf8').includes("classList.add('is-open')"), `${path} bypasses the shared opener`);
}
console.log('PASS: automatic dialogs move above page layers, retain draft fields, and receive modal focus');
