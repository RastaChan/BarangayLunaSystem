const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
function element(text='') { return {tagName:'DIV',textContent:text,dataset:{},classes:new Set(),styles:{},
  classList:{add(...names){names.forEach(name=>this.owner.classes.add(name));}},
  style:{setProperty(name,value){this.owner.styles[name]=value;}}}; }
function node(text) {const n=element(text);n.classList.owner=n;n.style.owner=n;return n;}
function modal(id, qr=false) {
  const panel=node(),header=node(),button=node('Reset Password'); header.querySelector=()=>node('Staff account details');
  return {id,panel,header,button,querySelector:()=>qr?{}:null,
    querySelectorAll:selector=>selector==='.modal-panel'?[panel]:selector==='.modal-header'?[header]:[button]};
}
const standard=modal('viewStaffModal'),residentId=modal('viewResidentModal'),qrId=modal('qrResultModal',true);
const label=node('Status'),value=node('Active'),row=node();row.querySelector=s=>s===':scope > span'?label:value;
const section=node();section.tagName='SECTION';section.querySelector=()=>node('Nested content');
const nestedOnly=node();nestedOnly.querySelector=()=>null;
const root={children:[row,section,nestedOnly]};let observe;
vm.runInNewContext(fs.readFileSync('js/portal-design.js','utf8'),{
  document:{querySelectorAll:s=>s==='.modal'?[standard,residentId,qrId]:[root]},
  MutationObserver:class{constructor(fn){observe=fn;}observe(){}},encodeURIComponent,
});
assert.equal(standard.panel.classes.has('portal-dialog-panel'),true);
assert.equal(standard.header.classes.has('portal-dialog-header'),true);
assert.equal(standard.button.classes.has('portal-icon-action'),true);
assert.equal(residentId.panel.classes.size,0,'Resident ID preview must retain its design');
assert.equal(qrId.header.classes.size,0,'QR identification preview is excluded');
assert.equal(value.dataset.detailStatus,'positive');
assert.equal(section.classes.size,0,'Attachment and Husay sections must not become narrow detail cards');
assert.equal(nestedOnly.classes.size,0,'Nested session content is not treated as a direct detail value');
value.textContent='Inactive';observe();assert.equal(value.dataset.detailStatus,'negative');
const replacement=node(); replacement.querySelector=s=>s===':scope > span'?node('Address'):node('Text <script> remains text');
root.children.push(replacement);observe();assert.equal(replacement.classes.has('portal-detail-card'),true);
assert.match(replacement.styles['--detail-icon'],/icons\/system\/house\.svg/);
console.log('PASS: shared dialog theme, action icons, changing statuses and dynamic cards; Resident ID previews excluded.');
