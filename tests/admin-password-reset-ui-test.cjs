const assert=require('node:assert/strict');
const fs=require('node:fs');
const vm=require('node:vm');
const source=fs.readFileSync('js/dashboard.js','utf8');
const start=source.indexOf("  document.querySelectorAll('[data-reset-password]').forEach");
const end=source.indexOf("  document.addEventListener('click', event => {",start);
for(const role of ['staff','resident']) {
  let callback;
  const values={id:{},redirect:{},password:{value:'stale',type:'text'}};
  const label={}; const modal={dataset:{}};
  const btn={getAttribute:key=>({'data-id':role==='staff'?'STF-TEST':'RES-TEST','data-name':'Test Account','data-redirect':role==='resident'?'residents':null}[key]),addEventListener:(name,fn)=>callback=fn,closest:()=>role==='staff'?{}:null};
  const form={querySelector:selector=>values[selector.match(/name="([^"]+)/)[1]]};
  const document={querySelectorAll:()=>[btn],getElementById:id=>id==='resetPasswordForm'?form:modal,querySelector:()=>label};
  vm.runInNewContext(source.slice(start,end),{document,closeModal:()=>{},openModal:()=>{}});
  callback();
  assert.equal(values.id.value,role==='staff'?'STF-TEST':'RES-TEST');
  assert.equal(values.redirect.value,role==='staff'?'user-management':'residents');
  assert.equal(values.password.value,''); assert.equal(values.password.type,'password');
}
console.log('PASS: both reset controls select the correct account and return route, and clear stale password values.');
