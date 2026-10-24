(function () {
  'use strict';
  const form = document.getElementById('smsBroadcastForm');
  if (!form) return;
  const get = id => document.getElementById(id);
  const dialog = get('broadcastConfirm');
  let batch = form.dataset.batch, running = false, pause = false, reviewing = false;
  async function call(action, extra = {}) {
    const body = new URLSearchParams({action, csrf: form.dataset.csrf, id: batch, ...extra});
    const response = await fetch('sms-broadcast-actions.php', {method: 'POST', credentials: 'same-origin', body});
    const data = await response.json();
    if (!data.success) throw new Error(data.message || 'Could not complete the broadcast action.');
    return data;
  }
  function progress(p) {
    const c = p.counts;
    get('broadcastProgress').textContent = `${p.done ? 'Finished' : 'Progress'}: ${p.processed}/${p.total}. Accepted: ${c.Accepted}; failed: ${c.Failed}; uncertain: ${c.Unknown}; skipped: ${c.Skipped}. Accepted does not confirm delivery.`;
  }
  async function send(confirm = false) {
    if (running) return;
    running = true; pause = false;
    get('broadcastError').textContent = '';
    get('broadcastResume').hidden = true;
    get('broadcastPause').hidden = false;
    form.querySelector('fieldset').disabled = true;
    let done = false;
    try {
      if (confirm) {
        const data = await call('confirm', {confirmed: '1'});
        progress(data.progress); done = data.progress.done;
      }
      while (!pause && !done) {
        const data = await call('step');
        progress(data.progress); done = data.progress.done;
        if (!done && !pause) await new Promise(resolve => setTimeout(resolve, 500));
      }
    } catch (error) {
      get('broadcastError').textContent = error.message + ' Check SMS History. Resume continues this batch without repeating recorded attempts.';
    } finally {
      running = false;
      get('broadcastPause').hidden = true;
      get('broadcastResume').hidden = done;
      form.querySelector('fieldset').disabled = !done;
      get('broadcastSend').disabled = false;
    }
  }
  form.addEventListener('submit', async event => {
    event.preventDefault();
    if (running || reviewing) return;
    reviewing = true;
    get('broadcastError').textContent = '';
    try {
      const fields = new FormData(form);
      const data = await call('prepare', {audience: fields.get('audience'), type: fields.get('type'), message: fields.get('message')});
      batch = data.review.id;
      get('broadcastCount').textContent = `Send to ${data.review.total} unique mobile numbers.`;
      get('broadcastSkipped').textContent = `${data.review.skipped} missing or invalid numbers excluded; ${data.review.duplicates} duplicate numbers removed.`;
      get('broadcastText').textContent = data.review.message;
      get('broadcastConsent').checked = false;
      get('broadcastSend').disabled = true;
      dialog.showModal();
    } catch (error) { get('broadcastError').textContent = error.message; }
    finally { reviewing = false; }
  });
  get('broadcastConsent').addEventListener('change', event => { get('broadcastSend').disabled = !event.target.checked; });
  get('broadcastCancel').addEventListener('click', () => dialog.close());
  get('broadcastSend').addEventListener('click', () => {
    if (!get('broadcastConsent').checked || running) return;
    get('broadcastSend').disabled = true; dialog.close(); send(true);
  });
  get('broadcastPause').addEventListener('click', () => { pause = true; });
  get('broadcastResume').addEventListener('click', () => send(true));
})();
