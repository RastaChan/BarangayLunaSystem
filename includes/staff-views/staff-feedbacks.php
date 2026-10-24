<?php $_SESSION['feedback_csrf'] = $_SESSION['feedback_csrf'] ?? bin2hex(random_bytes(32)); ?>
<div class="page-intro">
  <h2>Feedbacks</h2>
  <p>Read suggestions and feedback submitted by residents through the portal.</p>
</div>

<?php require __DIR__ . '/../views/feedback-rating-summary.php'; ?>
<section class="panel-card" data-overview-owner="staff-feedbacks-<?= (int) ($loggedInUser['id'] ?? 0) ?>">
  <div class="panel-head"><h3>Community feedback</h3></div>
  <div class="table-wrap community-feedback-wrap">
    <table class="data-table community-feedback-cards">
      <thead><tr><th>ID</th><th>From</th><th>Category</th><th>Rating</th><th>Status</th><th>Date</th><th>Actions</th></tr></thead>
      <tbody data-live-overview="community-feedback">
        <?php foreach ($staffFeedbacks as $feedback): ?>
        <tr data-feedback-code="<?= htmlspecialchars($feedback['id'], ENT_QUOTES) ?>">
          <td data-label="Feedback ID"><?= htmlspecialchars($feedback['id']) ?></td>
          <td data-label="Resident"><span class="feedback-resident-avatar" aria-hidden="true"><?= htmlspecialchars(mb_strtoupper(mb_substr(trim($feedback['resident']), 0, 1))) ?></span><strong><?= htmlspecialchars($feedback['resident']) ?></strong></td>
          <td data-label="Category"><?= htmlspecialchars($feedback['category'] ?: 'General feedback') ?></td>
<td data-label="Rating"><?= isset($feedback['rating']) ? (int) $feedback['rating'] . '/5' : 'Not rated' ?></td>
          <td data-label="Status"><span class="badge <?= $feedback['status'] === 'Read' ? 'badge-active' : 'badge-pending' ?>"><?= htmlspecialchars($feedback['status']) ?></span></td>
          <td data-label="Submitted"><?= htmlspecialchars($feedback['date']) ?></td>
          <td class="actions">
            <button type="button" class="btn-icon" data-view-feedback data-json='<?= htmlspecialchars(json_encode($feedback), ENT_QUOTES) ?>'>View</button>
            <form action="staff-actions.php" method="post" class="inline-action-form" data-feedback-unread <?= $feedback['status'] === 'Read' ? '' : 'hidden' ?>>
              <input type="hidden" name="action" value="mark_feedback_unread">
              <input type="hidden" name="redirect" value="staff-feedbacks">
              <input type="hidden" name="id" value="<?= htmlspecialchars($feedback['id']) ?>">
              <button type="submit" class="btn-icon">Unread</button>
            </form>
            <button type="button" class="btn-icon btn-danger" data-delete-feedback="<?= htmlspecialchars($feedback['id']) ?>" data-feedback-label="<?= htmlspecialchars($feedback['id'] . ' - ' . $feedback['resident']) ?>">Delete</button>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$staffFeedbacks): ?>
        <tr><td colspan="7">No feedback yet.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>

<div id="feedbackViewModal" class="modal" aria-hidden="true" role="dialog" aria-labelledby="feedbackViewTitle" data-read-token="<?= htmlspecialchars($_SESSION['feedback_csrf'], ENT_QUOTES) ?>">
  <div class="modal-backdrop" data-modal-close></div>
  <div class="modal-panel modal-panel--wide">
    <header class="modal-header"><h2 id="feedbackViewTitle">Feedback details</h2><button type="button" class="modal-close" data-modal-close aria-label="Close">&times;</button></header>
    <div class="modal-body">
      <section class="feedback-detail-card">
        <div class="feedback-detail-card__head"><div><span data-feedback-id>-</span><h3 data-feedback-resident>No feedback selected</h3></div><span class="badge badge-pending" data-feedback-status>Unread</span></div>
        <dl class="feedback-detail-card__meta"><div><dt>Rating</dt><dd data-feedback-rating>-</dd></div><div><dt>Category</dt><dd data-feedback-category>-</dd></div><div><dt>Date submitted</dt><dd data-feedback-date>-</dd></div></dl>
        <div class="feedback-message-box"><span>Resident feedback</span><p data-feedback-message>-</p></div>
        <div class="feedback-image-box" data-feedback-image-box><span>Attached image</span><a href="#" target="_blank" rel="noopener" data-feedback-image-link><img src="" alt="Resident feedback attachment" data-feedback-image></a></div>
        <div class="feedback-image-empty" data-feedback-image-empty>No image attached.</div>
      </section>
    </div>
    <footer class="modal-footer"><button type="button" class="btn btn-outline" data-modal-close>Close</button></footer>
  </div>
</div>

<div id="deleteFeedbackModal" class="modal" aria-hidden="true" role="dialog" aria-labelledby="deleteFeedbackTitle">
  <div class="modal-backdrop" data-modal-close></div>
  <div class="modal-panel">
    <header class="modal-header"><h2 id="deleteFeedbackTitle">Delete feedback</h2><button type="button" class="modal-close" data-modal-close aria-label="Close">&times;</button></header>
    <div class="modal-body"><p>Delete feedback <strong data-delete-feedback-label></strong>? This will also remove its attached image.</p></div>
    <form id="deleteFeedbackForm" action="staff-actions.php" method="post" data-server-form="true"><input type="hidden" name="action" value="delete_feedback"><input type="hidden" name="redirect" value="staff-feedbacks"><input type="hidden" name="id"></form>
    <footer class="modal-footer"><button type="button" class="btn btn-outline" data-modal-close>Cancel</button><button type="submit" form="deleteFeedbackForm" class="btn btn-danger">Delete</button></footer>
  </div>
</div>
