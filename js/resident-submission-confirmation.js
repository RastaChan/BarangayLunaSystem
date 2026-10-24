(() => {
  const modal = document.getElementById('residentSubmissionReview');
  if (!modal) return;
  const title = modal.querySelector('h2');
  const message = modal.querySelector('[data-submission-review-message]');
  const details = modal.querySelector('[data-submission-review-details]');
  const warning = modal.querySelector('[data-submission-copy-warning]');
  const reviewed = modal.querySelector('[data-submission-reviewed]');
  const reviewedLabel = modal.querySelector('[data-submission-reviewed-label]');
  const confirm = modal.querySelector('[data-submission-confirm]');
  const back = modal.querySelector('[data-submission-back]');
  let activeForm = null;
  let confirmed = false;
  const add = (label, value) => {
    if (!value) return;
    const row = document.createElement('div');
    const term = document.createElement('dt');
    const description = document.createElement('dd');
    term.textContent = label; description.textContent = value;
    row.append(term, description); details.append(row);
  };
  document.addEventListener('submit', event => {
    const form = event.target;
    if (!form.matches('[data-resident-review]')) return;
    if (form === activeForm && confirmed) {
      confirmed = false;
      form.querySelectorAll('[type="submit"]').forEach(button => {button.disabled = true;});
      return;
    }
    event.preventDefault();
    if (!form.reportValidity()) return;
    activeForm = form;
    details.replaceChildren();
    const value = name => form.querySelector(`[name="${name}"]`)?.value?.trim() || '';
    const kind = form.dataset.residentReview;
    const deleting = kind === 'delete';
    title.textContent = deleting ? 'Delete this submission?' : 'Review before submitting';
    message.textContent = deleting
      ? 'This submission will be removed from your list and the staff queue. You can submit a corrected one afterward.'
      : 'Check the details below. Choose Back to edit if anything is incorrect.';
    back.textContent = deleting ? 'Keep submission' : 'Back to edit';
    confirm.textContent = deleting ? 'Confirm delete' : 'Confirm submission';
    confirm.classList.toggle('btn-danger', deleting);
    confirm.classList.toggle('btn-primary', !deleting);
    reviewed.checked = false; confirm.disabled = true;
    reviewedLabel.textContent = deleting ? 'I want to delete this submission.' : 'I checked these details and want to submit.';
    warning.hidden = true;
    if (kind === 'document') {
      add('Resident', form.dataset.reviewResidentName);
      add('Address', form.dataset.reviewResidentAddress);
      add('Document type', value('document_type'));
      const indigency = form.querySelector('[data-resident-indigency-fields]');
      if (indigency && !indigency.hidden) add("Requesting resident's gender", indigency.querySelector('input[readonly]')?.value);
      add('Number of copies', value('copies'));
      add('Total fee', form.querySelector('#requestTotal')?.value || 'Confirm with the barangay office');
      add('Purpose', value('purpose'));
      for (const [field, label] of [['residency_years','Years as resident'],['beneficiary_name','Beneficiary'],['beneficiary_relationship','Relationship'],['partner_name','Partner'],['cohabitation_start_date','Living together since'],['contact_mobile','Contact number'],['remarks','Remarks']]) {
        const input = form.querySelector(`[name="${field}"]`);
        if (input && !input.closest('[hidden]')) add(label, value(field));
      }
      const copies = Number(value('copies'));
      reviewedLabel.textContent = `I checked these details and need exactly ${copies} ${copies === 1 ? 'copy' : 'copies'}.`;
      if (copies >= 5) {
        warning.hidden = false;
        warning.textContent = `You are requesting ${copies} copies of the same document. Make sure this quantity is intentional before confirming.`;
      }
    } else if (kind === 'complaint') {
      add('Subject', value('subject')); add('Category', value('category'));
      add('Description', value('details')); add('Location', value('location'));
    } else {
      add('Submission', form.dataset.reviewLabel);
    }
    const upload = form.querySelector('[type="file"]');
    if (upload?.files?.length) add('Attachment', upload.files[0].name);
    window.barangayOpenModal(modal.id);
  });
  reviewed.addEventListener('change', () => {confirm.disabled = !reviewed.checked;});
  confirm.addEventListener('click', () => {
    if (!activeForm || !reviewed.checked || !activeForm.reportValidity()) return;
    activeForm.querySelector('[name="review_confirmed"]').value = '1';
    const copies = activeForm.querySelector('[name="reviewed_copies"]');
    if (copies) copies.value = activeForm.querySelector('[name="copies"]').value;
    confirmed = true;
    confirm.disabled = true;
    activeForm.requestSubmit();
  });
})();
