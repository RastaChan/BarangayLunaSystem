const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const elements = new Map();
function element(value = '') { return {value, hidden:false, disabled:false, textContent:'',handlers:{}, addEventListener(type, fn) {this.handlers[type]=fn;},setAttribute(name,value) {this[name]=value;}}; }
for (const key of ['search','from','to','previous','next','empty','count','page','date-toggle','date-reset','date-error']) elements.set(`[data-payment-${key}]`, element());
elements.set('#paymentDateFilters', element()); elements.get('#paymentDateFilters').hidden = true;
const rows = Array.from({length:25}, (_,i) => ({textContent:`PAY-${i} ${i<5 ? 'Judith' : 'Christian'} OR-${i}`,dataset:{date:i<5 ? '2026-10-01':'2026-10-10'},hidden:false}));
const ledger = {querySelector: selector => elements.get(selector), querySelectorAll: () => rows};
vm.runInNewContext(fs.readFileSync('js/payment-ledger.js','utf8'), {document:{querySelector:()=>ledger}});
const get = key => elements.get(`[data-payment-${key}]`);
assert.equal(rows.filter(x=>!x.hidden).length,20);
get('next').handlers.click(); assert.equal(rows.filter(x=>!x.hidden).length,5); assert.equal(get('page').textContent,'2 / 2');
get('search').value='judith'; get('search').handlers.input(); assert.equal(rows.filter(x=>!x.hidden).length,5); assert.equal(get('page').textContent,'1 / 1');
get('from').value='2026-10-05'; get('from').handlers.change(); assert.equal(rows.filter(x=>!x.hidden).length,0); assert.equal(get('empty').hidden,false);
get('date-reset').handlers.click(); assert.equal(rows.filter(x=>!x.hidden).length,5); assert.equal(get('from').value,'');
get('search').value=''; get('search').handlers.input(); get('from').value='2026-10-11'; get('to').value='2026-10-01'; get('to').handlers.change(); assert.equal(get('date-error').hidden,false);
get('date-toggle').handlers.click(); assert.equal(elements.get('#paymentDateFilters').hidden,false); assert.equal(get('date-toggle')['aria-expanded'],'true');
console.log('PASS: pagination, search, date range, reset and invalid-date recovery');
