<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/announcement-helpers.php';
require_once __DIR__ . '/includes/public-office-details.php';
$publicOfficeDetails = barangay_public_office_details();

$currentUser = barangay_current_user();
if ($currentUser) {
  header('Location: ' . barangay_dashboard_for_role($currentUser['role']));
  exit;
}

$loginError = $_GET['error'] ?? '';
$loginStatus = $_GET['status'] ?? '';
$allowedLoginRoles = ['admin', 'staff', 'resident'];
$selectedRole = strtolower(trim((string) ($_GET['role'] ?? 'admin')));
if (!in_array($selectedRole, $allowedLoginRoles, true)) {
  $selectedRole = 'admin';
}
$enteredUsername = trim((string) ($_GET['username'] ?? ''));
$publicAnnouncements = [];

try {
  $publicAnnouncements = announcement_fetch_public(3);
} catch (Throwable $e) {
  $publicAnnouncements = [];
}

/**
 * Barangay Luna - Smart Barangay Portal
 * Primary login UI.
 *
 * Login form behavior:
 *   action="login-handler.php"
 *   method="post"
 *   fields: role, username, password, remember
 */
$registrationRequirements = [
  ['title' => 'Valid Government-Issued ID', 'required' => true, 'description' => 'Original and photocopy of any primary ID (e.g., National ID, Passport, Driver\'s License, UMID, Voter\'s ID, SSS/GSIS, PRC).', 'icon' => 'id'],
  ['title' => 'Proof of Residence', 'required' => true, 'description' => 'At least one recent document showing your address within Barangay Luna.', 'icon' => 'home', 'subitems' => ['Electricity bill (MERALCO or local cooperative)', 'Water bill', 'Internet / cable subscription bill', 'Lease contract or barangay certification of residency']],
  ['title' => 'Barangay Certificate', 'required' => true, 'description' => 'Barangay Certificate or Barangay Clearance issued by Barangay Luna, if required by the front desk.', 'icon' => 'document'],
  ['title' => 'Birth Certificate', 'required' => true, 'description' => 'PSA-issued birth certificate (original or certified true copy) for identity verification.', 'icon' => 'document'],
  ['title' => 'Other Supporting Documents', 'required' => false, 'description' => 'Additional papers that may be requested depending on your application type or civil status.', 'icon' => 'folder', 'subitems' => ['Marriage certificate (if applicable)', 'Authorization letter and valid ID of representative (if applying on behalf of another person)', 'Recent 1x1 or 2x2 ID photo', 'Any other document specified by the Barangay Secretary']],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Official login portal - Barangay Luna Smart Barangay Portal">
  <title>Sign In | Barangay Luna MIS</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700&family=Source+Serif+4:opsz,wght@8..60,600;8..60,700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css?v=<?= filemtime(__DIR__ . '/css/style.css') ?>">
  <link rel="stylesheet" href="css/mobile-select.css?v=<?= filemtime(__DIR__ . '/css/mobile-select.css') ?>">
  <link rel="stylesheet" href="css/motion.css?v=<?= filemtime(__DIR__ . '/css/motion.css') ?>">
</head>
<body class="login-alt-page">
  <main class="portal-shell">
    <section class="portal-hero" aria-label="Barangay Luna Smart Portal">
      <nav class="portal-nav" aria-label="Portal navigation preview">
        <a href="login.php" class="portal-brand">
          <span class="portal-seal">
            <img src="branding-logo.php" alt="Barangay Luna Seal"
                 onerror="this.hidden=true; this.parentElement.classList.add('seal-fallback');">
          </span>
          <span>
            <strong>Barangay Luna</strong>
            <small>Smart Portal</small>
          </span>
        </a>
        <div class="portal-links" aria-hidden="true">
          <span>Home</span>
          <span>Services</span>
          <span>Announcements</span>
          <span>Contact</span>
        </div>
      </nav>

      <div class="portal-hero-grid">
        <div class="portal-copy">
          <p class="portal-eyebrow">Welcome to</p>
          <h1>Barangay Luna Smart Portal</h1>
          <p class="portal-lead">
            Access online barangay services, request documents, verify resident records,
            and receive official service updates from one secure portal.
          </p>
          <div class="portal-actions">
            <a href="#portalFeatures" class="btn btn-primary">Learn More</a>
          </div>
        </div>

        <div class="portal-photo-card">
          <img src="assets/images/barangay-hall.jpg" alt="Barangay Luna Hall"
               onerror="this.parentElement.classList.add('photo-fallback'); this.hidden=true;">
          <div class="portal-photo-label">
            <span>Official Online Services</span>
            <strong>Barangay Luna Hall</strong>
          </div>
        </div>
      </div>

      <div class="portal-feature-grid" id="portalFeatures" aria-label="Portal features">
        <article class="portal-feature-card">
          <span class="feature-icon">
            <svg  viewBox="0 0 256 256" fill="currentColor" stroke="none" aria-hidden="true" data-system-icon="layout-dashboard" class="system-icon"><path d="M112,56v48a8,8,0,0,1-8,8H56a8,8,0,0,1-8-8V56a8,8,0,0,1,8-8h48A8,8,0,0,1,112,56Zm88-8H152a8,8,0,0,0-8,8v48a8,8,0,0,0,8,8h48a8,8,0,0,0,8-8V56A8,8,0,0,0,200,48Zm-96,96H56a8,8,0,0,0-8,8v48a8,8,0,0,0,8,8h48a8,8,0,0,0,8-8V152A8,8,0,0,0,104,144Zm96,0H152a8,8,0,0,0-8,8v48a8,8,0,0,0,8,8h48a8,8,0,0,0,8-8V152A8,8,0,0,0,200,144Z" opacity="0.2"/><path d="M200,136H152a16,16,0,0,0-16,16v48a16,16,0,0,0,16,16h48a16,16,0,0,0,16-16V152A16,16,0,0,0,200,136Zm0,64H152V152h48v48ZM104,40H56A16,16,0,0,0,40,56v48a16,16,0,0,0,16,16h48a16,16,0,0,0,16-16V56A16,16,0,0,0,104,40Zm0,64H56V56h48v48Zm96-64H152a16,16,0,0,0-16,16v48a16,16,0,0,0,16,16h48a16,16,0,0,0,16-16V56A16,16,0,0,0,200,40Zm0,64H152V56h48v48Zm-96,32H56a16,16,0,0,0-16,16v48a16,16,0,0,0,16,16h48a16,16,0,0,0,16-16V152A16,16,0,0,0,104,136Zm0,64H56V152h48v48Z"/></svg>
          </span>
          <div>
            <h2>Online Services</h2>
            <p>Access core barangay transactions online.</p>
          </div>
        </article>
        <article class="portal-feature-card">
          <span class="feature-icon">
            <svg  viewBox="0 0 256 256" fill="currentColor" stroke="none" aria-hidden="true" data-system-icon="file-text" class="system-icon"><path d="M208,88H152V32Z" opacity="0.2"/><path d="M213.66,82.34l-56-56A8,8,0,0,0,152,24H56A16,16,0,0,0,40,40V216a16,16,0,0,0,16,16H200a16,16,0,0,0,16-16V88A8,8,0,0,0,213.66,82.34ZM160,51.31,188.69,80H160ZM200,216H56V40h88V88a8,8,0,0,0,8,8h48V216Zm-32-80a8,8,0,0,1-8,8H96a8,8,0,0,1,0-16h64A8,8,0,0,1,168,136Zm0,32a8,8,0,0,1-8,8H96a8,8,0,0,1,0-16h64A8,8,0,0,1,168,168Z"/></svg>
          </span>
          <div>
            <h2>Request Documents</h2>
            <p>Submit and track barangay certificates.</p>
          </div>
        </article>
        <article class="portal-feature-card">
          <span class="feature-icon">
            <svg  viewBox="0 0 256 256" fill="currentColor" stroke="none" aria-hidden="true" data-system-icon="qr-code" class="system-icon"><path d="M112,56v48a8,8,0,0,1-8,8H56a8,8,0,0,1-8-8V56a8,8,0,0,1,8-8h48A8,8,0,0,1,112,56Zm-8,88H56a8,8,0,0,0-8,8v48a8,8,0,0,0,8,8h48a8,8,0,0,0,8-8V152A8,8,0,0,0,104,144Zm96-96H152a8,8,0,0,0-8,8v48a8,8,0,0,0,8,8h48a8,8,0,0,0,8-8V56A8,8,0,0,0,200,48Z" opacity="0.2"/><path d="M104,40H56A16,16,0,0,0,40,56v48a16,16,0,0,0,16,16h48a16,16,0,0,0,16-16V56A16,16,0,0,0,104,40Zm0,64H56V56h48v48Zm0,32H56a16,16,0,0,0-16,16v48a16,16,0,0,0,16,16h48a16,16,0,0,0,16-16V152A16,16,0,0,0,104,136Zm0,64H56V152h48v48ZM200,40H152a16,16,0,0,0-16,16v48a16,16,0,0,0,16,16h48a16,16,0,0,0,16-16V56A16,16,0,0,0,200,40Zm0,64H152V56h48v48Zm-64,72V144a8,8,0,0,1,16,0v32a8,8,0,0,1-16,0Zm80-16a8,8,0,0,1-8,8H184v40a8,8,0,0,1-8,8H144a8,8,0,0,1,0-16h24V144a8,8,0,0,1,16,0v8h24A8,8,0,0,1,216,160Zm0,32v16a8,8,0,0,1-16,0V192a8,8,0,0,1,16,0Z"/></svg>
          </span>
          <div>
            <h2>QR Verification</h2>
            <p>Verify resident profiles faster.</p>
          </div>
        </article>
        <article class="portal-feature-card">
          <span class="feature-icon">
            <svg  viewBox="0 0 256 256" fill="currentColor" stroke="none" aria-hidden="true" data-system-icon="message-square-text" class="system-icon"><path d="M224,64V192a8,8,0,0,1-8,8H80L45.15,230.11A8,8,0,0,1,32,224V64a8,8,0,0,1,8-8H216A8,8,0,0,1,224,64Z" opacity="0.2"/><path d="M216,48H40A16,16,0,0,0,24,64V224a15.85,15.85,0,0,0,9.24,14.5A16.13,16.13,0,0,0,40,240a15.89,15.89,0,0,0,10.25-3.78l.09-.07L83,208H216a16,16,0,0,0,16-16V64A16,16,0,0,0,216,48ZM40,224h0ZM216,192H80a8,8,0,0,0-5.23,1.95L40,224V64H216ZM88,112a8,8,0,0,1,8-8h64a8,8,0,0,1,0,16H96A8,8,0,0,1,88,112Zm0,32a8,8,0,0,1,8-8h64a8,8,0,1,1,0,16H96A8,8,0,0,1,88,144Z"/></svg>
          </span>
          <div>
            <h2>SMS Alerts</h2>
            <p>Receive service and pickup reminders.</p>
          </div>
        </article>
      </div>
    </section>

    <aside class="portal-side">
      <section class="portal-login-card" id="loginAltCard" aria-labelledby="loginAltTitle">
        <div class="login-card-head">
          <span class="auth-seal auth-seal-sm">
            <img src="branding-logo.php" alt=""
                 onerror="this.hidden=true; this.parentElement.classList.add('seal-fallback');">
          </span>
          <div>
            <p>Secure access</p>
            <h2 id="loginAltTitle">Sign in to portal</h2>
          </div>
        </div>

        <?php if ($loginError === 'invalid'): ?>
          <div class="auth-message auth-message-error" role="alert">
            The role, username, or password is incorrect.
          </div>
        <?php elseif ($loginError === 'inactive_resident'): ?>
          <div class="auth-message auth-message-error" role="alert">Your account is inactive after 6 months without use. Visit the barangay office to confirm your residency with Admin or Staff and restore access.</div>
        <?php elseif ($loginError === 'database'): ?>
          <div class="auth-message auth-message-error" role="alert">
            The database is not connected yet. Please import the users table and check the database settings.
          </div>
        <?php elseif ($loginError === 'login_required'): ?>
          <div class="auth-message auth-message-error" role="alert">
            Please sign in first to open your dashboard.
          </div>
        <?php elseif ($loginStatus === 'logged_out'): ?>
          <div class="auth-message auth-message-success" role="status">
            You have been logged out successfully.
          </div>
        <?php endif; ?>

        <form id="loginForm" class="auth-form" action="login-handler.php" method="post" novalidate>
          <div class="input-group">
            <label for="loginRole">Account type</label>
            <div class="input-wrap input-wrap-select">
              <svg data-original-class="input-icon" viewBox="0 0 256 256" fill="currentColor" stroke="none" aria-hidden="true" data-system-icon="users" class="system-icon input-icon"><path d="M136,108A52,52,0,1,1,84,56,52,52,0,0,1,136,108Z" opacity="0.2"/><path d="M117.25,157.92a60,60,0,1,0-66.5,0A95.83,95.83,0,0,0,3.53,195.63a8,8,0,1,0,13.4,8.74,80,80,0,0,1,134.14,0,8,8,0,0,0,13.4-8.74A95.83,95.83,0,0,0,117.25,157.92ZM40,108a44,44,0,1,1,44,44A44.05,44.05,0,0,1,40,108Zm210.14,98.7a8,8,0,0,1-11.07-2.33A79.83,79.83,0,0,0,172,168a8,8,0,0,1,0-16,44,44,0,1,0-16.34-84.87,8,8,0,1,1-5.94-14.85,60,60,0,0,1,55.53,105.64,95.83,95.83,0,0,1,47.22,37.71A8,8,0,0,1,250.14,206.7Z"/></svg>
              <select id="loginRole" name="role" required>
                <option value="admin" <?= $selectedRole === 'admin' ? 'selected' : '' ?>>Admin</option>
                <option value="staff" <?= $selectedRole === 'staff' ? 'selected' : '' ?>>Barangay Staff</option>
                <option value="resident" <?= $selectedRole === 'resident' ? 'selected' : '' ?>>Resident</option>
              </select>
            </div>
          </div>

          <div class="input-group">
            <label for="loginUsername">Username</label>
            <div class="input-wrap">
              <svg data-original-class="input-icon" viewBox="0 0 256 256" fill="currentColor" stroke="none" aria-hidden="true" data-system-icon="user" class="system-icon input-icon"><path d="M192,96a64,64,0,1,1-64-64A64,64,0,0,1,192,96Z" opacity="0.2"/><path d="M230.92,212c-15.23-26.33-38.7-45.21-66.09-54.16a72,72,0,1,0-73.66,0C63.78,166.78,40.31,185.66,25.08,212a8,8,0,1,0,13.85,8c18.84-32.56,52.14-52,89.07-52s70.23,19.44,89.07,52a8,8,0,1,0,13.85-8ZM72,96a56,56,0,1,1,56,56A56.06,56.06,0,0,1,72,96Z"/></svg>
              <input type="text" id="loginUsername" name="username" value="<?= htmlspecialchars($enteredUsername) ?>" placeholder="username@luna.gov" autocomplete="username" required>
            </div>
          </div>

          <div class="input-group">
            <label for="loginPassword">Password</label>
            <div class="input-wrap">
              <svg data-original-class="input-icon" viewBox="0 0 256 256" fill="currentColor" stroke="none" aria-hidden="true" data-system-icon="lock-keyhole" class="system-icon input-icon"><path d="M208,88H48a8,8,0,0,0-8,8V208a8,8,0,0,0,8,8H208a8,8,0,0,0,8-8V96A8,8,0,0,0,208,88Zm-80,72a20,20,0,1,1,20-20A20,20,0,0,1,128,160Z" opacity="0.2"/><path d="M208,80H176V56a48,48,0,0,0-96,0V80H48A16,16,0,0,0,32,96V208a16,16,0,0,0,16,16H208a16,16,0,0,0,16-16V96A16,16,0,0,0,208,80ZM96,56a32,32,0,0,1,64,0V80H96ZM208,208H48V96H208V208Zm-80-96a28,28,0,0,0-8,54.83V184a8,8,0,0,0,16,0V166.83A28,28,0,0,0,128,112Zm0,40a12,12,0,1,1,12-12A12,12,0,0,1,128,152Z"/></svg>
              <input type="password" id="loginPassword" name="password" placeholder="Enter password" autocomplete="current-password" required>
              <button type="button" class="toggle-pw" id="togglePassword" aria-label="Show password">
                <svg width="20" height="20" viewBox="0 0 256 256" fill="currentColor" stroke="none" aria-hidden="true" data-system-icon="eye" class="system-icon"><path d="M128,56C48,56,16,128,16,128s32,72,112,72,112-72,112-72S208,56,128,56Zm0,112a40,40,0,1,1,40-40A40,40,0,0,1,128,168Z" opacity="0.2"/><path d="M247.31,124.76c-.35-.79-8.82-19.58-27.65-38.41C194.57,61.26,162.88,48,128,48S61.43,61.26,36.34,86.35C17.51,105.18,9,124,8.69,124.76a8,8,0,0,0,0,6.5c.35.79,8.82,19.57,27.65,38.4C61.43,194.74,93.12,208,128,208s66.57-13.26,91.66-38.34c18.83-18.83,27.3-37.61,27.65-38.4A8,8,0,0,0,247.31,124.76ZM128,192c-30.78,0-57.67-11.19-79.93-33.25A133.47,133.47,0,0,1,25,128,133.33,133.33,0,0,1,48.07,97.25C70.33,75.19,97.22,64,128,64s57.67,11.19,79.93,33.25A133.46,133.46,0,0,1,231.05,128C223.84,141.46,192.43,192,128,192Zm0-112a48,48,0,1,0,48,48A48.05,48.05,0,0,0,128,80Zm0,80a32,32,0,1,1,32-32A32,32,0,0,1,128,160Z"/><line class="toggle-pw-slash" data-password-eye-slash x1="4" y1="4" x2="20" y2="20" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" transform="scale(10.6666667)"/></svg>
              </button>
            </div>
          </div>

          <div class="form-meta">
            <label class="check">
              <input type="checkbox" name="remember">
              <span>Keep me signed in</span>
            </label>
            <button type="button" class="text-link" data-modal-open="passwordHelpModal">Forgot password?</button>
          </div>

          <button type="submit" class="btn btn-primary btn-block">
            <span>Sign in</span>
            <svg width="18" height="18" viewBox="0 0 256 256" fill="currentColor" stroke="none" aria-hidden="true" data-system-icon="arrow-right" class="system-icon"><path d="M216,128l-72,72V56Z" opacity="0.2"/><path d="M221.66,122.34l-72-72A8,8,0,0,0,136,56v64H40a8,8,0,0,0,0,16h96v64a8,8,0,0,0,13.66,5.66l72-72A8,8,0,0,0,221.66,122.34ZM152,180.69V75.31L204.69,128Z"/></svg>
          </button>
        </form>

        <button type="button" class="btn btn-outline btn-block" data-modal-open="requirementsModal">
          View registration requirements
        </button>
      </section>

      <section class="quick-info-card">
        <h2>Latest Announcements</h2>
        <ul>
          <?php foreach ($publicAnnouncements as $announcement): ?>
          <li class="public-announcement-item <?= !empty($announcement['photo_path']) ? 'has-photo' : '' ?>">
            <a href="login.php?error=login_required#loginAltCard">
              <?php if (!empty($announcement['photo_path'])): ?>
              <img src="<?= htmlspecialchars($announcement['photo_path']) ?>" alt="<?= htmlspecialchars($announcement['title']) ?>">
              <?php endif; ?>
              <span>
                <strong><?= htmlspecialchars($announcement['title']) ?></strong>
                <small><?= htmlspecialchars(strlen($announcement['body']) > 75 ? substr($announcement['body'], 0, 75) . '...' : $announcement['body']) ?></small>
                <em><?= htmlspecialchars($announcement['date']) ?> - Sign in to view details</em>
              </span>
            </a>
          </li>
          <?php endforeach; ?>
          <?php if (!$publicAnnouncements): ?>
          <li><strong>No announcements yet</strong><span>Please check again soon.</span></li>
          <?php endif; ?>
        </ul>
      </section>

      <div class="quick-info-grid">
        <section class="quick-info-card">
          <h2>Office Hours</h2>
          <p><?= nl2br(htmlspecialchars($publicOfficeDetails['office_hours'] ?? 'Please contact the barangay office for office hours.', ENT_QUOTES)) ?></p>
        </section>
        <section class="quick-info-card">
          <h2>Contact</h2>
          <p><?= htmlspecialchars(barangay_public_contact_text($publicOfficeDetails), ENT_QUOTES) ?></p>
        </section>
      </div>

      <footer class="portal-footer">
        &copy; 2026 Barangay Luna. All Rights Reserved.
      </footer>
    </aside>
  </main>

  <div id="requirementsModal" class="modal" aria-hidden="true" role="dialog" aria-labelledby="requirementsTitle">
    <div class="modal-backdrop" data-modal-close></div>
    <div class="modal-dialog modal-dialog-wide">
      <div class="modal-accent"></div>
      <div class="modal-content">
        <header class="modal-head">
          <div>
            <p class="modal-step">In-person registration</p>
            <h2 id="requirementsTitle">Registration requirements</h2>
          </div>
          <button type="button" class="modal-close" data-modal-close aria-label="Close">
            <svg width="20" height="20" viewBox="0 0 256 256" fill="currentColor" stroke="none" aria-hidden="true" data-system-icon="phone" class="system-icon"><path d="M223.94,174.08A48.33,48.33,0,0,1,176,216,136,136,0,0,1,40,80,48.33,48.33,0,0,1,81.92,32.06a8,8,0,0,1,8.3,4.8l21.13,47.2a8,8,0,0,1-.66,7.53L89.32,117a7.93,7.93,0,0,0-.54,7.81c8.27,16.93,25.77,34.22,42.75,42.41a7.92,7.92,0,0,0,7.83-.59l25-21.3a8,8,0,0,1,7.59-.69l47.16,21.13A8,8,0,0,1,223.94,174.08Z" opacity="0.2"/><path d="M222.37,158.46l-47.11-21.11-.13-.06a16,16,0,0,0-15.17,1.4,8.12,8.12,0,0,0-.75.56L134.87,160c-15.42-7.49-31.34-23.29-38.83-38.51l20.78-24.71c.2-.25.39-.5.57-.77a16,16,0,0,0,1.32-15.06l0-.12L97.54,33.64a16,16,0,0,0-16.62-9.52A56.26,56.26,0,0,0,32,80c0,79.4,64.6,144,144,144a56.26,56.26,0,0,0,55.88-48.92A16,16,0,0,0,222.37,158.46ZM176,208A128.14,128.14,0,0,1,48,80,40.2,40.2,0,0,1,82.87,40a.61.61,0,0,0,0,.12l21,47L83.2,111.86a6.13,6.13,0,0,0-.57.77,16,16,0,0,0-1,15.7c9.06,18.53,27.73,37.06,46.46,46.11a16,16,0,0,0,15.75-1.14,8.44,8.44,0,0,0,.74-.56L168.89,152l47,21.05h0s.08,0,.11,0A40.21,40.21,0,0,1,176,208Z"/></svg>
          </button>
        </header>
        <?php include __DIR__ . '/includes/requirements-modal-body.php'; ?>
      </div>
    </div>
  </div>

  <div id="passwordHelpModal" class="modal" aria-hidden="true" role="dialog" aria-labelledby="passwordHelpTitle">
    <div class="modal-backdrop" data-modal-close></div>
    <div class="modal-dialog modal-dialog-sm">
      <div class="modal-accent"></div>
      <div class="modal-content">
        <header class="modal-head">
          <div>
            <p class="modal-step">Account recovery</p>
            <h2 id="passwordHelpTitle">Reset your password</h2>
          </div>
          <button type="button" class="modal-close" data-modal-close aria-label="Close">
            <svg width="20" height="20" viewBox="0 0 256 256" fill="currentColor" stroke="none" aria-hidden="true" data-system-icon="building-2" class="system-icon"><path d="M136,32V216H40V85.35a8,8,0,0,1,3.56-6.66l80-53.33A8,8,0,0,1,136,32Z" opacity="0.2"/><path d="M240,208H224V96a16,16,0,0,0-16-16H144V32a16,16,0,0,0-24.88-13.32L39.12,72A16,16,0,0,0,32,85.34V208H16a8,8,0,0,0,0,16H240a8,8,0,0,0,0-16ZM208,96V208H144V96ZM48,85.34,128,32V208H48ZM112,112v16a8,8,0,0,1-16,0V112a8,8,0,1,1,16,0Zm-32,0v16a8,8,0,0,1-16,0V112a8,8,0,1,1,16,0Zm0,56v16a8,8,0,0,1-16,0V168a8,8,0,0,1,16,0Zm32,0v16a8,8,0,0,1-16,0V168a8,8,0,0,1,16,0Z"/></svg>
          </button>
        </header>
        <div class="modal-form">
          <p class="modal-lead">Visit the Barangay Luna office and ask the system administrator to reset your account password. Bring your resident ID or another valid ID for identity verification.</p>
          <p>No verification code has been sent. Password recovery is handled by the administrator.</p>
          <button type="button" class="btn btn-primary btn-block" data-modal-close>Back to sign in</button>
        </div>
      </div>
    </div>
  </div>

  <script src="js/main.js?v=<?= filemtime(__DIR__ . '/js/main.js') ?>"></script>
  <script src="js/mobile-select.js?v=<?= filemtime(__DIR__ . '/js/mobile-select.js') ?>"></script>
  <script src="js/location-disclosures.js?v=<?= filemtime(__DIR__ . '/js/location-disclosures.js') ?>"></script>
<?php require __DIR__ . '/includes/system-icons.php'; ?>
</body>
</html>
