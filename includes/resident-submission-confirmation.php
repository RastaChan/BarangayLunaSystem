<div id="residentSubmissionReview" class="modal" role="dialog" aria-hidden="true" aria-labelledby="residentSubmissionReviewTitle">
  <div class="modal-backdrop" data-modal-close></div>
  <div class="modal-panel">
    <header class="modal-header"><h2 id="residentSubmissionReviewTitle">Review before submitting</h2><button type="button" class="modal-close" data-modal-close aria-label="Close"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 256 256" fill="currentColor" stroke="none" aria-hidden="true" class="system-icon" data-system-icon="x"><path d="M216,56V200a16,16,0,0,1-16,16H56a16,16,0,0,1-16-16V56A16,16,0,0,1,56,40H200A16,16,0,0,1,216,56Z" opacity="0.2"/><path d="M205.66,194.34a8,8,0,0,1-11.32,11.32L128,139.31,61.66,205.66a8,8,0,0,1-11.32-11.32L116.69,128,50.34,61.66A8,8,0,0,1,61.66,50.34L128,116.69l66.34-66.35a8,8,0,0,1,11.32,11.32L139.31,128Z"/></svg></button></header>
    <div class="modal-body form-grid">
      <p data-submission-review-message></p>
      <dl class="resident-review-details" data-submission-review-details></dl>
      <p class="resident-copy-warning" role="status" data-submission-copy-warning hidden></p>
      <label class="resident-review-checkbox"><input type="checkbox" data-submission-reviewed> <span data-submission-reviewed-label>I checked these details and want to submit.</span></label>
      <footer class="modal-footer"><button type="button" class="btn btn-outline" data-modal-close data-submission-back>Back to edit</button><button type="button" class="btn btn-primary" data-submission-confirm disabled>Confirm submission</button></footer>
    </div>
  </div>
</div>
