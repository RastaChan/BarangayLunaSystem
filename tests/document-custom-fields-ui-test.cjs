const fs=require('node:fs'),vm=require('node:vm'),assert=require('node:assert/strict');
let document;
class Element {
 constructor(tag='div') {this.tagName=tag.toUpperCase();this.children=[];this.dataset={};this.events={};this.value='';this.hidden=false;this.classList={add(){}};}
 append(...nodes){nodes.forEach(node=>{node.parent=this;this.children.push(node);});}
 remove(){this.parent.children=this.parent.children.filter(node=>node!==this);}
 replaceChildren(...nodes){this.children=[];this.append(...nodes);}
 closest(selector){return this.tagName===selector.toUpperCase()?this:this.parent?.closest(selector)||null;}
 matches(selector){return selector.split(',').some(s=>{const m=s.trim().match(/^\[([^=\]]+)(?:="([^"]*)")?\]$/);if(!m)return false;const name=m[1];const value=name.startsWith('data-')?this.dataset[name.slice(5).replace(/-([a-z])/g,(_,c)=>c.toUpperCase())]:this[name];return m[2]===undefined?value!==undefined:value===m[2];});}
 querySelectorAll(selector){return this.children.flatMap(node=>[...(node.matches(selector)?[node]:[]),...node.querySelectorAll(selector)]);}
 querySelector(selector){return this.querySelectorAll(selector)[0]||null;}
 addEventListener(name,callback){(this.events[name]||=[]).push(callback);}
 dispatchEvent(event){event.target ||= this;(this.events[event.type]||[]).forEach(fn=>fn(event));if(event.bubbles)this.parent?.dispatchEvent(event);}
 focus(){document.activeElement=this;}
 select(){this.selected=true;}
}
const defs=[{key:'business_name',label:'Business name',type:'text',required:true,options:[]},{key:'zone',label:'Zone',type:'select',required:false,options:['North','South']}];
const form=new Element('form'),type=new Element('input');type.name='document_type';type.value='Custom Business';
const panel=new Element();panel.dataset.documentCustomFields='';panel.dataset.customMap=JSON.stringify({'custom business':defs,other:[{key:'note',label:'Note',type:'textarea',required:false,options:[]}]});panel.dataset.customValues=JSON.stringify({business_name:'Saved resident business'});
const inputs=new Element();inputs.dataset.customInputs='';panel.append(inputs);form.append(type,panel);
const builderForm=new Element('form'),builderType=new Element('input');builderType.name='document_type';builderType.value='__new__';
const output=new Element('input');output.name='custom_field_definitions';
const builder=new Element();builder.dataset.customBuilder='';builder.dataset.customReserved=JSON.stringify(['resident_name']);builder.dataset.customBuilderMap=JSON.stringify({'custom business':defs});
const rows=new Element();rows.dataset.customBuilderRows='';const add=new Element('button');add.dataset.customAdd='';const empty=new Element();empty.dataset.customBuilderEmpty='';const preview=new Element();preview.dataset.customFormPreview='';const previewEmpty=new Element();previewEmpty.dataset.customPreviewEmpty='';const status=new Element();status.dataset.customSetupStatus='';builder.append(rows,add,empty,preview,previewEmpty,status);builderForm.append(builderType,output,builder);builderForm.reportValidity=()=>true;builderForm.action='staff-actions.php';
document={activeElement:null,createElement:tag=>new Element(tag),querySelectorAll:selector=>selector==='[data-document-custom-fields]'?[panel]:[builder]};
let resolveSave;const copied=[],redirects=[],payloads=[];
const context={document,navigator:{clipboard:{writeText:async text=>copied.push(text)}},setTimeout(){},window:{location:{assign:href=>redirects.push(href)}},AbortSignal:{timeout:()=>({})},FormData:class{constructor(){this.definitions=output.value;}},fetch:async(url,options)=>{payloads.push(options);return new Promise(resolve=>{resolveSave=resolve;});},CustomEvent:class{constructor(type,options={}){this.type=type;Object.assign(this,options);}}};
vm.runInNewContext(fs.readFileSync('js/document-custom-fields.js','utf8'),context);
let business=inputs.querySelector('[data-custom-key="business_name"]');
assert.equal(business.value,'Saved resident business');assert.equal(business.required,true);assert.equal(business.name,'custom_fields[business_name]');
business.value='Changed';type.value='Other';type.dispatchEvent({type:'change'});
assert.equal(inputs.children.length,1);assert.equal(inputs.querySelector('[data-custom-key="note"]').tagName,'TEXTAREA');
type.value='Custom Business';type.dispatchEvent({type:'change'});assert.equal(inputs.querySelector('[data-custom-key="business_name"]').value,'Changed','Switching types restores values for each document');
type.value='No fields';type.dispatchEvent({type:'change'});assert.equal(panel.hidden,true);assert.equal(inputs.children.length,0,'Removed custom fields cannot submit stale values');
add.dispatchEvent({type:'click'});assert.equal(rows.children.length,1);
const row=rows.children[0],label=row.querySelector('[data-definition-label]');label.value='Business name';label.dispatchEvent({type:'input',bubbles:true});
assert.equal(row.querySelector('[data-definition-key]').value,'business_name');assert.equal(JSON.parse(output.value)[0].label,'Business name');
const kind=row.querySelector('[data-definition-type]');kind.value='select';kind.dispatchEvent({type:'change',bubbles:true});const choices=row.querySelector('[data-definition-options]');assert.equal(choices.required,true);assert.equal(choices.disabled,false);
choices.value='North\nSouth';choices.dispatchEvent({type:'input',bubbles:true});assert.deepEqual(JSON.parse(output.value)[0].options,['North','South']);
row.children.find(node=>node.tagName==='BUTTON').dispatchEvent({type:'click'});assert.equal(output.value,'[]');
builderType.value='Custom Business';builderType.dispatchEvent({type:'change'});assert.equal(rows.children.length,2,'Editing a saved document loads its definitions');
console.log('PASS: field creation, automatic Word tags, required controls, dropdown options, saved values, type switching and stale-field removal.');

(async()=>{
  assert.equal(preview.children.length,2,'Saved fields appear in the resident form preview');
  assert.equal(preview.children[0].children[1].disabled,true,'Preview fields never participate in validation or submission');
  const savedRow=rows.children[0],copy=savedRow.querySelector('[data-custom-copy]');
  await copy.events.click[0]();assert.equal(copied.at(-1),'{{business_name}}','Copy writes the complete tag, including braces');
  const savedLabel=savedRow.querySelector('[data-definition-label]');savedLabel.value='Registered business name';savedLabel.dispatchEvent({type:'input',bubbles:true});
  assert.equal(savedRow.querySelector('[data-definition-key]').value,'business_name','Renaming a saved field does not break its Word tag');
  assert.equal(preview.children[0].children[0].textContent,'Registered business name *','Preview updates the label and required indicator');
  add.dispatchEvent({type:'click'});let newRow=rows.children.at(-1),newLabel=newRow.querySelector('[data-definition-label]');newLabel.value='Business name';newLabel.dispatchEvent({type:'input',bubbles:true});
  assert.equal(newRow.querySelector('[data-definition-key]').value,'business_name_2','Automatic tags avoid existing tags');
  newLabel.value='Resident name';newLabel.dispatchEvent({type:'input',bubbles:true});assert.equal(newRow.querySelector('[data-definition-key]').value,'additional_resident_name','Standard tags are protected automatically');
  document.body=new Element();let fallbackCopies=0;document.execCommand=()=>{fallbackCopies++;return true;};context.navigator.clipboard=null;
  await newRow.querySelector('[data-custom-copy]').events.click[0]();assert.equal(fallbackCopies,1,'Copy supports HTTP through the user-initiated clipboard fallback');assert.equal(document.body.children.length,0);
  const before=output.value;
  const firstSave=builderForm.events.submit[0]({preventDefault(){}});assert.equal(builderForm.inert,true);
  resolveSave({ok:false,headers:{get:()=> 'application/json'},json:async()=>({ok:false,message:'Add {{business_name}} to your Word file.'})});await firstSave;
  assert.equal(status.textContent,'Add {{business_name}} to your Word file.');assert.equal(status.dataset.error,'true');assert.equal(output.value,before,'Validation errors preserve the entered field setup');assert.equal(builderForm.inert,false);
  const secondSave=builderForm.events.submit[0]({preventDefault(){}});resolveSave({ok:true,headers:{get:()=> 'application/json'},json:async()=>({ok:true,redirect:'staff-dashboard.php?view=issue-documents#template-management'})});await secondSave;
  assert.equal(redirects.length,1);assert.equal(payloads[0].headers['X-Requested-With'],'TemplateSetup');
  console.log('PASS: automatic collision-safe tags, complete-tag copying with HTTP fallback, live preview, unchanged saved tags and save errors that preserve entries.');
})().catch(error=>{console.error(error);process.exitCode=1;});
