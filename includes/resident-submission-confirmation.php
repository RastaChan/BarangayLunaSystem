<div id="residentSubmissionReview" class="modal" role="dialog" aria-hidden="true" aria-labelledby="residentSubmissionReviewTitle">
  <div class="modal-backdrop" data-modal-close></div>
  <div class="modal-panel">
    <header class="modal-header"><h2 id="residentSubmissionReviewTitle">Review before submitting</h2><button type="button" class="modal-close" data-modal-close aria-label="Close">&times;</button></header>
    <div class="modal-body form-grid">
      <p data-submission-review-message></p>
      <dl class="resident-review-details" data-submission-review-details></dl>
      <p class="resident-copy-warning" role="status" data-submission-copy-warning hidden></p>
      <label class="resident-review-checkbox"><input type="checkbox" data-submission-reviewed> <span data-submission-reviewed-label>I checked these details and want to submit.</span></label>
      <footer class="modal-footer"><button type="button" class="btn btn-outline" data-modal-close data-submission-back>Back to edit</button><button type="button" class="btn btn-primary" data-submission-confirm disabled>Confirm submission</button></footer>
    </div>
  </div>
</div>
