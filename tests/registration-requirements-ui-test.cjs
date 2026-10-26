const fs=require('node:fs'),vm=require('node:vm'),assert=require('node:assert/strict');
const handlers={};
vm.runInNewContext(fs.readFileSync('js/registration-requirements.js','utf8'),{
 document:{addEventListener:(event,handler)=>handlers[event]=handler},
 Event:class{constructor(type,options){this.type=type;this.bubbles=options.bubbles;}},
});
function item(initial='Not Submitted') {
 const status={value:initial,events:[],dispatchEvent(event){this.events.push(event);}};
 const row={querySelector:()=>status};
 const input={files:[],closest:selector=>selector==='[data-registration-requirement]'?row:input};
 return {input,status};
}
const change=input=>handlers.change({target:input});
const first=item(), second=item('Not Applicable');
first.input.files=[{}];change(first.input);assert.equal(first.status.value,'Submitted');assert.equal(second.status.value,'Not Applicable');
assert.equal(first.status.events[0].type,'change');assert.equal(first.status.events[0].bubbles,true,'Dropdown label receives its change event');
first.input.files=[{replacement:true}];change(first.input);
first.input.files=[];change(first.input);assert.equal(first.status.value,'Not Submitted','Clearing a pending file restores the earlier status');
second.input.files=[{}];change(second.input);assert.equal(second.status.value,'Submitted');
second.input.files=[];change(second.input);assert.equal(second.status.value,'Not Applicable');
const saved=item('Submitted');saved.input.files=[{}];change(saved.input);saved.input.files=[];change(saved.input);assert.equal(saved.status.value,'Submitted','Clearing a replacement keeps the saved Submitted state');
first.input.files=[{}];change(first.input);handlers.reset({target:{querySelectorAll:()=>[first.input]}});
first.status.value='Not Applicable';first.input.files=[];change(first.input);assert.equal(first.status.value,'Not Applicable');
console.log('PASS: selecting attachments immediately marks Submitted, updates dropdowns, isolates cards and restores status on clear or reset.');
