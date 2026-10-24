<?php
$recentPostStatus = !empty($recentPost['archived_at']) ? 'Archived' : ($recentPost['status'] ?? 'Published');
$recentPostDate = $recentPost['publish_schedule'] ?? trim(($recentPost['date'] ?? '') . ' ' . ($recentPost['publish_time'] ?? ''));
?>
<header class="recent-post-meta">
  <span class="badge <?= $recentPostStatus === 'Published' ? 'badge-active' : 'badge-pending' ?>"><?= htmlspecialchars(ucfirst($recentPost['post_type'] ?? 'announcement') . ' · ' . $recentPostStatus) ?></span>
  <time><?= htmlspecialchars($recentPostDate) ?></time>
</header>
<h4><?= htmlspecialchars($recentPost['title']) ?></h4>
<p class="recent-post-message"><?= htmlspecialchars($recentPost['body']) ?></p>
<?php if (($recentPost['post_type'] ?? '') === 'event'): ?>
<div class="recent-post-event">
  <strong>Event details</strong>
  <span><?= htmlspecialchars(date('M j, Y · g:i A', strtotime($recentPost['event_at']))) ?></span>
  <span><?= htmlspecialchars($recentPost['event_location'] ?? '') ?></span>
</div>
<?php endif; ?>
<?php if (!empty($recentPost['photo_path'])): ?>
<img src="<?= htmlspecialchars($recentPost['photo_path'], ENT_QUOTES) ?>" alt="<?= htmlspecialchars($recentPost['title'], ENT_QUOTES) ?>" class="announcement-photo" loading="lazy">
<?php endif; ?>
