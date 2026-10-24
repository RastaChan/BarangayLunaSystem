const assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm'),path=require('node:path');
const source=fs.readFileSync(path.join(__dirname,'../js/issue-qr-capture.js'),'utf8');
function node(){return {disabled:false,hidden:false,events:{},addEventListener(k,f){this.events[k]=f;},click(){this.clicked=true;}};}
function fixture(){
  const start=node(),stop=node(),photo=node(),input=node(),panel=node(),video=node(),status=node(),lookup=node();
  video.readyState=2;video.videoWidth=320;video.videoHeight=240;video.play=async()=>{};
  let stopped=0,calls=0,decoded=null,revoked=0,qr=null;
  const camera={getTracks:()=>[{stop(){stopped++;}}]};
  const container={dataset:{lookupBusy:'false'},events:{},addEventListener(k,f){this.events[k]=f;},dispatchEvent:event=>{decoded=event.detail;}};
  const nodes={'[data-issue-camera-start]':start,'[data-issue-camera-stop]':stop,'[data-issue-photo-open]':photo,'[data-issue-photo-input]':input,'[data-issue-camera-panel]':panel,video,'[data-issue-camera-status]':status};
  const root={closest:()=>container,querySelector:s=>nodes[s]};
  const docEvents={},winEvents={},timers=[];
  const context={document:{hidden:false,querySelectorAll:()=>[root],createElement:()=>({getContext:()=>({drawImage(){},getImageData:()=>({data:[],width:320,height:240})})}),addEventListener:(k,f)=>docEvents[k]=f},
    window:{jsQR:()=>qr?{data:qr}:null,addEventListener:(k,f)=>winEvents[k]=f},navigator:{mediaDevices:{getUserMedia:async()=>{calls++;return camera;}}},
    setTimeout:f=>{timers.push(f);return timers.length;},clearTimeout(){},CustomEvent:class{constructor(name,options){this.detail=options.detail;}},
    URL:{createObjectURL:()=> 'blob:test',revokeObjectURL(){revoked++;}},Image:class{constructor(){this.naturalWidth=320;this.naturalHeight=240;}set src(value){queueMicrotask(()=>this.onload());}}};
  vm.runInNewContext(source,context);
  return {start,stop,photo,input,panel,video,status,lookup,container,context,timers,docEvents,winEvents,camera,setQr:value=>qr=value,getCalls:()=>calls,getStopped:()=>stopped,getDecoded:()=>decoded,getRevoked:()=>revoked};
}
(async()=>{
  let f=fixture();await f.start.events.click();assert.equal(f.getCalls(),1);assert.equal(f.panel.hidden,false);await f.start.events.click();assert.equal(f.getCalls(),1,'Duplicate clicks cannot open duplicate cameras');
  f.setQr('LUNA|RES-001|current');f.timers[0]();assert.equal(f.getDecoded(),'LUNA|RES-001|current');assert.equal(f.getStopped(),1);assert.equal(f.panel.hidden,true,'Successful QR stops the camera before resident lookup');
  f=fixture();let resolve;f.context.navigator.mediaDevices.getUserMedia=()=>new Promise(r=>resolve=r);
  const pending=f.start.events.click();f.stop.events.click();resolve(f.camera);await pending;assert.equal(f.getStopped(),1);assert.equal(f.getDecoded(),null,'Closing during permission request releases the late stream');
  f=fixture();await f.start.events.click();f.context.document.hidden=true;f.docEvents.visibilitychange();assert.equal(f.getStopped(),1,'Hidden page closes camera');
  f=fixture();await f.start.events.click();f.winEvents.pagehide();assert.equal(f.getStopped(),1,'Navigation closes camera');
  f=fixture();await f.start.events.click();f.container.events['resident-search-reset']();assert.equal(f.getStopped(),1,'Changing applicant mode or search stops camera');
  f=fixture();f.context.navigator.mediaDevices.getUserMedia=async()=>{const e=new Error();e.name='NotAllowedError';throw e;};await f.start.events.click();assert.match(f.status.textContent,/permission was denied/);assert.equal(f.start.disabled,false);
  f=fixture();f.context.navigator.mediaDevices=undefined;await f.start.events.click();assert.match(f.status.textContent,/Read QR photo/,'Insecure/no-camera environments retain a useful photo alternative');
  f=fixture();f.setQr('LUNA|RES-001|photo');f.input.files=[{type:'image/png',size:300}];await f.input.events.change();assert.equal(f.getDecoded(),'LUNA|RES-001|photo');assert.equal(f.getCalls(),0,'Photo reading needs no camera');assert.equal(f.getRevoked(),1,'Photo object URL released');
  f=fixture();f.input.files=[{type:'image/png',size:300}];await f.input.events.change();assert.match(f.status.textContent,/No QR was found/);assert.equal(f.photo.disabled,false);assert.equal(f.getRevoked(),1);
  f=fixture();f.input.files=[{type:'image/svg+xml',size:300}];await f.input.events.change();assert.match(f.status.textContent,/JPG, PNG, or WEBP/);assert.equal(f.getDecoded(),null);
  f=fixture();f.container.dataset.lookupBusy='true';await f.start.events.click();f.photo.events.click();assert.equal(f.getCalls(),0);assert.equal(f.input.clicked,undefined,'Scanning waits for an existing lookup');
  console.log('PASS: camera decode, duplicate taps, late permissions, navigation cleanup, denial recovery, no-camera alternative, photo decoding, invalid images, and busy lookup guards');
})().catch(error=>{console.error(error);process.exitCode=1;});
