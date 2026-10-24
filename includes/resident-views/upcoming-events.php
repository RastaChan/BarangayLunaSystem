<div class="page-intro"><h2>Upcoming Events</h2><p>View barangay events and community activities.</p></div>
<div class="announcement-cards">
  <?php foreach ($upcomingEvents as $event): ?>
  <article class="ann-card">
    <?php if (!empty($event['photo_path'])): ?>
    <img src="<?= htmlspecialchars($event['photo_path']) ?>" alt="<?= htmlspecialchars($event['title']) ?>" class="announcement-photo">
    <?php endif; ?>
    <h4><?= htmlspecialchars($event['title']) ?></h4>
    <time><?= htmlspecialchars($event['date']) ?> · <?= htmlspecialchars($event['location']) ?></time>
    <p><?= htmlspecialchars($event['description']) ?></p>
    <span class="badge badge-active" style="margin-top:0.75rem"><?= htmlspecialchars($event['status']) ?></span>
  </article>
  <?php endforeach; ?>
  <?php if (!$upcomingEvents): ?>
  <section class="panel-card"><div class="panel-body">No posted events yet.</div></section>
  <?php endif; ?>
</div>
