const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
let click;
let reduced = false;
const animations = [];
const group = {
  tagName: 'DETAILS', open: false, style: {}, dataset: {}, classList: {contains: () => false},
  getBoundingClientRect() { return {height: this.open ? 240 : 44}; },
  animate(frames, options) {
    const animation = {frames, options, cancel() { this.cancelled = true; }};
    animations.push(animation);
    return animation;
  }
};
const summary = {parentElement: group};
const event = {target: {closest: selector => selector === 'summary' ? summary : null}, preventDefault() {}};
vm.runInNewContext(fs.readFileSync('js/location-disclosures.js', 'utf8'), {
  document: {body: {contains: () => true, addEventListener: (_, handler) => {click = handler;}}},
  window: {matchMedia: () => ({matches: reduced})}
});
click(event);
assert.equal(animations[0].frames[1].height, '240px');
animations[0].onfinish();
assert.equal(group.open, true);
click(event);
assert.equal(group.open, true, 'Closing content remains visible until animation finishes');
assert.equal(animations[1].frames[1].height, '44px');
click(event);
assert.equal(animations[1].cancelled, true, 'Rapid reversal cancels previous animation');
animations[2].onfinish();
assert.equal(group.open, true);
click(event);
animations[3].onfinish();
assert.equal(group.open, false);
assert.equal(group.style.overflow, '');
reduced = true;
click(event);
assert.equal(group.open, true);
assert.equal(animations.length, 4, 'Reduced motion skips animation');
console.log('PASS: opening, closing, rapid reversal, style cleanup and reduced motion.');
