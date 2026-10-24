const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const source = fs.readFileSync('includes/staff-views/issue-documents.php', 'utf8');
const functions = source.slice(source.indexOf('  function scheduleWordPreview('), source.indexOf('  function updateAutofill()'));
const timers = [], calls = [], shown = [];
let resolveFirst, active = 0, peak = 0, input = 'first';
const context = vm.createContext({form: {}, FormData: class {constructor(){this.value=input;}},
  setTimeout(fn){timers.push(fn);return timers.length;}, clearTimeout(){},
  showWordPreviewLoading(){}, showWordPreview(url){shown.push(url);}, setWordPreviewStatus(){},
  preview:{classList:{remove(){}}},
  fetch:async(url, options)=>{
    calls.push(options.body.value); active++; peak=Math.max(peak,active);
    let result;
    if (calls.length===1) result=await new Promise(resolve=>{resolveFirst=resolve;});
    else result={success:true,url:options.body.value+'.pdf'};
    active--; return {json:async()=>result};
  },
});
vm.runInContext('let wordPreviewTimer=null, wordPreviewRequestId=0, wordPreviewPending=null, wordPreviewRunning=false;'+functions,context);
const tick=()=>new Promise(resolve=>setImmediate(resolve));
(async()=>{
  vm.runInContext('scheduleWordPreview()',context); timers.shift()(); await tick();
  input='middle'; vm.runInContext('scheduleWordPreview()',context); timers.shift()();
  input='latest'; vm.runInContext('scheduleWordPreview()',context); timers.shift()();
  resolveFirst({success:true,url:'outdated.pdf'}); await tick(); await tick();
  assert.deepEqual(calls,['first','latest']); assert.equal(peak,1);
  assert.deepEqual(shown,['latest.pdf'],'Outdated conversion must never overwrite current details');
  let attempts=0;
  context.fetch=async()=>({json:async()=>++attempts===1?{code:'busy'}:{success:true,url:'recovered.pdf'}});
  vm.runInContext('scheduleWordPreview()',context); timers.shift()(); await tick();
  timers.shift()(); await tick(); await tick();
  assert.equal(attempts,2); assert.equal(shown.at(-1),'recovered.pdf');
  console.log('PASS: preview conversions are serialized, intermediate edits are coalesced, stale previews ignored and busy conversion retried.');
})().catch(error=>{console.error(error);process.exitCode=1;});
