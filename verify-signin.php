<?php
require_once __DIR__ . '/includes/sms-verification.php';
barangay_start_session();
$pending = $_SESSION['pending_verification'] ?? null;
if (!$pending || time() - $pending['started'] > 900) {
  unset($_SESSION['pending_verification']);
  header('Location: login.php?error=login_required'); exit;
}
$error = $_SESSION['verification_error'] ?? '';
unset($_SESSION['verification_error']);
$notice = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  try {
    if (empty($_SESSION['verification_csrf']) || !hash_equals($_SESSION['verification_csrf'], (string) ($_POST['csrf'] ?? ''))) throw new RuntimeException('Refresh the page and try again.');
    if (($_POST['action'] ?? '') === 'cancel') {
      unset($_SESSION['pending_verification'], $_SESSION['verification_csrf']);
      header('Location: login.php'); exit;
    }
    if (($_POST['action'] ?? '') === 'resend') {
      sms_verification_send($pending);
      $notice = 'Code submitted to the SMS provider. Check your registered phone.';
      $error = '';
    } else {
      $user = sms_verification_check($pending, trim((string) ($_POST['code'] ?? '')));
      unset($_SESSION['pending_verification'], $_SESSION['verification_csrf']);
      barangay_record_login($user['id']);
      barangay_sign_in($user);
      header('Location: ' . barangay_dashboard_for_role($user['role'])); exit;
    }
  } catch (Throwable $e) { $error = $e instanceof PDOException ? 'Verification is temporarily unavailable.' : $e->getMessage(); }
}
header('Cache-Control: no-store');
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Verify sign-in | Barangay Luna</title>
<link rel="stylesheet" href="css/motion.css?v=<?= filemtime(__DIR__ . '/css/motion.css') ?>">
<link rel="stylesheet" href="css/dashboard.css"><style>body{display:grid;place-items:center;min-height:100vh;padding:20px}main{width:min(100%,440px)}input,button{max-width:100%}.verification-logo{width:72px;height:72px;object-fit:contain}.verification-error{color:#a31227;overflow-wrap:anywhere}</style></head><body>
<main><img class="verification-logo" src="branding-logo.php" alt="Barangay Luna"><h1>Verify sign-in</h1><p>Enter the six-digit code sent to your registered mobile number.</p>
<?php if ($error): ?><p class="verification-error" role="alert"><?= htmlspecialchars($error) ?></p><?php endif; ?>
<?php if ($notice): ?><p role="status"><?= htmlspecialchars($notice) ?></p><?php endif; ?>
<form method="post" class="form-grid"><input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['verification_csrf']) ?>">
<div class="field"><label for="code">Verification code</label><input id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required></div>
<button type="submit" class="btn btn-primary" name="action" value="verify">Verify</button>
<button type="submit" class="btn btn-outline" name="action" value="resend" formnovalidate>Resend code</button>
<button type="submit" class="btn btn-outline" name="action" value="cancel" formnovalidate>Back to sign in</button></form>
</main></body></html>
