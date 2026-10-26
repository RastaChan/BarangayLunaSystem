/* Custom document fields share the saved template definition across all forms. */
(() => {
  'use strict';
  const key = value => String(value || '').trim().replace(/\s+/g, ' ').toLowerCase();
  const own = (object, property) => Object.prototype.hasOwnProperty.call(object, property);
  const parse = value => {try {return JSON.parse(value || '{}');} catch (_) {return {};}};
  function input(label, type='text') {
    const wrapper = document.createElement('label'); wrapper.className = 'field';
    const text = document.createElement('span'); text.textContent = label;
    const control = document.createElement(type === 'textarea' ? 'textarea' : type === 'select' ? 'select' : 'input');
    if (control.tagName === 'INPUT') control.type = type;
    wrapper.append(text,control); return {wrapper,control};
  }
  document.querySelectorAll('[data-document-custom-fields]').forEach(panel => {
    const form=panel.closest('form');
    const type=form.querySelector('[data-autofill-document-type], [name="document_type"]');
    if (!type) return;
    const map=parse(panel.dataset.customMap), initial=parse(panel.dataset.customValues), cache=new Map();
    const container=panel.querySelector('[data-custom-inputs]');let current='';
    function sync() {
      const selected=key(type.value); if (selected===current) return;
      if (current) cache.set(current,Object.fromEntries([...container.querySelectorAll('[data-custom-key]')].map(node=>[node.dataset.customKey,node.value])));
      const first=!current; current=selected;
      const fields=own(map,selected)?map[selected]:[], values=cache.get(selected) || (first ? initial : {});
      container.replaceChildren(); panel.hidden=fields.length===0;
      fields.forEach(field => {
        const {wrapper,control}=input(field.label,field.type);
        if (field.type==='select') {
          const empty=document.createElement('option'); empty.value=''; empty.textContent='Select '+field.label; control.append(empty);
          field.options.forEach(value=>{const option=document.createElement('option'); option.value=value; option.textContent=value; control.append(option);});
        }
        control.name=`custom_fields[${field.key}]`; control.dataset.customKey=field.key; control.dataset.customLabel=field.label;
        control.value=own(values,field.key)?values[field.key]:''; control.required=!!field.required;
        if (field.type==='number') control.step='any';
        if (field.type==='textarea') {control.rows=3; wrapper.classList.add('custom-field-wide');}
        if (field.type==='text' || field.type==='textarea') control.maxLength=field.type==='textarea'?2000:250;
        container.append(wrapper);
      });
      form.dispatchEvent(new CustomEvent('custom-document-fields-changed',{bubbles:true}));
    }
    type.addEventListener('change',sync); sync();
  });
  document.querySelectorAll('[data-custom-builder]').forEach(panel => {
    const form=panel.closest('form'), map=parse(panel.dataset.customBuilderMap);
    const reserved=new Set(parse(panel.dataset.customReserved || '[]'));
    const type=form.querySelector('[name="document_type"]'), output=form.querySelector('[name="custom_field_definitions"]');
    const rows=panel.querySelector('[data-custom-builder-rows]'), add=panel.querySelector('[data-custom-add]');
    const preview=panel.querySelector('[data-custom-form-preview]'), status=panel.querySelector('[data-custom-setup-status]');
    let saving=false, previewSignature='';
    function message(text,error=false) {
      if (!status) return;
      status.textContent=text;status.dataset.error=String(error);
      if (error) {
        const reduced=typeof window.matchMedia==='function' && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        status.scrollIntoView?.({block:'nearest',behavior:reduced?'auto':'smooth'});
      }
    }
    function previewFields(fields) {
      if (!preview || previewSignature===JSON.stringify(fields)) return;
      previewSignature=JSON.stringify(fields);preview.replaceChildren();
      const empty=panel.querySelector('[data-custom-preview-empty]');if(empty)empty.hidden=!!fields.length;
      fields.forEach(field=>{
        const {wrapper,control}=input((field.label || 'New field')+(field.required?' *':''),field.type);
        control.disabled=true;
        if(field.type==='select') {
          ['Select '+(field.label || 'an option'),...field.options].forEach(text=>{const option=document.createElement('option');option.textContent=text;control.append(option);});
        } else control.placeholder='Resident enters '+(field.label || 'this detail').toLowerCase();
        if(field.type==='textarea'){control.rows=3;wrapper.classList.add('custom-field-wide');}
        preview.append(wrapper);
      });
    }
    function serialize() {
      const fields=[...rows.children].map(row=>({label:row.querySelector('[data-definition-label]').value.trim(),key:row.querySelector('[data-definition-key]').value.trim(),type:row.querySelector('[data-definition-type]').value,required:row.querySelector('[data-definition-required]').checked,options:row.querySelector('[data-definition-options]').value.split(/\r?\n/).map(v=>v.trim()).filter(Boolean)}));
      output.value=JSON.stringify(fields);panel.querySelector('[data-custom-builder-empty]').hidden=!!fields.length;add.disabled=fields.length>=20;
      previewFields(fields);
    }
    function automaticTag(label,row) {
      let base=label.normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase().replace(/[^a-z0-9]+/g,'_').replace(/^_+|_+$/g,'') || 'additional_detail';
      if(!/^[a-z]/.test(base) || reserved.has(base))base='additional_'+base;
      if(base.length<2)base+='_detail';base=base.slice(0,40);
      const used=new Set([...rows.children].filter(other=>other!==row).map(other=>other.querySelector('[data-definition-key]').value));
      let value=base,number=2;
      while(used.has(value) || reserved.has(value)){const suffix='_'+number++;value=base.slice(0,40-suffix.length)+suffix;}
      return value;
    }
    async function copyTag(text) {
      if(typeof navigator!=='undefined' && navigator.clipboard?.writeText) {
        try {await navigator.clipboard.writeText(text);return true;} catch (_) {}
      }
      const previous=document.activeElement,copy=document.createElement('textarea');
      copy.value=text;copy.className='custom-copy-fallback';document.body.append(copy);copy.select();
      let copied=false;
      try {copied=!!document.execCommand?.('copy');} catch (_) {}
      copy.remove();previous?.focus({preventScroll:true});return copied;
    }
    function addRow(field={}) {
      const row=document.createElement('div');row.className='custom-definition-row';
      const label=input('Field label'),kind=input('Field type','select'),choices=input('Dropdown choices (one per line)','textarea');
      const tag=document.createElement('input');tag.type='hidden';tag.dataset.definitionKey='';tag.value=field.key || '';
      label.control.dataset.definitionLabel='';label.control.value=field.label || '';label.control.required=true;label.control.maxLength=100;
      kind.control.dataset.definitionType='';
      Object.entries({text:'Text',textarea:'Long text',number:'Number',date:'Date',select:'Dropdown'}).forEach(([value,text])=>{const option=document.createElement('option');option.value=value;option.textContent=text;kind.control.append(option);});
      kind.control.value=field.type || 'text';
      choices.control.dataset.definitionOptions='';choices.control.rows=3;choices.control.value=(field.options || []).join('\n');choices.wrapper.classList.add('custom-field-wide');
      const required=document.createElement('label');required.className='check check-left';const checkbox=document.createElement('input');checkbox.type='checkbox';checkbox.dataset.definitionRequired='';checkbox.checked=!!field.required;
      const requiredText=document.createElement('span');requiredText.textContent='Required';required.append(checkbox,requiredText);
      const remove=document.createElement('button');remove.type='button';remove.className='btn btn-outline btn-sm';remove.textContent='Remove field';remove.addEventListener('click',()=>{row.remove();serialize();add.focus();});
      const copyBox=document.createElement('div');copyBox.className='custom-tag-copy';
      const hint=document.createElement('code');hint.className='custom-field-tag';
      const copy=document.createElement('button');copy.type='button';copy.className='btn btn-outline btn-sm';copy.textContent='Copy tag';copy.dataset.customCopy='';
      const help=document.createElement('small');help.textContent='Paste this tag into Word where the answer should appear.';
      copyBox.append(hint,copy,help);
      let fixed=!!field.key;
      label.control.addEventListener('input',()=>{if(!fixed)tag.value=automaticTag(label.control.value,row);});
      copy.addEventListener('click',async()=>{
        if(!label.control.value.trim()){label.control.focus();message('Enter a field label before copying its tag.',true);return;}
        if(await copyTag('{{'+tag.value+'}}')){fixed=true;message('Tag copied. Paste it into your Word template.');copy.textContent='Copied';setTimeout(()=>{copy.textContent='Copy tag';},1500);}
        else {message('Copy was unavailable. Select the displayed tag and copy it into Word.',true);}
      });
      function syncRow(){choices.wrapper.hidden=kind.control.value!=='select';choices.control.disabled=choices.wrapper.hidden;choices.control.required=!choices.wrapper.hidden;hint.textContent='{{'+(tag.value || 'field_name')+'}}';serialize();}
      row.append(label.wrapper,kind.wrapper,required,remove,tag,copyBox,choices.wrapper);rows.append(row);
      row.addEventListener('input',syncRow);row.addEventListener('change',syncRow);syncRow();return label.control;
    }
    function load(){rows.replaceChildren();(own(map,key(type.value))?map[key(type.value)]:[]).forEach(addRow);serialize();message('');}
    add.addEventListener('click',()=>{if(rows.children.length<20)addRow().focus();});
    type.addEventListener('change',load);
    form.addEventListener('template-fields-import',event=>{rows.replaceChildren();(event.detail?.fields || []).forEach(addRow);serialize();message('Fields detected from the prepared Word template. Check their types and required settings before saving.');});
    form.addEventListener('submit',async event=>{
      event.preventDefault();if(saving)return;serialize();if(!form.reportValidity())return;
      const payload=new FormData(form),submit=form.querySelector('[type="submit"]');
      saving=true;if(submit)submit.disabled=true;form.inert=true;message('Checking tags and saving template…');
      try {
        const response=await fetch(form.action,{method:'POST',body:payload,credentials:'same-origin',headers:{Accept:'application/json','X-Requested-With':'TemplateSetup'},signal:AbortSignal.timeout(45000)});
        if(!(response.headers.get('Content-Type') || '').includes('application/json'))throw new Error('Your session may have expired. Sign in again and retry.');
        const result=await response.json();
        if(!response.ok || !result.ok)throw new Error(result.message || 'Template could not be saved. Check the details and retry.');
        message('Template saved.');window.location.assign(result.redirect);
      } catch(error) {
        message(['TimeoutError','AbortError'].includes(error.name)?'Saving took longer than expected. Check Saved templates before retrying. Your field entries are still here.':error.message,true);
      } finally {saving=false;form.inert=false;if(submit)submit.disabled=false;}
    });
    load();
  });
})();
