const {chromium}=require('playwright');
const assert=require('node:assert/strict');
const fs=require('node:fs');
(async()=>{
  const browser=await chromium.launch({channel:'msedge',headless:true});
  try {
    const page=await browser.newPage();
    await page.setContent('<form><input name="first_name"><input name="last_name"><input name="address"><input name="username"><input name="email" type="email"><input name="password" type="password"><input name="first_name" type="search"><input name="full_name" readonly value="Existing lowercase"><textarea name="message"></textarea><input name="beneficiary_name"><button>Save</button></form>');
    await page.addScriptTag({content:fs.readFileSync('js/formal-text.js','utf8')});
    await page.locator('[name="first_name"][type="search"]').fill('lowercase search');
    const first=page.locator('input[name="first_name"]:not([type="search"])');
    await first.pressSequentially('juan dela cruz');
    assert.equal(await first.inputValue(),'Juan Dela Cruz');
    await page.locator('[name="last_name"]').fill("o'neill-macDonald");
    assert.equal(await page.locator('[name="last_name"]').inputValue(),"O'Neill-MacDonald");
    await page.locator('[name="address"]').fill('p-2 sitio looc');
    assert.equal(await page.locator('[name="address"]').inputValue(),'P-2 Sitio Looc');
    for(const [name,value]of Object.entries({username:'small@luna.gov',email:'small@example.test',password:'lowerCasePass123',message:'keep my message'})){
      await page.locator(`[name="${name}"]`).fill(value);assert.equal(await page.locator(`[name="${name}"]`).inputValue(),value);
    }
    assert.equal(await page.locator('[name="first_name"][type="search"]').inputValue(),'lowercase search');
    assert.equal(await page.locator('[name="full_name"]').inputValue(),'Existing lowercase');
    await first.evaluate(el=>{el.value='juan cruz';el.focus();el.setSelectionRange(2,2);el.dispatchEvent(new InputEvent('input',{bubbles:true}));});
    assert.equal(await first.inputValue(),'Juan Cruz');
    assert.equal(await first.evaluate(el=>el.selectionStart),2);
    await page.evaluate(()=>{const el=document.createElement('input');el.name='respondent_name';el.id='dynamic';document.querySelector('form').append(el);});
    await page.locator('#dynamic').fill('josé ñeri');
    assert.equal(await page.locator('#dynamic').inputValue(),'José Ñeri');
    await page.locator('[name="beneficiary_name"]').evaluate(el=>{el.value='ana maria';el.dispatchEvent(new InputEvent('input',{bubbles:true,isComposing:true}));});
    assert.equal(await page.locator('[name="beneficiary_name"]').inputValue(),'ana maria');
    await page.locator('[name="beneficiary_name"]').evaluate(el=>el.dispatchEvent(new CompositionEvent('compositionend',{bubbles:true})));
    assert.equal(await page.locator('[name="beneficiary_name"]').inputValue(),'Ana Maria');
    await page.evaluate(()=>{document.querySelector('[name="beneficiary_name"]').value='pasted name';document.querySelector('form').addEventListener('submit',event=>{event.preventDefault();window.saved=Object.fromEntries(new FormData(event.target));});});
    await page.getByRole('button',{name:'Save'}).click();
    assert.equal(await page.evaluate(()=>window.saved.beneficiary_name),'Pasted Name');
    console.log('PASS: Live capitalization, paste, cursor position, dynamic modal inputs, Unicode/IME, submitted values and protected fields.');
  }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exit(1);});
