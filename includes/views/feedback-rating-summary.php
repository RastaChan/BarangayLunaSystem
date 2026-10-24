<?php
require_once __DIR__ . '/../report-metrics.php';
$feedbackMetrics = barangay_feedback_metrics();
?>
<section class="panel-card feedback-rating-summary">
  <div class="panel-head"><h3>Resident feedback ratings</h3><span class="panel-subtitle">All active feedback</span></div>
  <div class="panel-body" data-live-overview="feedback-ratings">
    <p><strong><?= $feedbackMetrics['average'] === null ? 'No ratings yet' : number_format($feedbackMetrics['average'], 2) . ' / 5 average' ?></strong> · <?= (int) $feedbackMetrics['count'] ?> rated responses · <?= (int) $feedbackMetrics['unrated'] ?> unrated responses</p>
    <?php foreach (array_reverse($feedbackMetrics['distribution'], true) as $score => $count): ?>
      <div class="feedback-rating-row">
        <span><?= (int) $score ?> / 5</span>
        <div class="feedback-rating-bar" aria-hidden="true"><i style="width:<?= $feedbackMetrics['count'] ? round($count / $feedbackMetrics['count'] * 100, 2) : 0 ?>%"></i></div>
        <strong><?= (int) $count ?> response<?= $count === 1 ? '' : 's' ?></strong>
      </div>
    <?php endforeach; ?>
    <p class="field-hint">Older feedback without a rating is excluded from the average. Archived feedback is excluded from this summary.</p>
  </div>
</section>
