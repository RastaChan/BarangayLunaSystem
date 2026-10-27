(function () {
  'use strict';
  const form = document.getElementById('smsCompose');
  if (!form) return;
  const data = JSON.parse(document.getElementById('smsComposeData').textContent);
  const get = id => document.getElementById(id);
  const template = get('smsTemplate');
  const request = get('smsRequest');
  const resident = get('smsResident');
  const recipientMode = get('smsRecipientMode');
  const name = get('smsName');
  const mobile = get('smsMobile');
  const message = get('smsMessage');
  const dialog = get('smsConfirm');
  const confirmed = get('smsConfirmed');
  const requests = new Map(data.requests.map(row => [row.request_code, row]));
  const residents = new Map(data.residents.map(row => [row.user_code, row]));
  const isDocument = () => ['ready', 'reminder', 'status'].includes(template.value);
  const fee = row => 'PHP ' + Number(row.fee_amount).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
  function fullMessage() {
    const text = message.value.trim();
    const prefix = data.prefix ? data.prefix + ':' : '';
    return prefix && !text.toLowerCase().startsWith(prefix.toLowerCase()) ? prefix + ' ' + text : text;
  }
  function refreshPreview() {
    const text = fullMessage();
    const bytes = new TextEncoder().encode(text).length;
    get('smsPreviewText').textContent = text;
    get('smsPreviewRecipient').textContent = name.value ? name.value + ' - ' + mobile.value : 'No recipient selected';
    const row = isDocument() ? requests.get(request.value) : null;
    get('smsRequestStatus').value = row?.status || '';
    get('smsRequestFee').value = row?.fee_amount ?? '';
    get('smsPreviewFee').textContent = row ? fee(row) : 'Not applicable';
    get('smsLength').textContent = `${Array.from(text).length} characters | ${bytes}/918 bytes including sender`;
    const unfinished = ['Barangay Luna announcement:', 'Barangay Luna emergency advisory:'].includes(message.value.trim());
    message.setCustomValidity(unfinished ? 'Complete the message before sending.' : (bytes > 918 ? 'Shorten the message to fit the 918-byte limit, including the sender prefix.' : ''));
    confirmed.checked = false;
    confirmed.setCustomValidity('');
  }
  function populateMessage() {
    const row = requests.get(request.value);
    if (isDocument()) {
      if (!row) { message.value = ''; return; }
      const documentName = `${row.document_type} (${row.request_code})`;
      if (template.value === 'status') {
        message.value = `Your ${documentName} status is now ${row.status}. Document fee: ${fee(row)}. Check your account for details.`;
      } else {
        message.value = `${template.value === 'reminder' ? 'Reminder: ' : ''}${row.ready_message}`;
      }
    } else {
      message.value = ({complaint: recipientMode.value === 'manual' ? 'There is an update to your complaint. Please contact the Barangay Luna office for details.' : 'There is an update to your complaint. Please sign in to your Barangay Luna account and open My Complaints for details.', announcement: 'Barangay Luna announcement: ', emergency: 'Barangay Luna emergency advisory: ', custom: ''})[template.value] || '';
    }
  }
  function filterRequests() {
    const search = get('smsRequestSearch').value.toLowerCase().trim();
    Array.from(request.options).forEach(option => {
      if (!option.value) return;
      const row = requests.get(option.value);
      const eligible = !['ready', 'reminder'].includes(template.value) || row.status === 'Ready for Pickup';
      option.hidden = !eligible || !option.textContent.toLowerCase().includes(search);
      option.disabled = !eligible;
    });
    if (request.selectedOptions[0]?.disabled) request.value = '';
  }
  function updateRecipient(preserveManual = false) {
    const documentMode = isDocument();
    get('smsRequestFields').hidden = !documentMode;
    get('smsResidentFields').hidden = documentMode;
    request.disabled = !documentMode;
    request.required = documentMode;
    const manualMode = recipientMode.value === 'manual';
    recipientMode.disabled = documentMode;
    get('smsRegisteredFields').hidden = manualMode;
    get('smsResidentSearch').disabled = documentMode || manualMode;
    resident.disabled = documentMode || manualMode;
    resident.required = !documentMode && !manualMode;
    const row = documentMode ? requests.get(request.value) : (manualMode ? null : residents.get(resident.value));
    name.readOnly = mobile.readOnly = documentMode || Boolean(row);
    if (row) {
      name.value = row.resident_name || row.full_name;
      mobile.value = row.mobile || '';
    } else if (!preserveManual || documentMode) {
      name.value = ''; mobile.value = '';
    }
    get('smsComposeError').textContent = row && !row.mobile ? 'No mobile number is recorded for this recipient. Update their resident profile first.' : '';
  }
  template.addEventListener('change', () => { filterRequests(); updateRecipient(true); populateMessage(); refreshPreview(); });
  recipientMode.addEventListener('change', () => {
    clearTimeout(searchTimer); searchController?.abort(); ++searchVersion;
    resident.value = '';
    get('smsResidentSearch').value = '';
    get('smsResidentResults').hidden = true;
    get('smsResidentSearchStatus').textContent = '';
    updateRecipient();
    if (template.value === 'complaint') populateMessage();
    refreshPreview();
  });
  request.addEventListener('change', () => { updateRecipient(); populateMessage(); refreshPreview(); });
  resident.addEventListener('change', () => { updateRecipient(); refreshPreview(); });
  [name, mobile, message].forEach(input => input.addEventListener('input', refreshPreview));
  get('smsRequestSearch').addEventListener('input', filterRequests);
  const searchInput = get('smsResidentSearch');
  const searchResults = get('smsResidentResults');
  const searchStatus = get('smsResidentSearchStatus');
  let searchTimer, searchController, searchVersion = 0;
  searchInput.addEventListener('input', () => {
    clearTimeout(searchTimer);
    searchController?.abort();
    const version = ++searchVersion;
    const text = searchInput.value.trim();
    searchResults.replaceChildren();
    searchResults.hidden = true;
    searchStatus.textContent = text ? 'Searching residents…' : '';
    if (!text) return;
    searchTimer = setTimeout(async () => {
      searchController = new AbortController();
      try {
        const response = await fetch('resident-service-lookup.php', {
          method: 'POST', credentials: 'same-origin', signal: searchController.signal,
          headers: {'Content-Type': 'application/x-www-form-urlencoded'},
          body: new URLSearchParams({kind: 'sms-resident-search', search: text, csrf: form.querySelector('[name="csrf"]').value})
        });
        const result = await response.json();
        if (version !== searchVersion) return;
        if (!response.ok || !result.ok) throw new Error(result.message || 'Resident search is unavailable. Try again.');
        searchStatus.textContent = result.residents.length ? `${result.residents.length} matching residents. Select a recipient below.` : 'No active residents match your search.';
        result.residents.forEach(row => {
          const button = document.createElement('button');
          button.type = 'button';
          button.textContent = `${row.full_name} · ${row.user_code} · ${row.mobile || 'No mobile number recorded'}`;
          button.addEventListener('click', () => {
            residents.set(row.user_code, row);
            let option = Array.from(resident.options).find(item => item.value === row.user_code);
            if (!option) { option = document.createElement('option'); option.value = row.user_code; resident.appendChild(option); }
            option.textContent = `${row.full_name} - ${row.user_code}`;
            resident.value = row.user_code;
            resident.dispatchEvent(new Event('change', {bubbles: true}));
            searchResults.hidden = true;
            searchStatus.textContent = `${row.full_name} selected.`;
            searchInput.value = row.full_name;
            searchInput.focus();
          });
          searchResults.appendChild(button);
        });
        searchResults.hidden = !result.residents.length;
      } catch (error) {
        if (version === searchVersion && error.name !== 'AbortError') searchStatus.textContent = error instanceof SyntaxError ? 'Your session may have expired. Refresh the page and search again.' : error.message;
      }
    }, 220);
  });
  searchInput.addEventListener('keydown', event => {
    if (event.key === 'ArrowDown' && !searchResults.hidden) { event.preventDefault(); searchResults.firstElementChild?.focus(); }
    if (event.key === 'Enter') event.preventDefault();
    if (event.key === 'Escape') searchResults.hidden = true;
  });
  searchResults.addEventListener('keydown', event => {
    const buttons = Array.from(searchResults.children);
    const index = buttons.indexOf(document.activeElement);
    if (event.key === 'ArrowDown') { event.preventDefault(); buttons[(index + 1) % buttons.length]?.focus(); }
    if (event.key === 'ArrowUp') { event.preventDefault(); if (index <= 0) searchInput.focus(); else buttons[index - 1].focus(); }
    if (event.key === 'Escape') { searchResults.hidden = true; searchInput.focus(); }
  });
  function review() {
    refreshPreview();
    if (!form.reportValidity()) return;
    if (!/^(?:\+?639\d{9}|09\d{9})$/.test(mobile.value.replace(/[^\d+]/g, ''))) {
      get('smsComposeError').textContent = 'Enter a valid Philippine mobile number.';
      return;
    }
    get('smsComposeError').textContent = '';
    get('smsConfirmRecipient').textContent = name.value + ' - ' + mobile.value;
    get('smsConfirmMessage').textContent = fullMessage();
    dialog.showModal();
  }
  get('smsReview').addEventListener('click', review);
  get('smsCancel').addEventListener('click', () => dialog.close());
  confirmed.addEventListener('change', () => confirmed.setCustomValidity(''));
  dialog.addEventListener('click', event => { if (event.target === dialog) {
    const rect = dialog.getBoundingClientRect();
    if (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom) dialog.close();
  } });
  form.addEventListener('submit', event => {
    if (!dialog.open) { event.preventDefault(); review(); return; }
    if (!confirmed.checked) { event.preventDefault(); confirmed.setCustomValidity('Confirm the recipient and message.'); confirmed.reportValidity(); return; }
    form.querySelectorAll('button[type="submit"]').forEach(button => { button.disabled = true; button.textContent = 'Sending...'; });
  });
  filterRequests();
  updateRecipient(true);
  const currentRequest = requests.get(request.value);
  const changedRequest = isDocument() && currentRequest && (data.draftStatus !== currentRequest.status || Number(data.draftFee) !== Number(currentRequest.fee_amount));
  if (!data.draft || changedRequest) populateMessage();
  refreshPreview();
})();
