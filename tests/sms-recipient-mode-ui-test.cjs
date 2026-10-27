const {chromium} = require('playwright');
const fs = require('node:fs');
const assert = require('node:assert/strict');

(async () => {
  const browser = await chromium.launch({channel:'msedge',headless:true});
  try {
    const page = await browser.newPage();
    const data = {residents:[{user_code:'RES-ONE',full_name:'Registered Resident',mobile:'09171234567'}],requests:[{request_code:'REQ-ONE',resident_name:'Walk-in Applicant',mobile:'09981234567',status:'Ready for Pickup',document_type:'Certificate',fee_amount:150,ready_message:'Your document is ready.'}],prefix:'BRGY LUNA'};
    await page.setContent(`<form id="smsCompose"><input name="csrf" value="test"><select id="smsTemplate" name="template"><option value="custom">Custom</option><option value="complaint">Complaint</option><option value="ready">Ready</option></select><div id="smsRequestFields"><input id="smsRequestSearch"><select id="smsRequest" name="request_code"><option value="">Choose</option><option value="REQ-ONE">Walk-in request</option></select></div><div id="smsResidentFields"><select id="smsRecipientMode" name="recipient_mode"><option value="registered">Registered</option><option value="manual">Unregistered</option></select><div id="smsRegisteredFields"><input id="smsResidentSearch"><div id="smsResidentSearchStatus"></div><div id="smsResidentResults" hidden></div><select id="smsResident" name="resident_code"><option value="">Choose resident</option><option value="RES-ONE">Registered Resident</option></select></div></div><input id="smsName" name="recipient_name" required><input id="smsMobile" name="recipient_mobile" required><textarea id="smsMessage" name="message" required></textarea><input id="smsRequestStatus"><input id="smsRequestFee"><span id="smsPreviewText"></span><span id="smsPreviewRecipient"></span><span id="smsPreviewFee"></span><span id="smsLength"></span><span id="smsComposeError"></span><button id="smsReview" type="button">Review</button><dialog id="smsConfirm"><span id="smsConfirmRecipient"></span><span id="smsConfirmMessage"></span><input id="smsConfirmed" type="checkbox"><button id="smsCancel" type="button">Back</button><button type="submit">Send SMS</button></dialog></form><script id="smsComposeData" type="application/json">${JSON.stringify(data)}</script>`);
    await page.addScriptTag({content:fs.readFileSync('js/sms-workspace.js','utf8')});
    await page.selectOption('#smsResident','RES-ONE');
    assert.equal(await page.locator('#smsName').inputValue(),'Registered Resident');
    assert.equal(await page.locator('#smsMobile').evaluate(el=>el.readOnly),true);
    await page.selectOption('#smsRecipientMode','manual');
    assert.equal(await page.locator('#smsRegisteredFields').isVisible(),false);
    assert.equal(await page.locator('#smsName').inputValue(),'');
    assert.equal(await page.locator('#smsMobile').evaluate(el=>el.readOnly),false);
    await page.locator('#smsName').fill('Unregistered Recipient');
    await page.locator('#smsMobile').fill('09987654321');
    await page.selectOption('#smsTemplate','complaint');
    assert.equal(await page.locator('#smsName').inputValue(),'Unregistered Recipient');
    assert.match(await page.locator('#smsMessage').inputValue(),/contact the Barangay Luna office/);
    assert.doesNotMatch(await page.locator('#smsMessage').inputValue(),/sign in/);
    await page.locator('#smsReview').click();
    assert.equal(await page.locator('#smsConfirm').evaluate(el=>el.open),true);
    await page.locator('#smsConfirmed').check();
    await page.evaluate(()=>document.getElementById('smsCompose').addEventListener('submit',event=>{event.preventDefault();window.payload=Object.fromEntries(new FormData(event.target));}));
    await page.getByRole('button',{name:'Send SMS',exact:true}).click();
    const payload=await page.evaluate(()=>window.payload);
    assert.equal(payload.recipient_mode,'manual');
    assert.equal(payload.recipient_name,'Unregistered Recipient');
    assert.equal(payload.recipient_mobile,'09987654321');
    assert.equal(payload.resident_code,undefined);
    await page.locator('#smsCancel').click();
    await page.selectOption('#smsRecipientMode','registered');
    assert.equal(await page.locator('#smsResident').evaluate(el=>el.required),true);
    await page.selectOption('#smsResident','RES-ONE');
    assert.equal(await page.locator('#smsName').inputValue(),'Registered Resident');
    await page.selectOption('#smsTemplate','ready');
    await page.selectOption('#smsRequest','REQ-ONE');
    assert.equal(await page.locator('#smsName').inputValue(),'Walk-in Applicant');
    assert.equal(await page.locator('#smsMobile').evaluate(el=>el.readOnly),true);
    assert.equal(await page.locator('#smsRecipientMode').isEnabled(),false);
    console.log('PASS: Registered and unregistered modes, editable manual details, complaint wording, review confirmation, unlinked manual submission, and walk-in document recipient. No SMS sent.');
  } finally {await browser.close();}
})().catch(error=>{console.error(error);process.exit(1);});
