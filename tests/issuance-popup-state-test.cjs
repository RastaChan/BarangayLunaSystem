const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const source = fs.readFileSync('js/dashboard.js','utf8');
const block = source.slice(source.indexOf('  const issuanceForm ='), source.indexOf('  if (issuanceForm && issuancePaymentMode)'));
const frame = {listeners:{},hidden:true,addEventListener(name,fn){this.listeners[name]=fn;}};
const feedback = {};
const modal = {id:'issuancePrintModal',dataset:{},querySelector:selector=>selector.includes('frame')?frame:feedback};
const generate = {disabled:false};
const form = {values:[['walkin_name','Test Resident'],['copies','2']],listeners:{},
  getAttribute(){return 'staff-actions.php';},querySelector(){return null;},addEventListener(name,fn){this.listeners[name]=fn;}};
let requests=0, opened=0;
const rows = new Map([['123', {remove(){rows.delete('123');}}], ['124', {remove(){rows.delete('124');}}]]);
const count = {}, empty = {};
const list = {querySelectorAll:()=>Array.from(rows.values()),querySelector:selector=>rows.get(selector.match(/"(\d+)"/)[1]),prepend(row){rows.set(row.dataset.preparedDocumentId,row);}};
const context={document:{getElementById:id=>id==='issueDocumentForm'?form:modal,
  createElement(){return {dataset:{},children:[],listeners:{},append(...children){this.children.push(...children);},addEventListener(name,fn){this.listeners[name]=fn;},remove(){rows.delete(this.dataset.preparedDocumentId);}};},
  querySelector:selector=>({'[data-issuance-generate]':generate,'[data-prepared-list]':list,'[data-prepared-count]':count,'[data-prepared-empty]':empty}[selector] || null),querySelectorAll:()=>[]},
  FormData:class {entries(){return form.values;}},openModal(){opened++;},
  fetch:async()=>{requests++;return {ok:true,headers:{get:()=> 'application/json'},json:async()=>({ok:true,url:'staff-print-issued-document.php?id=123',document:{id:125,resident_name:'Test Resident',document_type:'Barangay Certification',document_number:'TEST-125'}})};},
};
vm.runInNewContext(block,context);
(async()=>{
  const submit=()=>form.listeners.submit({preventDefault(){}});
  await submit();
  assert.equal(requests,1); assert.equal(modal.dataset.prepared,'true');
  assert.equal(frame.src,'staff-print-issued-document.php?id=123');
  assert.equal(rows.has('125'),true,'New preparation appears immediately without a refresh');
  await submit();
  assert.equal(requests,1,'Reopening unchanged details must not create another prepared record');
  assert.equal(opened,2); assert.equal(form.values[0][1],'Test Resident');
  frame.contentDocument={body:{hasAttribute:()=>true,dataset:{issuanceStatus:'Ready for Pickup',documentId:'123'}}};
  frame.listeners.load(); assert.equal(modal.dataset.released,'false');
  assert.equal(rows.size,3,'Opening or printing a prepared document must not remove it');
  frame.contentDocument.body.dataset.issuanceStatus='Released'; frame.listeners.load();
  assert.equal(modal.dataset.released,'true','Refresh becomes appropriate only after explicit release');
  assert.equal(rows.has('123'),false); assert.equal(rows.has('124'),true);
  assert.equal(count.textContent,'2'); assert.equal(empty.hidden,true);
  frame.contentDocument.body.dataset.documentId='124'; frame.listeners.load();
  assert.equal(count.textContent,'1');
  frame.contentDocument.body.dataset.documentId='125'; frame.listeners.load();
  assert.equal(count.textContent,'0'); assert.equal(empty.hidden,false);
  frame.contentDocument={body:{hasAttribute:()=>false,dataset:{}}}; frame.listeners.load();
  assert.equal(frame.hidden,true,'Unexpected dashboard/login pages must be hidden');
  assert.match(feedback.textContent,/could not be opened/);
  context.document.getElementById=id=>id==='issueDocumentForm'?null:modal;
  vm.runInNewContext(block,{...context,document:{...context.document}});
  frame.listeners.load();
  console.log('PASS: prepared popup reopens without duplicate generation, keeps applicant details and distinguishes confirmed release.');
})().catch(error=>{console.error(error);process.exitCode=1;});
