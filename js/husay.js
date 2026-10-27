(() => {
  'use strict';
  const root = document.querySelector('[data-husay-root]');
  if (!root) return;
  const list = root.querySelector('[data-husay-list]');
  const search = root.querySelector('[data-husay-search]');
  const form = document.getElementById('husayScheduleForm');
  const details = document.querySelector('[data-husay-details]');
  const owner = root.dataset.husayOwner;
  const workspace = root.hasAttribute('data-husay-workspace') ? window.barangayHusayWorkspace(root, list, search) : null;
  let refreshing = false, detailVersion = 0, complaintVersion = 0;
  let currentComplaint = null;
  const field = name => form.querySelector(`[name="${name}"]`);
  const toast = (message, kind = 'success') => window.barangayToast?.(message, kind);
  function showModal(id) {
    document.querySelectorAll('.modal.is-open').forEach(modal => { if (modal.id !== id) window.barangayCloseModal(modal); });
    window.barangayOpenModal(id);
    const modal = document.getElementById(id);
    [modal, ...modal.querySelectorAll('.modal-panel, .modal-body')].forEach(element => { element.scrollTop = 0; });
  }
  async function getFeed(query = '') {
    const response = await fetch('husay-feed.php' + query, {credentials: 'same-origin', cache: 'no-store', headers: {Accept: 'application/json'}, signal: AbortSignal.timeout(15000)});
    const data = await response.json();
    if (!response.ok || !data.ok) throw new Error(data.message || 'Mediation details could not be loaded. Please sign in again if your session expired.');
    if (data.owner !== owner) throw new Error('The signed-in account changed. Refresh this page.');
    return data;
  }
  function filter() {
    if (workspace) { workspace.filter(); return; }
    const query = search.value.trim().toLowerCase();
    const rows = [...list.querySelectorAll('[data-husay-search-text]')];
    rows.forEach(row => { row.hidden = !row.dataset.husaySearchText.toLowerCase().includes(query); });
    root.querySelector('[data-husay-no-match]').hidden = rows.length === 0 || rows.some(row => !row.hidden);
  }
  async function refresh() {
    if (refreshing || document.hidden || navigator.onLine === false) return;
    refreshing = true;
    try {
      const data = await getFeed();
      if (list.dataset.signature !== data.html) { list.innerHTML = data.html; list.dataset.signature = data.html; filter(); }
      const picker = field('existing_complaint_code');
      if (data.complaintsHtml !== null && data.complaintsHtml !== undefined && !document.getElementById('husayScheduleModal').classList.contains('is-open') && picker.dataset.signature !== data.complaintsHtml) {
        picker.innerHTML = data.complaintsHtml; picker.dataset.signature = data.complaintsHtml; picker.dispatchEvent(new Event('change', {bubbles: true}));
      }
      root.querySelector('[data-husay-refresh-error]').hidden = true;
    } catch (error) {
      const warning = root.querySelector('[data-husay-refresh-error]');
      warning.textContent = 'Schedule updates are paused. Your last loaded list is kept. ' + error.message; warning.hidden = false;
    } finally { refreshing = false; }
  }
  function openSchedule(complaint = null, previous = null) {
    form.reset();
    field('request_key').value = [...window.crypto.getRandomValues(new Uint8Array(16))].map(value => value.toString(16).padStart(2, '0')).join('');
    const choosing = !!complaint?.choose;
    const walkin = !complaint && !previous;
    const pickerLabel = form.querySelector('[data-husay-complaint-picker]');
    pickerLabel.hidden = !choosing;
    field('existing_complaint_code').disabled = !choosing; field('existing_complaint_code').required = choosing;
    const group = form.querySelector('[data-husay-walkin-fields]');
    group.hidden = !walkin;
    group.querySelectorAll('input,textarea').forEach(input => { input.disabled = !walkin; input.required = walkin && input.hasAttribute('data-husay-walkin-required'); });
    field('intake_mode').value = walkin ? 'walkin' : 'linked';
    field('complaint_code').value = previous?.complaint_code || complaint?.id || '';
    field('complainant_name').value = previous?.complainant_name || complaint?.resident || '';
    field('complainant_name').readOnly = !walkin;
    field('complainant_mobile').value = previous?.complainant_mobile || complaint?.contact_mobile || '';
    field('reschedule_id').value = previous?.id || '';
    field('version').value = previous?.version || '';
    const reason = form.querySelector('[data-husay-reschedule-reason]');
    reason.hidden = !previous; field('change_reason').disabled = !previous; field('change_reason').required = !!previous;
    form.querySelector('[data-husay-error]').hidden = true;
    const today = new Intl.DateTimeFormat('en-CA', {timeZone: 'Asia/Manila', year: 'numeric', month: '2-digit', day: '2-digit'}).format(new Date());
    field('hearing_date').min = today; field('hearing_date').value = today;
    if (previous) {
      ['venue','assigned_user_id','respondent_name','respondent_mobile','respondent_address','instructions'].forEach(name => { field(name).value = previous[name] || ''; });
      field('hearing_date').value = previous.starts_at.slice(0, 10);
      field('start_time').value = previous.starts_at.slice(11, 16); field('end_time').value = previous.ends_at.slice(11, 16);
    }
    document.getElementById('husayScheduleTitle').textContent = previous ? 'Reschedule Mediation' : walkin ? 'Add walk-in Mediation' : 'Schedule existing complaint';
    form.querySelector('[data-husay-mode-description]').textContent = previous
      ? 'Choose a new appointment time for this complaint. The previous session stays in its history.'
      : walkin ? 'For a person reporting a new complaint at the barangay office. This saves a new complaint and its Mediation appointment together. No resident account is required.'
      : choosing ? 'For a complaint already filed in the system. Select the complaint and its complainant details will fill automatically, then set the Mediation appointment.'
      : 'This appointment is linked to the complaint shown below. The complainant details come from that record. Set the Mediation appointment without creating another complaint.';
    form.querySelector('[type="submit"]').textContent = previous ? 'Save rescheduled appointment' : walkin ? 'Save walk-in complaint and schedule' : 'Save appointment for existing complaint';
    form.querySelector('[data-husay-complaint-label]').textContent = walkin ? 'New complaint · Walk-in intake' : 'Complaint: ' + field('complaint_code').value;
    if (form.dataset?.husayApproval === 'required') {
      document.getElementById('husayScheduleTitle').textContent = previous ? 'Request rescheduling' : 'Propose Mediation schedule';
      form.querySelector('[data-husay-mode-description]').textContent += ' This is a proposal only. The Barangay Captain must approve it before any appointment is confirmed.';
      form.querySelector('[type="submit"]').textContent = 'Submit for Captain approval';
    }
    showModal('husayScheduleModal');
    field('existing_complaint_code').dispatchEvent(new Event('change', {bubbles: true}));
    field('assigned_user_id').dispatchEvent(new Event('change', {bubbles: true}));
  }
  function openFollowup(previous) {
    openSchedule({id: previous.complaint_code, resident: previous.complainant_name, contact_mobile: previous.complainant_mobile});
    ['venue','assigned_user_id','respondent_name','respondent_mobile','respondent_address'].forEach(name => { field(name).value = previous[name] || ''; });
    ['hearing_date','start_time','end_time'].forEach(name => { field(name).value = ''; });
    document.getElementById('husayScheduleTitle').textContent = 'Schedule follow-up hearing';
    form.querySelector('[data-husay-mode-description]').textContent = 'The previous session is completed. Choose the next date and time, review the details, and save to book the follow-up. Closing this form leaves the follow-up unscheduled.';
    form.querySelector('[type="submit"]').textContent = 'Confirm follow-up appointment';
    if (form.dataset?.husayApproval === 'required') {
      document.getElementById('husayScheduleTitle').textContent = 'Propose follow-up hearing';
      form.querySelector('[data-husay-mode-description]').textContent = 'Choose the proposed follow-up date and time. The Barangay Captain must approve it before the parties are notified.';
      form.querySelector('[type="submit"]').textContent = 'Submit for Captain approval';
    }
    field('assigned_user_id').dispatchEvent(new Event('change', {bubbles: true}));
  }
  async function openDetails(id, trigger = null) {
    if (trigger?.disabled) return;
    const version = ++detailVersion;
    const label = trigger?.textContent;
    const sourceModal = trigger?.closest('.modal');
    if (trigger) { trigger.disabled = true; trigger.textContent = 'Opening session…'; trigger.setAttribute('aria-busy', 'true'); }
    try {
      const data = await getFeed('?hearing=' + encodeURIComponent(id));
      if (version !== detailVersion || (sourceModal && !sourceModal.classList.contains('is-open'))) return;
      // Populate the final layout before opening: no tiny loading dialog first.
      details.innerHTML = data.html;
      showModal('husayDetailsModal');
    } catch (error) {
      if (version === detailVersion) toast(error.message, 'error');
    } finally {
      if (trigger) { trigger.disabled = false; trigger.textContent = label; trigger.removeAttribute('aria-busy'); }
    }
  }
  async function scheduleFromMenu(button) {
    if (button.disabled) return;
    button.disabled = true;
    try {
      const complaint = JSON.parse(button.dataset.husayScheduleComplaint);
      const data = await getFeed('?complaint=' + encodeURIComponent(complaint.id));
      if (data.activeId) {
        await openDetails(data.activeId);
        toast('This complaint already has an appointment or a proposal awaiting review. Open its details to see the next action.', 'info');
      } else openSchedule(complaint);
    } catch (error) { toast(error.message, 'error'); }
    finally { button.disabled = false; }
  }
  async function loadComplaint(complaint) {
    const version = ++complaintVersion;
    const body = document.querySelector('[data-record-view]')?.closest('.modal-body');
    body?.querySelector('[data-complaint-husay]')?.remove();
    if (!body || !complaint) return;
    const section = document.createElement('section'); section.className = 'husay-complaint-section'; section.setAttribute('data-complaint-husay', '');
    const heading = document.createElement('h3'); heading.textContent = 'Mediation sessions'; section.appendChild(heading);
    let scheduleButton = null;
    if (['Open','In Progress'].includes(complaint.status)) {
      scheduleButton = document.createElement('button'); scheduleButton.type = 'button'; scheduleButton.className = 'btn btn-primary'; scheduleButton.textContent = 'Checking schedule…'; scheduleButton.disabled = true; scheduleButton.setAttribute('data-husay-for-complaint', ''); section.appendChild(scheduleButton);
    }
    const content = document.createElement('div'); content.textContent = 'Loading sessions…'; section.appendChild(content); body.appendChild(section);
    try {
      const data = await getFeed('?complaint=' + encodeURIComponent(complaint.id));
      if (version === complaintVersion && section.isConnected) {
        content.innerHTML = data.html;
        if (scheduleButton) {
          if (data.hasActive) scheduleButton.remove();
          else { scheduleButton.textContent = form.dataset?.husayApproval === 'required' ? 'Propose Mediation schedule' : 'Schedule Mediation'; scheduleButton.disabled = false; }
        }
      }
    } catch (error) { if (section.isConnected) content.textContent = error.message; }
  }
  document.addEventListener('record-details-opened', event => { currentComplaint = event.detail.title === 'Complaint' ? event.detail.data : null; loadComplaint(currentComplaint); });
  document.addEventListener('click', event => {
    const followup = event.target.closest('[data-husay-followup]');
    if (followup) { openFollowup(JSON.parse(followup.dataset.husayFollowup)); return; }
    const view = event.target.closest('[data-husay-view]');
    const reschedule = event.target.closest('[data-husay-reschedule]');
    const complaintSchedule = event.target.closest('[data-husay-schedule-complaint]');
    if (complaintSchedule) scheduleFromMenu(complaintSchedule);
    else if (view) openDetails(view.dataset.husayView, view);
    else if (reschedule) openSchedule(null, JSON.parse(reschedule.dataset.husayReschedule));
    else if (event.target.closest('[data-husay-existing]')) openSchedule({choose: true});
    else if (event.target.closest('[data-husay-walkin]')) openSchedule();
    else if (event.target.closest('[data-husay-for-complaint]') && currentComplaint) openSchedule(currentComplaint);
  });
  field('existing_complaint_code').addEventListener('change', event => {
    if (event.target.disabled) return;
    const option = event.target.selectedOptions[0];
    field('complaint_code').value = event.target.value;
    field('complainant_name').value = option?.dataset.resident || '';
    field('complainant_mobile').value = option?.dataset.mobile || '';
    form.querySelector('[data-husay-complaint-label]').textContent = event.target.value ? 'Complaint: ' + event.target.value : 'Choose the complaint for this appointment.';
  });
  document.addEventListener('submit', async event => {
    const submitted = event.target.closest('[data-husay-form]');
    if (!submitted) return;
    event.preventDefault();
    if (submitted.dataset.saving) return;
    const errorBox = submitted.querySelector('[data-husay-error]'); errorBox.hidden = true;
    const payload = new FormData(submitted);
    const submit = submitted.querySelector('[type="submit"]');
    submitted.dataset.saving = 'true'; submit.disabled = true;
    try {
      const response = await fetch('husay-actions.php', {method: 'POST', credentials: 'same-origin', headers: {Accept: 'application/json'}, body: payload, signal: AbortSignal.timeout(60000)});
      if (!(response.headers.get('Content-Type') || '').includes('application/json')) throw new Error('The server could not confirm the save. Check the Mediation list before trying again.');
      const result = await response.json();
      if (!response.ok || !result.ok) throw new Error(result.message || 'Mediation changes could not be saved.');
      toast(result.message); await refresh();
      if (payload.get('action') === 'record_husay' && payload.get('outcome') === 'For follow-up' && submitted.dataset.husayFollowupDetails) {
        openFollowup(JSON.parse(submitted.dataset.husayFollowupDetails));
      } else await openDetails(result.id);
      document.dispatchEvent(new CustomEvent('husay-updated'));
    } catch (error) { errorBox.textContent = ['TimeoutError','AbortError'].includes(error.name) ? 'Confirmation took too long. Check the Mediation list before trying again.' : error.message; errorBox.hidden = false; }
    finally { delete submitted.dataset.saving; submit.disabled = false; }
  });
  search.addEventListener('input', filter); filter(); refresh();
  window.setInterval(refresh, 5000);
  window.addEventListener('focus', refresh); window.addEventListener('online', refresh);
  document.addEventListener('document-notifications-updated', refresh);
  document.addEventListener('visibilitychange', () => { if (!document.hidden) refresh(); });
  const requested = new URL(window.location.href).searchParams.get('husay');
  if (requested && /^\d+$/.test(requested)) openDetails(requested);
})();
