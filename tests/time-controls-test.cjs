const fs=require('node:fs'),vm=require('node:vm'),assert=require('node:assert/strict');
const source=fs.readFileSync('js/time-controls.js','utf8');
const helpers=source.slice(source.indexOf('  const parse'),source.indexOf('  function enhance'));
const math={};vm.runInNewContext(helpers+'\nthis.parseTime=parse;this.serializeTime=serialize;',math);
assert.equal(math.parseTime('00:05').clock,'12:05');assert.equal(math.parseTime('00:05').period,'AM');
assert.equal(math.parseTime('12:00').period,'PM');assert.equal(math.parseTime('23:59').clock,'11:59');
assert.equal(math.serializeTime('12:00','AM'),'00:00');assert.equal(math.serializeTime('12:00','PM'),'12:00');
assert.equal(math.serializeTime('2:30','PM'),'14:30');assert.equal(math.serializeTime('13:00','AM'),'');
assert.equal(math.serializeTime('2:60','PM'),'');assert.equal(math.serializeTime('2:30','PM','2026-10-28',true),'2026-10-28T14:30');
class Element {
 constructor(tag='input'){this.tag=tag;this.dataset={};this.events={};this.children=[];this.attributes={};this.type='text';this.disabled=false;this.readOnly=false;this.required=false;this.defaultValue='';this.name='start_time';}
 set value(value){this._value=String(value);} get value(){return this._value||'';}
 setAttribute(key,value){this.attributes[key]=value;} addEventListener(type,fn){(this.events[type]??=[]).push(fn);}
 dispatchEvent(event){(this.events[event.type]||[]).forEach(fn=>fn(event));}
 appendChild(node){this.children.push(node);} append(...nodes){this.children.push(...nodes);}
 insertAdjacentElement(position,node){this.control=node;} setCustomValidity(message){this.validityMessage=message;}
 querySelectorAll(){return [];} focus(){}
}
const original=new Element();original.type='time';original.required=true;original.value=original.defaultValue='14:30';
const eventTime=new Element();eventTime.type='datetime-local';eventTime.value=eventTime.defaultValue='2026-10-28T00:15';
const form=new Element('form');original.form=form;
const document={body:new Element('body'),querySelectorAll:()=>[original,eventTime],createElement:tag=>new Element(tag)};
const timers=[];
vm.runInNewContext(source,{document,HTMLInputElement:Element,Event:class{constructor(type){this.type=type;}},MutationObserver:class{constructor(callback){this.callback=callback;}observe(){}},setTimeout:fn=>timers.push(fn)});
const [clock,period]=original.control.children;
assert.equal(original.type,'hidden');assert.equal(clock.value,'2:30');assert.equal(period.value,'PM');assert.equal(clock.required,true);
clock.value='1';clock.dispatchEvent({type:'input'});assert.equal(clock.value,'1','Partial typing is not erased by a synthetic change event');assert.equal(original.value,'');
clock.value='1:15';clock.dispatchEvent({type:'input'});assert.equal(original.value,'13:15');
period.value='AM';period.dispatchEvent({type:'change'});assert.equal(original.value,'01:15');
original.value='23:45';assert.equal(clock.value,'11:45');assert.equal(period.value,'PM');assert.equal(clock.validityMessage,'');
form.dispatchEvent({type:'reset'});original.value=original.defaultValue;timers.forEach(fn=>fn());assert.equal(clock.value,'2:30');
original.disabled=true;original.value='10:00';assert.equal(clock.disabled,true);assert.equal(period.disabled,true);assert.equal(clock.required,false);
const [eventDate,eventClock,eventPeriod]=eventTime.control.children;
assert.equal(eventDate.value,'2026-10-28');assert.equal(eventClock.value,'12:15');assert.equal(eventPeriod.value,'AM');
eventClock.value='3:45';eventPeriod.value='PM';eventPeriod.dispatchEvent({type:'change'});assert.equal(eventTime.value,'2026-10-28T15:45');
eventTime.value='2026-11-01T12:00';assert.equal(eventDate.value,'2026-11-01');assert.equal(eventClock.value,'12:00');assert.equal(eventPeriod.value,'PM');
console.log('PASS: midnight/noon conversion, PM scheduling, invalid times, partial typing, programmatic rescheduling, reset and disabled state.');
