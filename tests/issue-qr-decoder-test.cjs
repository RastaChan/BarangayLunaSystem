// Pipe the JSON output of issue-qr-pixels.php into this test.
const assert=require('node:assert/strict'),fs=require('node:fs');
const decoder=require('../js/vendor/jsQR.js');
const image=JSON.parse(fs.readFileSync(0,'utf8'));
const result=decoder(new Uint8ClampedArray(image.pixels),image.width,image.height,{inversionAttempts:'attemptBoth'});
assert.equal(result?.data,'LUNA|RES-TEST|CAMERA-PHOTO','The real bundled decoder reads locally generated resident QR pixels');
const blank=new Uint8ClampedArray(image.pixels.length).fill(255);
assert.equal(decoder(blank,image.width,image.height,{inversionAttempts:'attemptBoth'}),null,'Blank images cannot produce a resident code');
console.log('PASS: real bundled QR decoder reads a generated resident QR and rejects a blank image');
