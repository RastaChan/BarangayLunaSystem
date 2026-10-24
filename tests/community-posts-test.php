<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../includes/announcement-helpers.php';
function community_check(bool $ok, string $label): void {
  if (!$ok) throw new RuntimeException($label);
  echo "PASS: {$label}\n";
}
$base = ['title' => 'Community notice', 'body' => 'Details', 'publish_date' => '2099-12-01', 'publish_time' => '08:00'];
$post = announcement_post_input($base);
community_check($post[':status'] === 'Published' && $post[':publish_date'] === date('Y-m-d'), 'Immediate posting ignores stale or future dates when scheduling is off');
$post = announcement_post_input($base + ['schedule_enabled' => '1']);
community_check($post[':status'] === 'Scheduled' && $post[':publish_at'] === '2099-12-01 08:00:00', 'Future publication is scheduled');
$event = $base + ['post_type' => 'event', 'event_at' => '2099-12-25T09:00', 'event_location' => 'Barangay Hall'];
$post = announcement_post_input($event);
community_check($post[':status'] === 'Published' && $post[':event_at'] === '2099-12-25 09:00:00' && $post[':event_location'] === 'Barangay Hall', 'Future event dates do not delay immediate publication');
$post = announcement_post_input($event + ['schedule_enabled' => '1']);
community_check($post[':event_at'] !== $post[':publish_at'], 'Event and publication dates remain separate');
$post = announcement_post_input(array_replace($event, ['post_type' => 'announcement']));
community_check($post[':event_at'] === null && $post[':event_location'] === null, 'Switching to announcement clears event details');
foreach ([['post_type' => 'invalid'], ['title' => ''], ['title' => str_repeat('x', 181)], ['body' => ''], ['post_type' => 'event', 'event_at' => '2026-02-30T09:00', 'event_location' => 'Hall'], ['post_type' => 'event', 'event_at' => '2099-12-25T09:00', 'event_location' => ''], ['schedule_enabled' => '1', 'publish_date' => '2026-02-30']] as $invalid) {
  $rejected = false;
  try { announcement_post_input(array_replace($base, $invalid)); } catch (InvalidArgumentException $e) { $rejected = true; }
  community_check($rejected, 'Invalid post input rejected');
}
foreach (['admin', 'staff'] as $currentRole) {
  ob_start(); include __DIR__ . '/../includes/community-post-form.php'; $html = ob_get_clean();
  community_check(str_contains($html, 'action="' . $currentRole . '-actions.php"') && str_contains($html, 'value="create_announcement"'), ucfirst($currentRole) . ' form uses direct publishing');
  community_check(str_contains($html, 'value="' . ($currentRole === 'admin' ? 'dashboard' : 'staff-dashboard') . '"'), ucfirst($currentRole) . ' returns to dashboard');
}
$postFixture = ['id' => 1, 'title' => 'A longer community notice', 'body' => "First line\n<Details>", 'post_type' => 'announcement',
  'date' => '2026-10-24', 'publish_schedule' => '2026-10-24 09:00', 'publish_time' => '09:00', 'status' => 'Published',
  'photo_path' => 'storage/announcement-photos/test-only.jpg', 'photo_file_name' => 'test-only.jpg', 'archived_at' => null];
$eventFixture = array_replace($postFixture, ['id' => 2, 'title' => 'Barangay meeting', 'post_type' => 'event', 'status' => 'Scheduled',
  'event_at' => '2099-12-25 09:00:00', 'event_location' => 'Barangay Hall', 'photo_path' => '']);
foreach (['admin', 'staff'] as $currentRole) {
  $adminAnnouncements = $staffAnnouncements = [$postFixture, $eventFixture];
  ob_start(); require __DIR__ . '/../includes/' . ($currentRole === 'admin' ? 'views/announcements.php' : 'staff-views/staff-announcements.php'); $html = ob_get_clean();
  $dom = new DOMDocument(); @$dom->loadHTML($html); $xpath = new DOMXPath($dom);
  community_check($xpath->query('//*[@id="community-posts"]//article[contains(@class,"recent-post-card")]')->length === 2 && $xpath->query('//*[@id="community-posts"]//table')->length === 0, "$currentRole recent posts render as separate cards without a wide table");
  community_check(str_contains($html, '&lt;Details&gt;') && str_contains($html, 'Barangay Hall') && $xpath->query('//article//img[@src="storage/announcement-photos/test-only.jpg"]')->length === 1, "$currentRole cards retain full post text, event details and photos");
  community_check($xpath->query('//article[contains(@class,"recent-post-card")]//button[@data-modal-open]')->length === 4, "$currentRole can edit and delete both posts and events");
  community_check($xpath->query('//article//div[@data-post-options]/button[@data-post-toggle and @aria-expanded="false"]')->length === 2 && $xpath->query('//div[@data-post-actions and @hidden]//button[@data-modal-open]')->length === 4, "$currentRole three-dot buttons control initially hidden Edit and Delete menus");
  community_check($xpath->query('//form[@action="' . $currentRole . '-actions.php"]//input[@name="action" and @value="update_announcement"]')->length === 2 && $xpath->query('//form[@action="' . $currentRole . '-actions.php"]//input[@name="action" and @value="delete_announcement"]')->length === 2, "$currentRole edit and delete forms use its authorized handler");
  community_check($xpath->query('//input[@name="csrf"]')->length === 5, "$currentRole create, edit and delete forms include request protection");
}
$currentRole = 'resident';
$residentAnnouncements = [$postFixture + ['category' => 'Public Advisory']];
$upcomingEvents = [['title' => 'Barangay meeting', 'description' => 'Details', 'date' => '2099-12-25', 'location' => 'Barangay Hall', 'status' => 'Upcoming']];
foreach (['resident-announcements.php', 'upcoming-events.php'] as $view) {
  ob_start(); require __DIR__ . '/../includes/resident-views/' . $view; $html = ob_get_clean();
  community_check(!str_contains($html, 'editAnnouncementModal') && !str_contains($html, 'deleteAnnouncementModal') && !str_contains($html, 'update_announcement'), 'Resident ' . $view . ' remains read-only');
}
