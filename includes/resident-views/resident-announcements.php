<div class="page-intro"><h2>Announcements</h2><p>Read official public barangay announcements and advisories.</p></div>
<div class="announcement-cards">
  <?php foreach ($residentAnnouncements as $announcement): ?>
  <article class="ann-card">
    <?php if (!empty($announcement['photo_path'])): ?>
    <img src="<?= htmlspecialchars($announcement['photo_path']) ?>" alt="<?= htmlspecialchars($announcement['title']) ?>" class="announcement-photo">
    <?php endif; ?>
    <h4><?= htmlspecialchars($announcement['title']) ?></h4>
    <time><?= htmlspecialchars($announcement['date']) ?> · <?= htmlspecialchars($announcement['category']) ?></time>
    <details class="announcement-details">
      <summary class="btn btn-sm btn-outline">View full announcement</summary>
      <p><?= nl2br(htmlspecialchars($announcement['body'])) ?></p>
    </details>
  </article>
  <?php endforeach; ?>
  <?php if (!$residentAnnouncements): ?>
  <section class="panel-card"><div class="panel-body">No published announcements yet.</div></section>
  <?php endif; ?>
</div>
