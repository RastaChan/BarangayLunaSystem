const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
class Element {
  constructor() { this.dataset = {}; this.attributes = {}; this.children = []; this.listeners = {}; this.value = ''; this.classList = {toggle() {}}; }
  setAttribute(name, value) { this.attributes[name] = value; }
  append(...children) { this.children.push(...children); }
  appendChild(child) { this.children.push(child); }
  replaceChildren(fragment) { this.children = fragment.children; }
  addEventListener(name, listener) { this.listeners[name] = listener; }
  dispatchEvent(event) { this.listeners[event.type]?.(event); }
  querySelector() { return null; }
}
const nodes = Object.fromEntries(['status','officer','period','from','to','no-match','date-range','calendar','agenda-label','month-label','calendar-grid'].map(key => [key, new Element()]));
nodes.status.value = 'active'; nodes.period.value = 'upcoming';
const counts = Object.fromEntries(['today','upcoming','completed'].map(key => [key,new Element()]));
const modes = ['calendar','list'].map(mode => { const button = new Element(); button.dataset.husayMode = mode; return button; });
const root = new Element();
root.querySelector = selector => {
  const count = selector.match(/data-husay-count="(\w+)"/); if (count) return counts[count[1]];
  const key = selector.match(/data-husay-([a-z-]+)/)?.[1]; return nodes[key];
};
root.querySelectorAll = () => modes;
const today = new Intl.DateTimeFormat('en-CA',{timeZone:'Asia/Manila',year:'numeric',month:'2-digit',day:'2-digit'}).format(new Date());
const tomorrowDate = new Date(today + 'T00:00:00Z'); tomorrowDate.setUTCDate(tomorrowDate.getUTCDate()+1); const tomorrow = tomorrowDate.toISOString().slice(0,10);
const makeRow = (date,status,officer,text) => {const row = new Element(); row.dataset = {husayDate:date,husayStatus:status,husayOfficer:officer,husaySearchText:text};return row;};
const cards = [makeRow(today,'Scheduled','1','CMP-A Maria Room A'),makeRow(tomorrow,'Scheduled','2','CMP-B Juan Room B'),makeRow(today,'Completed','1','CMP-C Room A'),makeRow(today,'Cancelled','2','CMP-D Room B'),makeRow('2000-02-29','Completed','1','CMP-LEAP')];
const list = {querySelectorAll: () => cards, querySelector: () => null}, search = {value:''};
const context = {window:{},document:{activeElement:null,createElement:()=>new Element(),createDocumentFragment:()=>new Element()},Intl,Date,Event:class {constructor(type){this.type=type;}}};
vm.createContext(context); vm.runInContext(fs.readFileSync('js/husay-calendar.js','utf8'),context);
const workspace = context.window.barangayHusayWorkspace(root,list,search);
const click = dataset => root.listeners.click({target:{closest: selector => Object.keys(dataset).some(key => selector === '[data-' + key.replace(/[A-Z]/g,letter => '-'+letter.toLowerCase()) + ']') ? {dataset} : null}});
workspace.filter();
assert.deepEqual(cards.map(card=>!card.hidden),[true,false,false,false,false]);
assert.equal(counts.today.textContent,2); assert.equal(counts.upcoming.textContent,1); assert.equal(counts.completed.textContent,2);
const todayButton = nodes['calendar-grid'].children.find(child=>child.dataset.husayDay===today);
assert.equal(todayButton.attributes['aria-pressed'],'true'); assert.equal(todayButton.attributes['aria-current'],'date'); assert.match(todayButton.attributes['aria-label'],/1 session$/);
click({husayMode:'list'}); assert.deepEqual(cards.map(card=>!card.hidden),[true,true,false,false,false]); assert.equal(nodes.calendar.hidden,true);
nodes.officer.value='2'; nodes.officer.dispatchEvent({type:'change'}); assert.deepEqual(cards.map(card=>!card.hidden),[false,true,false,false,false]);
nodes.officer.value=''; search.value='maria'; workspace.filter(); assert.deepEqual(cards.map(card=>!card.hidden),[true,false,false,false,false]);
search.value=''; nodes.status.value='Completed'; nodes.period.value='all'; workspace.filter(); assert.deepEqual(cards.map(card=>!card.hidden),[false,false,true,false,true]);
nodes.period.value='custom'; nodes.from.value='2000-02-01'; nodes.to.value='2000-02-29'; nodes.period.dispatchEvent({type:'change'});
assert.deepEqual(cards.map(card=>!card.hidden),[false,false,false,false,true]); assert.equal(nodes['date-range'].hidden,false); assert.equal(nodes.to.min,'2000-02-01');
click({husayMode:'calendar'}); click({husayDay:'2000-02-29'}); assert.equal(cards[4].hidden,false);
assert.equal(nodes['calendar-grid'].children.filter(child=>child.dataset.husayDay).length,29);
click({husayMonth:'1'}); assert.match(nodes['month-label'].textContent,/March 2000/); assert.equal(nodes['no-match'].hidden,false);
click({husayReset:''}); assert.equal(search.value,''); assert.equal(nodes.status.value,'active'); assert.equal(nodes.period.value,'upcoming'); assert.equal(nodes.from.value,''); assert.equal(nodes.to.value,'');
assert.deepEqual(cards.map(card=>!card.hidden),[true,false,false,false,false]);
cards.push(makeRow(today,'In Progress','1','CMP-NEW')); workspace.filter(); assert.equal(counts.today.textContent,3); assert.equal(cards[5].hidden,false);
nodes.period.value='today'; click({husayToday:''}); assert.equal(nodes.period.value,'all'); assert.equal(nodes['date-range'].hidden,true);
console.log('PASS: calendar/list views, Manila dates, active/history filters, official and search filters, custom ranges, leap-year month navigation, live additions, counts and reset.');
