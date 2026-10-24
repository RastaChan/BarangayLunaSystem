<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/announcement-helpers.php';

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
            <svg viewBox="0 0 24 24" fill="none"><path d="M4 5h16v14H4V5Zm4 4h8M8 13h5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
          </span>
          <div>
            <h2>Online Services</h2>
            <p>Access core barangay transactions online.</p>
          </div>
        </article>
        <article class="portal-feature-card">
          <span class="feature-icon">
            <svg viewBox="0 0 24 24" fill="none"><path d="M8 4h8l2 4v12H6V4h2Zm1 8h6M9 16h4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
          </span>
          <div>
            <h2>Request Documents</h2>
            <p>Submit and track barangay certificates.</p>
          </div>
        </article>
        <article class="portal-feature-card">
          <span class="feature-icon">
            <svg viewBox="0 0 24 24" fill="none"><path d="M3 3h7v7H3V3Zm11 0h7v7h-7V3ZM3 14h7v7H3v-7Zm14 1h4m-4 4h4m-4-4v4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
          </span>
          <div>
            <h2>QR Verification</h2>
            <p>Verify resident profiles faster.</p>
          </div>
        </article>
        <article class="portal-feature-card">
          <span class="feature-icon">
            <svg viewBox="0 0 24 24" fill="none"><path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4v8ZM9 10h6M9 14h3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
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
              <svg class="input-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <path d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z" stroke="currentColor" stroke-width="1.5"/>
                <path d="M4 21a8 8 0 0 1 16 0" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
              </svg>
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
              <svg class="input-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <path d="M4 6h16v12H4V6Z" stroke="currentColor" stroke-width="1.5"/>
                <path d="M4 7l8 6 8-6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
              </svg>
              <input type="text" id="loginUsername" name="username" value="<?= htmlspecialchars($enteredUsername) ?>" placeholder="username@luna.gov" autocomplete="username" required>
            </div>
          </div>

          <div class="input-group">
            <label for="loginPassword">Password</label>
            <div class="input-wrap">
              <svg class="input-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <rect x="5" y="11" width="14" height="10" rx="2" stroke="currentColor" stroke-width="1.5"/>
                <path d="M8 11V8a4 4 0 1 1 8 0v3" stroke="currentColor" stroke-width="1.5"/>
              </svg>
              <input type="password" id="loginPassword" name="password" placeholder="Enter password" autocomplete="current-password" required>
              <button type="button" class="toggle-pw" id="togglePassword" aria-label="Show password">
                <svg viewBox="0 0 24 24" fill="none" width="20" height="20" aria-hidden="true">
                  <path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12Z" stroke="currentColor" stroke-width="1.5"/>
                  <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.5"/>
                  <line class="toggle-pw-slash" data-password-eye-slash x1="4" y1="4" x2="20" y2="20" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/>
                </svg>
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
            <svg viewBox="0 0 24 24" fill="none" width="18" height="18" aria-hidden="true">
              <path d="M5 12h14M13 6l6 6-6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
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
          <p>Monday to Friday<br>8:00 AM - 5:00 PM</p>
        </section>
        <section class="quick-info-card">
          <h2>Contact</h2>
          <p>(000) 000-0000<br>mis@barangayluna.gov.ph</p>
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
            <svg viewBox="0 0 24 24" width="20" height="20"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
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
            <svg viewBox="0 0 24 24" width="20" height="20"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
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
</body>
</html>
