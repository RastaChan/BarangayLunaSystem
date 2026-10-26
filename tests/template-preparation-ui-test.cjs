const fs=require('node:fs'),vm=require('node:vm'),assert=require('node:assert/strict');
let document;const downloads=[];
class Element {
 constructor(tag='div'){this.tagName=tag.toUpperCase();this.children=[];this.dataset={};this.events={};this.value='';this.files=[];this.checked=false;this.hidden=false;}
 append(...nodes){nodes.forEach(node=>{node.parent=this;this.children.push(node);});}
 remove(){this.parent.children=this.parent.children.filter(node=>node!==this);}
 replaceChildren(...nodes){this.children=[];this.append(...nodes);}
 closest(selector){return this.tagName===selector.toUpperCase()?this:this.parent?.closest(selector)||null;}
 matches(selector){const m=selector.match(/^\[([^=\]]+)(?:="([^"]*)")?\]$/);if(!m)return false;const val=m[1].startsWith('data-')?this.dataset[m[1].slice(5).replace(/-([a-z])/g,(_,c)=>c.toUpperCase())]:this[m[1]];return m[2]===undefined?val!==undefined:val===m[2];}
 querySelectorAll(selector){return this.children.flatMap(child=>[...(child.matches(selector)?[child]:[]),...child.querySelectorAll(selector)]);}
 querySelector(selector){return this.querySelectorAll(selector)[0]||null;}
 addEventListener(type,fn){(this.events[type]||=[]).push(fn);}
 dispatchEvent(event){event.target ||= this;(this.events[event.type]||[]).forEach(fn=>fn(event));if(event.bubbles)this.parent?.dispatchEvent(event);}
 focus(){document.activeElement=this;}
 click(){if(this.tagName==='A')downloads.push({href:this.href,name:this.download});return this.events.click?.[0]?.();}
}
const form=new Element('form'),tool=new Element();tool.dataset.templatePreparationTool='';form.append(tool);
const nodes={};for(const name of ['status','review','replacements','text-preview','analyze','manual-add','reviewed','download','use','extra-label','extra-type','extra-add']){const e=new Element(name==='reviewed'?'input':name.includes('download')||['analyze','use','manual-add','extra-add'].includes(name)?'button':'div');const data='template-'+(name==='status'?'preparation-status':name==='review'?'preparation-review':name);e.dataset[data.replace(/-([a-z])/g,(_,c)=>c.toUpperCase())]='';tool.append(e);nodes[name]=e;}
const upload=new Element('input');upload.name='template_docx';const original={name:'certificate.docx',size:10,lastModified:1};upload.files=[original];const csrf=new Element('input');csrf.name='template_fields_csrf';csrf.value='test-token';const definitions=new Element('input');definitions.name='custom_field_definitions';definitions.value='[]';form.append(upload,csrf,definitions);
let imported;form.addEventListener('template-fields-import',event=>{imported=event.detail.fields;});
document={body:new Element('body'),activeElement:null,querySelector:()=>tool,createElement:tag=>new Element(tag)};
let resolveFetch;const requests=[];const context={document,AbortSignal:{timeout:()=>({})},FormData:class{constructor(){this.values={};}set(key,value){this.values[key]=value;}},fetch:async(url,options)=>{requests.push({url,options});return new Promise(resolve=>{resolveFetch=resolve;});},File:class{constructor(parts,name,options){Object.assign(this,{parts,name,...options});}},DataTransfer:class{constructor(){this.files=[];this.items={add:file=>this.files.push(file)};}},Event:class{constructor(type,options={}){this.type=type;Object.assign(this,options);}},CustomEvent:class{constructor(type,options={}){this.type=type;Object.assign(this,options);}}};
vm.runInNewContext(fs.readFileSync('js/template-preparation.js','utf8'),context);
const plan={ok:true,token:'source-token',name:'certificate.docx',paragraphs:[{id:'p0_0',text:'Name: Anna'}],suggestions:[{paragraph:'p0_0',start:6,length:4,source:'Anna',key:'resident_name_proper',reason:'From name label'}],existing_tags:[],catalog:[{key:'resident_name_proper',label:'Resident name',standard:true},{key:'business_name',label:'Business name',standard:false,type:'text',options:[],required:false}]};
const json=(data,ok=true)=>({ok,headers:{get:()=> 'application/json'},json:async()=>data});
(async()=>{
 assert.equal(nodes.download.disabled,true,'Download is disabled before analysis and review');
 const analysis=nodes.analyze.click();assert.equal(requests[0].options.body.values.template_docx,original);resolveFetch(json(plan));await analysis;
 assert.equal(nodes.review.hidden,false);assert.equal(nodes.replacements.children.length,1);assert.equal(nodes.download.disabled,true);
 let row=nodes.replacements.children[0];assert.equal(row.children[1].children[1].textContent,'Anna');assert.equal(nodes['text-preview'].children[0].children[1].textContent,'{{resident_name_proper}}');
 await nodes.download.click();assert.equal(requests.length,1,'Direct clicks cannot bypass review confirmation');
 nodes.reviewed.checked=true;nodes.reviewed.dispatchEvent({type:'change'});assert.equal(nodes.download.disabled,false);
 const firstDownload=nodes.download.click();assert.equal(requests.at(-1).options.body.values.reviewed,'1');resolveFetch(json({ok:true,url:'template-preparation.php?token=prepared',name:'prepared.docx',fields:[]}));await firstDownload;
 assert.deepEqual(downloads,[{href:'template-preparation.php?token=prepared',name:'prepared.docx'}]);assert.equal(upload.files[0],original,'Downloading does not replace the original upload');
 row.children[0].children[0].checked=false;row.children[0].children[0].dispatchEvent({type:'change'});assert.equal(nodes.reviewed.checked,false,'Changing mappings requires a fresh review');
 nodes['manual-add'].click();row=nodes.replacements.children.at(-1);const source=row.children[1].children[1];source.value='Anna';source.dispatchEvent({type:'input'});
 nodes['extra-label'].value='Permit number';nodes['extra-type'].value='number';nodes['extra-add'].click();row=nodes.replacements.children.at(-1);const field=row.children.at(-1).children[1];field.value='permit_number';field.dispatchEvent({type:'change'});
 nodes.reviewed.checked=true;nodes.reviewed.dispatchEvent({type:'change'});
 const use=nodes.use.click();const payload=requests.at(-1).options.body.values;assert.equal(JSON.parse(payload.mappings)[0].source,'Anna');assert.equal(JSON.parse(payload.mappings)[0].key,'permit_number');assert.equal(JSON.parse(payload.new_fields)[0].type,'number');
 resolveFetch(json({ok:true,url:'template-preparation.php?token=prepared2',name:'prepared2.docx',fields:[{key:'permit_number',label:'Permit number',type:'number',required:false,options:[]}]}));
 await new Promise(resolve=>setTimeout(resolve,0));assert.equal(requests.at(-1).url,'template-preparation.php?token=prepared2');resolveFetch({ok:true,headers:{get:()=> 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'},blob:async()=>({bytes:'prepared'})});await use;
 assert.equal(upload.files[0].name,'prepared2.docx');assert.equal(imported[0].key,'permit_number');assert.equal(nodes.review.hidden,true);assert.match(nodes.status.textContent,/Prepared Word file selected/);
 const late=nodes.analyze.click();upload.files=[{name:'different.docx'}];upload.dispatchEvent({type:'change'});resolveFetch(json(plan));await late;assert.equal(nodes.review.hidden,true,'An old analysis cannot replace a newly chosen file');
 console.log('PASS: review-gated preparation, safe text preview, manual mappings, added fields, download without upload changes, prepared-file import and stale-response cancellation.');
})().catch(error=>{console.error(error);process.exitCode=1;});
