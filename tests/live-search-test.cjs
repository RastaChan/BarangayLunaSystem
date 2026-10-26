const fs=require('node:fs'),vm=require('node:vm'),assert=require('node:assert/strict');
const events={},requests=[],timers=new Map();let id=0;
const input={value:'',events:{},addEventListener(name,fn){this.events[name]=fn;}};
const status={hidden:true,setAttribute(){}};
const region={nodes:['original'],events:{},setAttribute(){},removeAttribute(){},replaceChildren(...nodes){this.nodes=nodes;},addEventListener(name,fn){this.events[name]=fn;}};
const form={dataset:{liveSearchForm:'residents'},getAttribute:()=>null,insertAdjacentElement(){},querySelectorAll:s=>s==='input[type="search"]'?[input]:[],addEventListener(name,fn){events[name]=fn;}};
const histories=[];
vm.runInNewContext(fs.readFileSync('js/live-search.js','utf8'),{
 document:{querySelectorAll:s=>s==='[data-live-search-form]'?[form]:[region],createElement:()=>status},
 location:{href:'http://localhost/dashboard.php?view=residents&resident_page=4'},
 window:{addEventListener(){}},URL,URLSearchParams,AbortController,
 FormData:class{constructor(){return [['view','residents'],['resident_search',input.value]];}},
 DOMParser:class{parseFromString(text){return {querySelectorAll:()=>[{childNodes:[text]}]};}},
 fetch:(url,opts)=>new Promise(resolve=>requests.push({url,opts,resolve})),
 history:{replaceState:(state,title,url)=>histories.push(String(url))},
 setTimeout:(fn,ms)=>{timers.set(++id,{fn,ms});return id;},clearTimeout:id=>timers.delete(id),
});
const tick=()=>new Promise(resolve=>setImmediate(resolve));
const runDebounce=()=>{const entry=[...timers].find(([,t])=>t.ms===220);assert.ok(entry);timers.delete(entry[0]);entry[1].fn();};
const respond=(n,text,ok=true)=>requests[n].resolve({ok,redirected:false,text:async()=>text});
(async()=>{
 input.value='L';input.events.input({});runDebounce();
 assert.match(String(requests[0].url),/resident_search=L/);assert.ok(!requests[0].url.searchParams.has('resident_page'),'A new query starts at the first page');
 input.value='Lor';input.events.input({});assert.equal(requests[0].opts.signal.aborted,true);
 respond(0,'outdated');await tick();assert.deepEqual(region.nodes,['original']);
 runDebounce();respond(1,'Lorlie');await tick();assert.deepEqual(region.nodes,['Lorlie']);assert.equal(input.value,'Lor');assert.equal(status.hidden,true);
 input.value='';input.events.input({});runDebounce();respond(2,'all records');await tick();assert.deepEqual(region.nodes,['all records']);
 input.value='missing';input.events.input({});runDebounce();respond(3,'',false);await tick();assert.deepEqual(region.nodes,['all records']);assert.match(status.textContent,/try again/);
 let prevented=false;events.submit({preventDefault(){prevented=true;}});assert.equal(prevented,true);respond(4,'no matches');await tick();assert.deepEqual(region.nodes,['no matches']);
 input.events.input({isComposing:true});assert.ok(![...timers.values()].some(t=>t.ms===220),'Composition waits for a completed character');
 console.log('PASS: single-letter live search, first-page reset, clearing, Enter fallback, stale response protection, composition and failure recovery.');
})().catch(e=>{console.error(e);process.exitCode=1;});
