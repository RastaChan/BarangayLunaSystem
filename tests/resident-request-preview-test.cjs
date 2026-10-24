const fs=require('node:fs'),vm=require('node:vm'),assert=require('node:assert/strict');
let click,observer,resolveFetch,requests=[];
const frame={hidden:true,src:'',removeAttribute(){this.src='';}};
const status={textContent:''};let ariaHidden='false';
const modal={querySelector:s=>s.includes('frame')?frame:status,getAttribute:()=>ariaHidden};
const form={elements:{document_type:{value:'Barangay Certification'},purpose:{value:'School'},preview_csrf:{value:'test-token'}}};
class Payload{constructor(){this.values={};}set(k,v){this.values[k]=v;}}
vm.runInNewContext(fs.readFileSync('js/resident-request-preview.js','utf8'),{
  document:{querySelector:s=>s.includes('form')?form:{addEventListener:(name,fn)=>click=fn},getElementById:()=>modal},
  FormData:Payload,AbortSignal:{timeout:()=>({})},MutationObserver:class{constructor(fn){observer=fn;}observe(){}},
  fetch:async(url,options)=>{requests.push(options.body.values);return new Promise(r=>resolveFetch=r);}
});
(async()=>{
  const first=click();assert.equal(status.textContent,'Preparing your document preview…');assert.equal(frame.hidden,true);
  resolveFetch({ok:true,json:async()=>({success:true,url:'document-preview-viewer.html?file=private-token'})});await first;
  assert.equal(frame.hidden,false);assert.match(frame.src,/private-token/);assert.equal(requests[0].purpose,'School');assert.equal(requests[0].resident_code,undefined,'The resident cannot select another profile');
  const late=click();ariaHidden='true';observer();resolveFetch({ok:true,json:async()=>({success:true,url:'late-url'})});await late;assert.equal(frame.hidden,true);assert.equal(frame.src,'','Closing removes the preview and ignores a late response');
  ariaHidden='false';const error=click();resolveFetch({ok:false,json:async()=>({success:false,message:'Enter purpose first'})});await error;assert.equal(status.textContent,'Enter purpose first');assert.equal(form.elements.purpose.value,'School','Form details survive closing and errors');
  console.log('PASS: resident preview opens without submitting, preserves form values, handles errors and ignores responses after close');
})().catch(e=>{console.error(e);process.exitCode=1;});
