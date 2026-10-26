/* Analyze editable Word text, review exact replacements, and download/use a prepared copy. */
(() => {
  'use strict';
  const tool=document.querySelector('[data-template-preparation-tool]');if(!tool)return;
  const form=tool.closest('form'),upload=form.querySelector('[name="template_docx"]');
  const status=tool.querySelector('[data-template-preparation-status]'),review=tool.querySelector('[data-template-preparation-review]');
  const rows=tool.querySelector('[data-template-replacements]'),preview=tool.querySelector('[data-template-text-preview]');
  const analyze=tool.querySelector('[data-template-analyze]'),manual=tool.querySelector('[data-template-manual-add]'),checked=tool.querySelector('[data-template-reviewed]');
  const download=tool.querySelector('[data-template-download]'),use=tool.querySelector('[data-template-use]');
  const extraLabel=tool.querySelector('[data-template-extra-label]'),extraType=tool.querySelector('[data-template-extra-type]'),extraAdd=tool.querySelector('[data-template-extra-add]');
  let plan=null,items=[],extras=[],busy=false,version=0,currentFile=null;
  const points=value=>Array.from(value || '');
  const message=(text,error=false)=>{status.textContent=text;status.dataset.error=String(error);};
  function controls(){analyze.disabled=busy;manual.disabled=busy;extraAdd.disabled=busy;download.disabled=use.disabled=busy || !plan || !checked.checked;review.inert=busy;}
  async function request(payload) {
    payload.set('csrf',form.querySelector('[name="template_fields_csrf"]').value);
    const response=await fetch('template-preparation.php',{method:'POST',body:payload,credentials:'same-origin',headers:{Accept:'application/json'},signal:AbortSignal.timeout(45000)});
    if(!(response.headers.get('Content-Type') || '').includes('application/json'))throw new Error('Sign in again and reopen Template Management.');
    const result=await response.json();if(!response.ok || !result.ok)throw new Error(result.message || 'The Word file could not be prepared.');return result;
  }
  function resetReview(){checked.checked=false;controls();}
  function fieldSelect(value) {
    const select=document.createElement('select');
    plan.catalog.forEach(field=>{const option=document.createElement('option');option.value=field.key;option.textContent=field.label+' · {{'+field.key+'}}';select.append(option);});select.value=value;return select;
  }
  function label(text,control){const wrapper=document.createElement('label');wrapper.className='field';const caption=document.createElement('span');caption.textContent=text;wrapper.append(caption,control);return wrapper;}
  function selectedMappings() {
    const selected=[];
    for(const item of items) {
      if(!item.enabled)continue;
      const paragraph=plan.paragraphs.find(p=>p.id===item.paragraph);
      if(!paragraph)throw new Error('Choose a paragraph for every replacement.');
      if(item.manual) {
        if(!item.source.trim())throw new Error('Enter the exact text to replace, or uncheck the empty replacement.');
        const index=paragraph.text.indexOf(item.source);
        if(index<0)throw new Error('The manual text was not found in its selected paragraph. Copy it exactly from the preview.');
        if(paragraph.text.indexOf(item.source,index+1)!==-1)throw new Error('The manual text appears more than once in that paragraph. Select a longer unique phrase.');
        selected.push({paragraph:item.paragraph,start:points(paragraph.text.slice(0,index)).length,length:points(item.source).length,source:item.source,key:item.key});
      } else selected.push({paragraph:item.paragraph,start:item.start,length:item.length,source:item.source,key:item.key});
    }
    for(const paragraph of plan.paragraphs) {
      const values=selected.filter(item=>item.paragraph===paragraph.id).sort((a,b)=>a.start-b.start);
      for(let i=1;i<values.length;i++)if(values[i].start<values[i-1].start+values[i-1].length)throw new Error('Two replacements select the same text. Uncheck one of them.');
    }
    return selected;
  }
  function updatePreview() {
    if(!plan)return;
    let mappings=[];
    try {mappings=selectedMappings();message('Review the highlighted tags, then confirm below.');}
    catch(error){message(error.message,true);}
    preview.replaceChildren();
    plan.paragraphs.forEach(paragraph=>{
      const row=document.createElement('p');const chars=points(paragraph.text);let end=0;
      mappings.filter(item=>item.paragraph===paragraph.id).sort((a,b)=>a.start-b.start).forEach(item=>{
        const before=document.createElement('span');before.textContent=chars.slice(end,item.start).join('');
        const tag=document.createElement('mark');tag.textContent='{{'+item.key+'}}';row.append(before,tag);end=item.start+item.length;
      });
      const tail=document.createElement('span');tail.textContent=chars.slice(end).join('');row.append(tail);preview.append(row);
    });
  }
  function renderRows() {
    rows.replaceChildren();
    if(!items.length){const empty=document.createElement('p');empty.className='field-hint';empty.textContent=plan.existing_tags.length?'Existing tags found. They will be kept unchanged. Add replacements only for other text that should vary.':'No confident suggestions were found. Add a text replacement to choose the text and its field.';rows.append(empty);}
    items.forEach(item=>{
      const row=document.createElement('div');row.className='template-replacement-row';
      const check=document.createElement('input');check.type='checkbox';check.checked=item.enabled;
      const enabled=document.createElement('label');enabled.className='check check-left';const checkText=document.createElement('span');checkText.textContent='Replace this text';enabled.append(check,checkText);row.append(enabled);
      const source=document.createElement(item.manual?'input':'strong');
      if(item.manual){source.type='text';source.value=item.source;source.placeholder='Copy the exact text from the preview';source.maxLength=500;}
      else source.textContent=item.source;
      row.append(label('Text in the Word file',source));
      if(item.manual) {
        const paragraph=document.createElement('select');plan.paragraphs.forEach((p,index)=>{const option=document.createElement('option');option.value=p.id;option.textContent=(index+1)+'. '+p.text.slice(0,100);paragraph.append(option);});paragraph.value=item.paragraph;
        row.append(label('Paragraph containing the text',paragraph));paragraph.addEventListener('change',()=>{item.paragraph=paragraph.value;resetReview();updatePreview();});
        source.addEventListener('input',()=>{item.source=source.value;resetReview();updatePreview();});
      } else { /* The reason follows the two aligned field controls below. */ }
      const field=fieldSelect(item.key);row.append(label('Replace with this field',field));
      if(!item.manual){const reason=document.createElement('small');reason.textContent=item.reason;row.append(reason);}else row.className+=' template-replacement-row--manual';
      check.addEventListener('change',()=>{item.enabled=check.checked;resetReview();updatePreview();});
      field.addEventListener('change',()=>{item.key=field.value;resetReview();updatePreview();});
      rows.append(row);
    });
  }
  upload.addEventListener('change',()=>{
    version++;currentFile=upload.files?.[0] || null;plan=null;items=[];extras=[];review.hidden=true;busy=false;checked.checked=false;message('');controls();
  });
  analyze.addEventListener('click',async()=>{
    if(busy)return;
    const file=upload.files?.[0];if(!file){message('Choose a DOCX in Upload Word template above first.',true);upload.focus();return;}
    const current=++version;busy=true;plan=null;review.hidden=true;controls();message('Reading the Word file and finding possible fill-in details…');
    const payload=new FormData();payload.set('action','analyze');payload.set('template_docx',file);payload.set('definitions',form.querySelector('[name="custom_field_definitions"]')?.value || '[]');
    try {
      const result=await request(payload);if(current!==version)return;
      plan=result;currentFile=file;extras=[];items=result.suggestions.map(item=>({...item,enabled:true,manual:false}));
      review.hidden=false;checked.checked=false;renderRows();updatePreview();
      message(`${items.length} suggested replacement${items.length===1?'':'s'}; ${result.existing_tags.length} existing tag${result.existing_tags.length===1?'':'s'} kept. Review all highlights before continuing.`);
    } catch(error){if(current===version)message(['TimeoutError','AbortError'].includes(error.name)?'Analysis took too long. Try a smaller DOCX file.':error.message,true);}
    finally {if(current===version){busy=false;controls();}}
  });
  manual.addEventListener('click',()=>{
    if(!plan || busy)return;
    items.push({manual:true,enabled:true,source:'',paragraph:plan.paragraphs[0].id,key:'resident_name_proper'});resetReview();renderRows();updatePreview();
  });
  extraAdd.addEventListener('click',()=>{
    if(!plan || busy)return;
    if(extras.length>=20){message('Use up to 20 new field choices for one template.',true);return;}
    const label=extraLabel.value.trim();if(!label){message('Enter a label for the new field choice.',true);extraLabel.focus();return;}
    let base=label.normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase().replace(/[^a-z0-9]+/g,'_').replace(/^_+|_+$/g,'') || 'additional_detail';if(!/^[a-z]/.test(base))base='additional_'+base;if(base.length<2)base+='_detail';base=base.slice(0,40);
    let key=base,n=2;while(plan.catalog.some(field=>field.key===key)){const suffix='_'+n++;key=base.slice(0,40-suffix.length)+suffix;}
    const field={key,label,type:extraType.value,required:false,options:[],standard:false};extras.push(field);plan.catalog.push(field);extraLabel.value='';resetReview();renderRows();updatePreview();message('Field choice added. Select it in a text replacement.');
  });
  checked.addEventListener('change',controls);
  async function prepare(mode) {
    if(busy || !plan || !checked.checked)return;
    if(upload.files?.[0]!==currentFile){message('The uploaded file changed. Analyze it again before preparing.',true);return;}
    let mappings;try{mappings=selectedMappings();}catch(error){message(error.message,true);return;}
    if(!mappings.length && !plan.existing_tags.length){message('Choose a text replacement before preparing this file.',true);return;}
    const current=version;busy=true;controls();message('Preparing the reviewed Word template…');
    const payload=new FormData();payload.set('action','prepare');payload.set('token',plan.token);payload.set('mappings',JSON.stringify(mappings));payload.set('new_fields',JSON.stringify(extras.map(({standard,...field})=>field)));payload.set('reviewed','1');
    try {
      const result=await request(payload);if(current!==version)return;
      if(mode==='download'){
        const link=document.createElement('a');link.href=result.url;link.download=result.name;document.body.append(link);link.click();link.remove();message('Prepared DOCX is ready. Check the downloaded Word file before using it.');
      } else {
        const response=await fetch(result.url,{credentials:'same-origin',cache:'no-store'});
        if(!response.ok || !(response.headers.get('Content-Type') || '').includes('wordprocessingml'))throw new Error('The prepared file could not be loaded. Use Download prepared DOCX, then choose that file above.');
        const blob=await response.blob();if(current!==version)return;
        if(typeof DataTransfer==='undefined')throw new Error('Download the prepared DOCX, then choose it in Upload Word template above.');
        const transfer=new DataTransfer();transfer.items.add(new File([blob],result.name,{type:'application/vnd.openxmlformats-officedocument.wordprocessingml.document'}));upload.files=transfer.files;
        upload.dispatchEvent(new Event('change',{bubbles:true}));form.dispatchEvent(new CustomEvent('template-fields-import',{detail:{fields:result.fields}}));
        message('Prepared Word file selected, and additional fields added below. Check the document type, field settings and template status, then Save / replace template.');
      }
    } catch(error){message(['TimeoutError','AbortError'].includes(error.name)?'Preparation took too long. Try again; the source file is still unchanged.':error.message,true);}
    finally {busy=false;controls();}
  }
  download.addEventListener('click',()=>prepare('download'));use.addEventListener('click',()=>prepare('use'));controls();
})();
