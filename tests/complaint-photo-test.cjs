const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync('js/dashboard.js', 'utf8').replace(/\r\n/g, '\n');
const start = source.indexOf("  document.addEventListener('click', event => {\n      const btn = event.target.closest('[data-view-record]');");
const end = source.indexOf("  document.addEventListener('click', async event => {", start);
assert.ok(start >= 0 && end > start);
let focused;
const element = tag => ({tag, children: [], style: {}, attributes: {}, closest() {return this.recordBody || null;}, querySelectorAll() {return flatten(this).filter(child => 'data-complaint-section' in child.attributes);}, remove() {this.parent.children = this.parent.children.filter(child => child !== this);}, setAttribute(name, value) {this.attributes[name] = value;}, focus() {focused = this;}, querySelector() {const nodes = this.children.flatMap(child => [child, ...flatten(child)]); return nodes.find(child => child.tag === 'select');}, append(...nodes) {nodes.forEach(node=>{node.parent=this;});this.children.push(...nodes);}, appendChild(node) {node.parent=this;this.children.push(node);}, set innerHTML(value) {this.children = [];}});
function flatten(node) {return node.children.flatMap(child => [child, ...flatten(child)]);}
const container = element('div'), title = element('h2'), body = element('div');
container.recordBody = body; body.appendChild(container);
let handler, opened;
let updateUrl = null, editing = 'false';
let attachments = [{id: 17, mime_type: 'image/jpeg', filename: 'Complaint photo.jpg'}, {id: 18, mime_type: 'application/pdf', filename: 'Evidence.pdf'}];
const button = {getAttribute: key => key === 'data-title' ? 'Complaint' : key === 'data-record-attachments' ? JSON.stringify(attachments) : key === 'data-complaint-update-url' ? updateUrl : key === 'data-complaint-edit' ? editing : JSON.stringify({id: 'CMP-1', subject: '<Concern>', details: '<script>plain text</script>', status: 'In Progress', remarks: 'Existing remarks'}), closest: () => null};
vm.runInNewContext(source.slice(start, end), {
  document: {addEventListener: (name, fn) => handler = fn, querySelector: selector => selector === '[data-record-view]' ? container : title, createElement: element},
  openModal: id => opened = id,
});
handler({target: {closest: () => button}});
let evidence = body.children.find(child => child.tag === 'section');
assert.equal(evidence.parent,body,'Attachment sections sit outside the narrow detail grid');
assert.equal(opened, 'viewRecordModal');
assert.equal(evidence.children[1].children[0].children[0].src, 'record-attachment.php?id=17', 'View shows the protected complaint photo inside the modal');
assert.equal(evidence.children[2].children[0].href, 'record-attachment.php?id=18', 'PDF can be opened from the same View modal');
assert.equal(evidence.children[2].children[0].children[0].textContent, 'Open PDF');
assert.equal(container.children[2].children[1].textContent, '<script>plain text</script>', 'Complaint details remain plain text');
const photo = evidence.children[1].children[0].children[0];
photo.onerror();
assert.equal(photo.hidden, true);
assert.match(evidence.children[1].children[1].textContent, /could not load/);
attachments = [];
handler({target: {closest: () => button}});
evidence = body.children.find(child => child.tag === 'section');
assert.equal(body.children.filter(child=>child.tag==='section').length,1,'Reopening a complaint clears its old attachment section');
assert.equal(evidence.children[1].textContent, 'No photo or attachment was submitted.', 'Opening an unattached complaint clears the previous photo');
for (const role of ['staff', 'admin']) {
  updateUrl = role + '-actions.php'; editing = 'true';
  handler({target: {closest: () => button}});
  const editor = body.children.find(child => child.tag === 'form');
  assert.equal(editor.attributes.action, updateUrl);
  assert.equal(editor.method, 'post');
  const nodes = flatten(editor);
  const fields = Object.fromEntries(nodes.filter(child => child.tag === 'input').map(child => [child.name, child.value]));
  assert.equal(fields.action, 'update_complaint_status'); assert.equal(fields.id, 'CMP-1');
  assert.equal(fields.redirect, role === 'staff' ? 'staff-complaints' : 'complaints');
  assert.equal(nodes.find(child => child.tag === 'textarea').value, 'Existing remarks');
  const select = nodes.find(child => child.tag === 'select');
  assert.equal(select.children.find(child => child.selected).value, 'In Progress');
  assert.equal(select.children.length, 4);
  assert.equal(focused, select, 'Update status focuses the editor inside the popup');
  assert.equal(nodes.find(child => child.tag === 'button').type, 'submit');
}
updateUrl = 'https://example.com/'; editing = 'false';
handler({target: {closest: () => button}});
assert.equal(body.children.some(child => child.tag === 'form'), false, 'Only the authorized local update endpoints receive complaint forms');
console.log('PASS: complaint photos in View, PDF viewing links, safe text, failed-image recovery and empty attachments.');
