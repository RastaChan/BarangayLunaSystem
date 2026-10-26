const fs = require('node:fs'), vm = require('node:vm'), assert = require('node:assert/strict');
const events = {}, calls = [], historyCalls = [];
const timers = new Map(); let timerId = 0;
const fields = Object.fromEntries(['registration_search','registration_status','registration_purok'].map(name => [name,{value:'',events:{},addEventListener(event,handler){this.events[event]=handler;},dispatchEvent(){}}]));
const apply = {disabled:false}, count = {textContent:'Showing 1–1 of 1'}, heading = {textContent:'Resident registration records (1)'}, message = {hidden:true,textContent:''};
const results = {childNodes:['original'],setAttribute(){},removeAttribute(){},replaceChildren(...nodes){this.childNodes = nodes;}};
const form = {elements:fields,querySelector:()=>apply,querySelectorAll:selector=>selector==='select'?[fields.registration_status]:[fields.registration_search,fields.registration_purok],addEventListener:(name,handler)=>events[name]=handler};
const nodes = {'.registration-card-filters':form,'[data-registration-results]':results,'[data-registration-count]':count,'[data-registration-heading]':heading,'[data-registration-message]':message};
const section = {querySelector:selector=>nodes[selector],addEventListener:(name,handler)=>events[name]=handler};
const pages = {
  filtered: {'[data-registration-results]':{childNodes:['filtered-card']},'[data-registration-count]':{textContent:'Showing 1–1 of 1'},'[data-registration-heading]':{textContent:'Filtered records (1)'}},
  cleared: {'[data-registration-results]':{childNodes:['card-a','card-b']},'[data-registration-count]':{textContent:'Showing 1–2 of 2'},'[data-registration-heading]':{textContent:'Resident registration records (2)'}},
};
vm.runInNewContext(fs.readFileSync('js/registration-record-filters.js','utf8'), {
  document:{getElementById:()=>section},window:{location:{href:'http://localhost/dashboard.php?view=resident-account-registration'},addEventListener(){}},
  URL,URLSearchParams,AbortController,Event:class{},FormData:class{constructor(){return [['view','resident-account-registration'],...Object.entries(fields).map(([name,field])=>[name,field.value])];}},
  DOMParser:class{parseFromString(key){return {getElementById:()=>({querySelector:selector=>pages[key][selector]})};}},
  fetch:(url,options)=>new Promise(resolve=>calls.push({url,options,resolve})),
  history:{replaceState:(state,title,url)=>historyCalls.push(String(url))},
  setTimeout:(callback,delay)=>{timers.set(++timerId,{callback,delay});return timerId;},clearTimeout:id=>timers.delete(id),
});
const tick = () => new Promise(resolve=>setImmediate(resolve));
const respond = (index,key,ok=true) => calls[index].resolve({ok,redirected:false,text:async()=>key});
let prevented = 0;
const submit = () => events.submit({preventDefault:()=>prevented++});
const clear = () => events.click({target:{closest:selector=>selector==='[data-registration-clear]'?{}:null},preventDefault:()=>prevented++});
(async()=>{
  fields.registration_search.value='Deloy'; submit();
  assert.equal(count.textContent,'Showing 1–1 of 1','Existing count stays visible during loading');
  assert.equal(apply.disabled,true); respond(0,'filtered'); await tick();
  assert.deepEqual(results.childNodes,['filtered-card']); assert.equal(fields.registration_search.value,'Deloy');
  assert.equal(apply.disabled,false); assert.equal(historyCalls.length,1);
  fields.registration_status.value='Active'; fields.registration_purok.value='Cortes'; clear();
  assert.ok(Object.values(fields).every(field=>field.value===''),'Clear removes values even when initial page had active filters');
  respond(1,'cleared'); await tick(); assert.equal(count.textContent,'Showing 1–2 of 2');
  submit(); clear();
  assert.equal(calls[2].options.signal.aborted,true,'New filter cancels an older response');
  respond(3,'cleared'); await tick(); respond(2,'filtered'); await tick();
  assert.deepEqual(results.childNodes,['card-a','card-b'],'Late old filter cannot overwrite Clear');
  submit(); respond(4,'filtered',false); await tick();
  assert.deepEqual(results.childNodes,['card-a','card-b']); assert.equal(count.textContent,'Showing 1–2 of 2');
  assert.match(message.textContent,/retry/); assert.equal(apply.disabled,false); assert.equal(prevented,5);
  fields.registration_search.value='L'; fields.registration_search.events.input({});
  const debounce=[...timers.values()].find(timer=>timer.delay===220); assert.ok(debounce,'Typing schedules a search without Enter');
  debounce.callback(); assert.equal(calls.length,6); assert.match(String(calls[5].url),/registration_search=L/);
  fields.registration_search.value='Lor'; fields.registration_search.events.input({});
  assert.equal(calls[5].options.signal.aborted,true,'Typing immediately invalidates pending responses');
  respond(5,'filtered'); await tick(); assert.deepEqual(results.childNodes,['card-a','card-b']);
  console.log('PASS: in-place filtering, clear, stable count, stale-response protection and failure recovery.');
})().catch(error=>{console.error(error);process.exitCode=1;});
