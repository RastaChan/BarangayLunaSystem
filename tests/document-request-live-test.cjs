const fs=require('node:fs'), vm=require('node:vm'), assert=require('node:assert/strict');
class Row {
  constructor(code,status) {this.dataset={documentRequestCode:code};this.outerHTML=code+':'+status;this.open=false;this.parent=null;}
  contains(node){return node===this.editor;}
  querySelector(){return this.open ? {} : null;}
  remove(){if(this.parent){this.parent.children.splice(this.parent.children.indexOf(this),1);this.parent=null;}}
  get nextElementSibling(){return this.parent?.children[this.parent.children.indexOf(this)+1] || null;}
}
class List {
  constructor(rows){this.children=[];for(const row of rows)this.insertBefore(row,null);}
  get firstElementChild(){return this.children[0] || null;}
  insertBefore(row,cursor){row.remove();const index=cursor?this.children.indexOf(cursor):this.children.length;this.children.splice(index,0,row);row.parent=this;}
}
const original=new Row('REQ-1','Pending');original.editor={value:'Keep my rejection reason'};
const list=new List([original]);
const owner={dataset:{documentRequestLiveOwner:'staff:7'},querySelector:()=>list};
let poll, nextRows=[['REQ-2','Pending'],['REQ-1','Processing']], nextOwner='staff:7', fail=false, requests=0;
const events={};
const document={hidden:false,activeElement:original.editor,querySelector:()=>owner,getElementById:()=>null,addEventListener(n,f){events[n]=f;}};
vm.runInNewContext(fs.readFileSync('js/document-request-live.js','utf8'),{
  document,navigator:{onLine:true},AbortSignal,
  window:{getSelection:()=>null,setInterval(fn,delay){assert.equal(delay,5000);poll=fn;},addEventListener(){}},
  fetch:async()=>{requests++;if(fail)throw Error('offline');return {ok:true,headers:{get:()=> 'application/json'},json:async()=>({ok:true,owner:nextOwner,html:'fixture',choices:[]})};},
  DOMParser:class {parseFromString(){return {querySelector:()=>new List(nextRows.map(row=>new Row(...row))) };}},
});
const tick=()=>new Promise(resolve=>setImmediate(resolve));
(async()=>{
  await tick();
  assert.deepEqual(list.children.map(row=>row.dataset.documentRequestCode),['REQ-2','REQ-1']);
  assert.equal(list.children[1],original,'New requests must appear without replacing the edited row');
  assert.equal(original.editor.value,'Keep my rejection reason');
  document.activeElement=null; await poll();
  assert.equal(list.children[1].outerHTML,'REQ-1:Processing','Status refresh resumes after editing ends');
  const stable=list.children[0];await poll();assert.equal(list.children[0],stable,'Unchanged rows retain their nodes and action controls');
  nextRows=[['REQ-2','Pending']];await poll();assert.equal(list.children.length,1,'Archived requests disappear');
  nextOwner='admin:99';nextRows=[['OTHER','Pending']];await poll();assert.equal(list.children[0],stable,'Another account response cannot replace this list');
  nextOwner='staff:7';fail=true;await poll();assert.equal(list.children[0],stable,'Connection failure preserves the last list');
  document.hidden=true;const before=requests;await poll();assert.equal(requests,before,'Hidden tabs do not poll');
  document.hidden=false;fail=false;await events['document-notifications-updated']();assert.equal(list.children[0].dataset.documentRequestCode,'OTHER','Notification refresh recovers automatically');
  assert.ok(fs.readFileSync('js/dashboard.js','utf8').includes("event.target.closest('[data-view-record]')"),'New View buttons need delegated events');
  console.log('PASS: new rows refresh during editing, statuses update safely, unchanged rows stay usable, removals refresh, account isolation and network recovery work.');
})().catch(error=>{console.error(error);process.exitCode=1;});
