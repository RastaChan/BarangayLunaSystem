const fs=require('node:fs'); const vm=require('node:vm'); const assert=require('node:assert/strict');
function field(value='') {return {value,handlers:{},addEventListener(type,fn){this.handlers[type]=fn;}};}
function fixture(action,initialYear) {
  const inputs={action:field(action),document_type:field('Barangay Certification'),document_type_custom:field(),name:field('Old name'),effective_year:field(initialYear),template_docx:field()}; inputs.template_docx.files=[];
  const form={dataset:{templateCurrentYear:'2026'},handlers:{},querySelector:selector=>inputs[selector.match(/name="([^"]+)/)[1]],addEventListener(type,fn){this.handlers[type]=fn;}};
  vm.runInNewContext(fs.readFileSync('js/template-auto-fields.js','utf8'),{document:{querySelectorAll:()=>[form]},setTimeout:fn=>fn()}); return {inputs,form};
}
let {inputs,form}=fixture('save_template','1900');
assert.equal(inputs.name.value,'Barangay Certification 2026 Official Format'); assert.equal(inputs.effective_year.value,'2026');
inputs.document_type.value='Certificate of Indigency'; inputs.document_type.handlers.change(); assert.equal(inputs.name.value,'Certificate of Indigency 2026 Official Format');
inputs.document_type.value='__new__'; inputs.document_type.handlers.change(); assert.equal(inputs.name.value,'');
inputs.document_type_custom.value='Custom Certificate'; inputs.document_type_custom.handlers.input(); assert.equal(inputs.name.value,'Custom Certificate 2026 Official Format');
({inputs,form}=fixture('update_template','2024'));
assert.equal(inputs.effective_year.value,'2024');
inputs.template_docx.files=[{}]; inputs.template_docx.handlers.change(); assert.equal(inputs.name.value,'Barangay Certification 2026 Official Format');
inputs.template_docx.files=[]; inputs.template_docx.handlers.change(); assert.equal(inputs.effective_year.value,'2024');
console.log('PASS: names follow document selection and custom types; current-year uploads and historical-year edits stay consistent.');
