<?php $_SESSION['account_security_csrf'] ??= bin2hex(random_bytes(32)); ?>
<div class="page-intro"><h2>Change Password</h2><p>Keep your resident account secure by updating your password.</p></div>
<section class="panel-card"><div class="panel-head"><h3>Password security</h3></div><div class="panel-body">
  <form action="resident-actions.php" method="post" class="form-grid">
    <input type="hidden" name="action" value="change_password">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['account_security_csrf'], ENT_QUOTES) ?>">
    <input type="hidden" name="redirect" value="change-password">
    <div class="field"><label>Current password</label><input type="password" name="current_password" placeholder="Enter current password" required></div>
    <div class="field"><label>New password</label><input type="password" name="new_password" placeholder="Enter new password" minlength="8" maxlength="72" autocomplete="new-password" required></div>
    <div class="field"><label>Confirm new password</label><input type="password" name="confirm_password" placeholder="Confirm new password" minlength="8" required></div>
    <div class="info-banner"><div><strong>Password validation</strong>New password and confirm password must match before saving.</div></div>
    <button type="submit" class="btn btn-primary">Save password</button>
  </form>
</div></section>
