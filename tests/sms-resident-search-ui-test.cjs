const {chromium} = require('playwright');
const fs = require('node:fs');
const assert = require('node:assert/strict');

(async () => {
  const browser = await chromium.launch({channel: 'msedge', headless: true});
  try {
    const page = await browser.newPage();
    await page.route('http://sms.test/**', route => route.fulfill({body: '<html><body></body></html>', contentType: 'text/html'}));
    await page.goto('http://sms.test/');
    const ids = ['smsCompose','smsResidentSearch','smsResidentResults','smsResidentSearchStatus'];
    await page.setContent('<form id="smsCompose"><input name="csrf" value="test-token"><input id="smsResidentSearch"><div id="smsResidentSearchStatus"></div><div id="smsResidentResults" hidden></div><select id="recipient"><option value="">Manual</option></select><input id="name"><input id="mobile"></form>');
    await page.addStyleTag({content: fs.readFileSync('css/sms-workspace.css','utf8')});
    let searches = [];
    await page.route('**/resident-service-lookup.php', async route => {
      const params = new URLSearchParams(route.request().postData());
      searches.push(params.get('search'));
      assert.equal(params.get('csrf'), 'test-token');
      assert.equal(params.get('kind'), 'sms-resident-search');
      const query = params.get('search');
      const residents = query === 'none' ? [] : [{user_code:'RES-NEW',full_name:'New Registered Resident',mobile:'09171234567'}];
      await route.fulfill({json: {ok:true,residents}});
    });
    const source = fs.readFileSync('js/sms-workspace.js','utf8');
    const searchCode = source.slice(source.indexOf('  const searchInput ='), source.indexOf('  function review()'));
    await page.evaluate(code => {
      window.updates = 0;
      const bootstrap = `const get=id=>document.getElementById(id), form=get('smsCompose'), resident=get('recipient'), residents=new Map(); resident.addEventListener('change',()=>{const row=residents.get(resident.value);get('name').value=row.full_name;get('mobile').value=row.mobile;window.updates++;});`;
      new Function(bootstrap + code)();
    }, searchCode);
    await page.locator('#smsResidentSearch').fill('New');
    await page.getByRole('button',{name:/New Registered Resident/}).click();
    assert.equal(await page.locator('#name').inputValue(),'New Registered Resident');
    assert.equal(await page.locator('#mobile').inputValue(),'09171234567');
    assert.equal(await page.locator('#recipient').inputValue(),'RES-NEW');
    assert.equal(await page.evaluate(()=>window.updates),1);
    await page.locator('#smsResidentSearch').fill('none');
    await page.getByRole('status').count();
    await page.waitForFunction(()=>document.getElementById('smsResidentSearchStatus').textContent.includes('No active residents'));
    assert.equal(await page.locator('#smsResidentResults').isVisible(),false);
    await page.locator('#smsResidentSearch').fill('R');
    await page.locator('#smsResidentSearch').fill('RES');
    await page.getByRole('button',{name:/New Registered Resident/}).waitFor();
    await page.locator('#smsResidentSearch').press('ArrowDown');
    await page.keyboard.press('Enter');
    assert.equal(await page.evaluate(()=>window.updates),2);
    await page.setViewportSize({width:375,height:812});
    await page.locator('#smsResidentSearch').fill('Resident');
    await page.getByRole('button',{name:/New Registered Resident/}).waitFor();
    assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),true);
    assert.ok(!searches.includes('R'),'Debounced typing must skip superseded searches');
    console.log('PASS: Visible live results, newly registered recipient selection, name/mobile autofill, empty results, keyboard selection, debounce, and mobile width.');
  } finally { await browser.close(); }
})().catch(error => {console.error(error);process.exit(1);});
