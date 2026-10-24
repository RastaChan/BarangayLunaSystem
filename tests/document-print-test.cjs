const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const source = fs.readFileSync(require('node:path').join(__dirname, '../js/document-print.js'), 'utf8');
function setup(fetchResult, ready = '0') {
  let requests = 0, prints = 0;
  const elements = {};
  for (const selector of ['[data-document-print]','[data-document-frame]','[data-print-document]','[data-open-pdf]','[data-preview-status]','[data-preview-title]','[data-preview-message]','[data-retry-preview]']) {
    elements[selector] = {hidden:false,disabled:false,dataset:{documentId:'12',previewToken:'test-token',previewReady:ready},listeners:{},addEventListener(event,fn){this.listeners[event]=fn;},setAttribute(){},removeAttribute(){},getAttribute(name){return this[name];}};
  }
  elements['[data-document-frame]'].contentWindow = {focus(){},print(){prints++;}};
  const context = {document:{querySelector(selector){return elements[selector];}},URL,URLSearchParams,AbortController,location:{href:'http://localhost/staff-print-issued-document.php?id=12',origin:'http://localhost'},fetch:async()=>{requests++;return await fetchResult(requests);},setTimeout,clearTimeout};
  vm.runInNewContext(source,context);
  return {elements,requests:()=>requests,prints:()=>prints};
}
const tick = () => new Promise(resolve=>setImmediate(resolve));
(async () => {
  const success = setup(async()=>({ok:true,json:async()=>({ok:true,url:'storage/document-previews/print-test.pdf'})})); await tick();
  assert.equal(success.elements['[data-document-frame]'].src,'http://localhost/storage/document-previews/print-test.pdf');
  assert.equal(success.elements['[data-preview-status]'].hidden,true);
  assert.equal(success.prints(),0,'Preparing a preview must never print or release automatically');
  success.elements['[data-document-frame]'].listeners.load();
  assert.equal(success.elements['[data-print-document]'].disabled,false);
  success.elements['[data-print-document]'].listeners.click(); assert.equal(success.prints(),1);
  console.log('PASS: Preview is displayed; printing requires an explicit click.');
  const recovery = setup(async count=>({ok:true,json:async()=>count===1?{ok:false,message:'Conversion timed out'}:{ok:true,url:'storage/document-previews/print-retry.pdf'}})); await tick();
  assert.equal(recovery.elements['[data-retry-preview]'].hidden,false);
  assert.equal(recovery.elements['[data-preview-message]'].textContent,'Conversion timed out');
  await recovery.elements['[data-retry-preview]'].listeners.click();
  assert.equal(recovery.elements['[data-preview-status]'].hidden,true);
  console.log('PASS: Failed conversion shows Retry and recovers after a successful retry.');
  const cached=setup(async()=>{throw Error('Must not request cached preview');},'1'); await tick(); assert.equal(cached.requests(),0);
  console.log('PASS: Cached previews skip conversion requests.');
  const invalid=setup(async()=>({ok:true,json:async()=>({ok:true,url:'https://example.com/print.pdf'})})); await tick();
  assert.equal(invalid.elements['[data-retry-preview]'].hidden,false);
  assert.equal(invalid.elements['[data-document-frame]'].src,undefined);
  console.log('PASS: Unexpected external preview URLs are rejected.');
  const denied=setup(async()=>({ok:false,json:async()=>({ok:false,message:'Please sign in again'})})); await tick();
  assert.equal(denied.elements['[data-preview-message]'].textContent,'Please sign in again');
  console.log('PASS: Expired sessions show a recovery message.');
})().catch(error=>{console.error(error);process.exitCode=1;});
