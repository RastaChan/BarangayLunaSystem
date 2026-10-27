(() => {
  'use strict';
  const fields = new Set(['first_name','middle_name','last_name','full_name','suffix',
    'address','resident_name','beneficiary_name','partner_name','walkin_name','walkin_address',
    'complainant_name','respondent_name','respondent_address','venue','position','position_title',
    'guardian_name','spouse_name','father_name','mother_name','recipient_name']);
  function eligible(input) {
    if (!(input instanceof HTMLInputElement) || input.disabled || input.readOnly || input.type !== 'text' || input.dataset.formalCase === 'off') return false;
    return fields.has(input.name) || input.dataset.formalCase === 'words' ||
      /^(?:district_names|purok_names|purok_chairmen)\[/.test(input.name) ||
      /^purok_entries\[\d+\]\[(?:name|chairman)\]$/.test(input.name) ||
      (input.name === 'name' && input.form?.querySelector('[name="action"]')?.value === 'update_staff');
  }
  function normalize(input) {
    if (!eligible(input)) return;
    const value = input.value;
    const corrected = value.replace(/(^|[\s\-'’(/])(\p{Ll})/gu, (match, separator, letter) => separator + letter.toLocaleUpperCase('en-PH'));
    if (corrected === value) return;
    const start = input.selectionStart, end = input.selectionEnd, direction = input.selectionDirection;
    const correctedLength = index => value.slice(0,index).replace(/(^|[\s\-'’(/])(\p{Ll})/gu, (match, separator, letter) => separator + letter.toLocaleUpperCase('en-PH')).length;
    input.value = corrected;
    if (start !== null && end !== null) input.setSelectionRange(correctedLength(start),correctedLength(end),direction || 'none');
  }
  document.addEventListener('input', event => { if (!event.isComposing) normalize(event.target); }, true);
  document.addEventListener('compositionend', event => normalize(event.target), true);
  document.addEventListener('change', event => normalize(event.target), true);
  document.addEventListener('submit', event => event.target.querySelectorAll('input').forEach(normalize), true);
})();
