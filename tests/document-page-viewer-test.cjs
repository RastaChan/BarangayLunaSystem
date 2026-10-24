const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const source = fs.readFileSync('js/document-page-viewer.mjs','utf8').replace(/^import .*;\s*/,'').replaceAll('import.meta.url',"'http://localhost/portal/js/document-page-viewer.mjs'");
async function run(file, fail=false) {
  const status={textContent:'',hidden:false}, pages={clientWidth:320,children:[],appendChild(n){this.children.push(n);}}, open={hidden:true,href:'',getAttribute(){return this.href;}};
  const rendered=[],calls=[],events={},watermarks=[]; let destroyed=false;
  const context={URL,URLSearchParams,location:{href:'http://localhost/portal/document-preview-viewer.html',search:'?file='+encodeURIComponent(file)},devicePixelRatio:2,Math,Error,GlobalWorkerOptions:{},
    document:{addEventListener(){},getElementById:id=>({status,pages,open}[id]),createElement:()=>({setAttribute(){},getContext:()=>({save(){},translate(){},rotate(){},fillText(text){watermarks.push(text);},restore(){}})})},
    addEventListener:(name,fn)=>events[name]=fn,
    getDocument:options=>{calls.push(options);return {destroy(){destroyed=true;},promise:fail?Promise.reject(new Error('Network')):Promise.resolve({numPages:2,getPage:async n=>({getViewport:({scale})=>({width:600*scale,height:850*scale}),render:()=>({promise:Promise.resolve().then(()=>rendered.push(n))}),cleanup(){}})})};}};
  await vm.runInNewContext('(async()=>{'+source+'})()',context);
  return {status,pages,open,rendered,calls,events,watermarks,destroyed:()=>destroyed};
}
(async()=>{
  const ok=await run('storage/document-previews/preview-test.pdf?v=1');
  assert.deepEqual(ok.rendered,[1,2]);assert.equal(ok.pages.children.length,2);assert.equal(ok.status.hidden,true);assert.equal(ok.open.hidden,true);
  assert.equal(ok.calls[0].isEvalSupported,false);assert.ok(ok.pages.children[0].width<=1600);ok.events.pagehide();assert.equal(ok.destroyed(),true);
  for (const url of ['https://example.com/preview.pdf','storage/document-previews/../../secret.pdf','staff-actions.php']) {const invalid=await run(url);assert.equal(invalid.calls.length,0);assert.equal(invalid.pages.children.length,0);}
  const failure=await run('storage/document-previews/preview-test.pdf',true);assert.equal(failure.open.hidden,false);assert.match(failure.status.textContent,/could not load/);
  const resident=await run('resident-document-preview.php?token='+'a'.repeat(48));assert.equal(resident.watermarks.length,2);assert.equal(resident.open.href,'');assert.equal(resident.open.hidden,true);
  const residentFailure=await run('resident-document-preview.php?token='+'a'.repeat(48),true);assert.equal(residentFailure.open.hidden,true,'Resident preview never exposes an Open PDF link');
  console.log('PASS: all document pages render, bounded canvas size, local-only URLs, error recovery and viewer cleanup');
})().catch(e=>{console.error(e);process.exitCode=1;});
