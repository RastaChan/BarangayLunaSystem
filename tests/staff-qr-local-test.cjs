const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
function element() { return {events:{},dataset:{},value:'',hidden:false,disabled:false,addEventListener(k,f){this.events[k]=f;}}; }
const start=element(),stop=element(),status=element(),photo=element(),input=element(),video=element();
Object.assign(video,{readyState:2,videoWidth:1280,videoHeight:720,play:async()=>{}});
const nodes={startStaffQrScanner:start,stopStaffQrScanner:stop,staffQrCameraStatus:status,staffQrPhotoInput:photo,staffQrManualInput:input,staffQrVideo:video};
const events={},windowEvents={},scripts=[],requests=[];
let stopped=0,decoded='LUNA|RES-TEST|test';
const window={addEventListener(k,f){windowEvents[k]=f;}};
const document={hidden:false,querySelector:()=>({dataset:{}}),getElementById:id=>nodes[id]||null,addEventListener(k,f){events[k]=f;},head:{appendChild(script){scripts.push(script.src);window.jsQR=()=>decoded?{data:decoded}:null;queueMicrotask(()=>script.onload());}},createElement(tag){
  if(tag==='script') return {remove(){}};
  if(tag==='canvas') return {width:0,height:0,getContext:()=>({drawImage(){},getImageData:()=>({data:new Uint8ClampedArray(16),width:2,height:2})})};
  return element();
}};
class Image {constructor(){this.naturalWidth=300;this.naturalHeight=300;}set src(value){queueMicrotask(()=>this.onload());}}
const context={document,window,Image,URL:{createObjectURL:()=> 'blob:test',revokeObjectURL(){}},URLSearchParams,AbortSignal,HTMLMediaElement:{HAVE_CURRENT_DATA:2},navigator:{mediaDevices:{getUserMedia:async()=>({getTracks:()=>[{stop(){stopped++;}}]})}},setTimeout:()=>1,clearTimeout(){},console,fetch:async(url,options)=>{requests.push({url,body:options.body.toString()});return {ok:true,json:async()=>({ok:true,result:'Not Found',resident:null,message:'Test lookup complete'})};}};
vm.runInNewContext(fs.readFileSync('js/staff-qr-scanner.js','utf8'),context);
const flush=()=>new Promise(resolve=>setImmediate(resolve));
(async()=>{
  await start.events.click();await flush();
  assert.deepEqual(scripts,['js/vendor/jsQR.js'],'Browsers without BarcodeDetector load only the bundled QR decoder');
  assert.equal(requests.length,1,'Local camera decoder passes the QR to the real lookup flow');assert.equal(stopped,1,'Camera closes after decoding');
  assert.match(requests[0].body,/LUNA/);assert.equal(start.disabled,false);
  photo.files=[{type:'image/png',size:100}];photo.events.change();await flush();
  assert.equal(requests.length,2,'Photo scanning works without any external scanner');assert.equal(photo.value,'');
  photo.files=[{type:'application/pdf',size:100}];photo.events.change();await flush();assert.equal(requests.length,2);assert.match(status.textContent,/JPG, PNG or WEBP/);
  photo.files=[{type:'image/jpeg',size:11*1024*1024}];photo.events.change();await flush();assert.equal(requests.length,2,'Oversized images never reach lookup');
  decoded='';photo.files=[{type:'image/webp',size:100}];photo.events.change();await flush();assert.equal(requests.length,2);assert.match(status.textContent,/No QR code/);
  photo.files=[{type:'image/png',size:100}];photo.events.change();document.hidden=true;events.visibilitychange();decoded='LUNA|RES-TEST|late';await flush();assert.equal(requests.length,2,'Leaving the page cancels a pending photo scan');
  assert.equal(scripts.length,1,'Repeated photos reuse the local reader');
  document.hidden=false;decoded='LUNA|RES-TEST|fallback';
  window.BarcodeDetector=class {async detect(){return [];}};context.BarcodeDetector=window.BarcodeDetector;
  await start.events.click();await flush();
  assert.equal(requests.length,3,'Bundled reader still decodes when the native detector misses a QR');
  window.isSecureContext=false;
  await start.events.click();assert.match(status.textContent,/trusted HTTPS/);assert.equal(start.disabled,false);
  window.isSecureContext=true;
  const camera=context.navigator.mediaDevices.getUserMedia;
  context.navigator.mediaDevices.getUserMedia=async()=>{const error=new Error();error.name='NotReadableError';throw error;};
  await start.events.click();assert.match(status.textContent,/camera is busy/);assert.equal(start.disabled,false);
  let resolveCamera;
  context.navigator.mediaDevices.getUserMedia=()=>new Promise(resolve=>resolveCamera=resolve);
  const pending=start.events.click();await flush();assert.equal(stop.disabled,false,'Stop remains available during the permission prompt');
  stop.events.click();const stoppedBefore=stopped;resolveCamera(await camera());await pending;
  assert.equal(stopped,stoppedBefore+1,'Cancelling a pending camera request releases the late stream');assert.equal(requests.length,3);
  console.log('PASS: local camera and photo decoding, stream cleanup, file validation, unreadable photos and hidden-page cancellation.');
})().catch(error=>{console.error(error);process.exitCode=1;});
