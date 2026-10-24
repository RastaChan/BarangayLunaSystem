<div class="page-intro">
  <h2>My Profile</h2>
  <p>View and update your own staff profile details.</p>
</div>

<section class="panel-card">
  <div class="panel-head"><h3>Profile information</h3></div>
  <div class="panel-body">
    <form action="staff-actions.php" method="post" class="form-grid">
      <input type="hidden" name="action" value="save_profile">
      <input type="hidden" name="redirect" value="my-profile">
      <div class="form-grid-2">
        <div class="field">
          <label>Name</label>
          <input type="text" name="full_name" value="<?= htmlspecialchars($staffName) ?>" required>
        </div>
        <div class="field">
          <label>Position</label>
          <input type="text" name="position" value="<?= htmlspecialchars($staffRole) ?>" required>
        </div>
      </div>
      <div class="form-grid-2">
        <div class="field">
          <label>Username</label>
          <input type="text" value="<?= htmlspecialchars($loggedInUser['username'] ?? '') ?>" readonly>
        </div>
        <div class="field">
          <label>Mobile number</label>
          <input type="tel" name="mobile" value="<?= htmlspecialchars($loggedInUser['contact'] ?? '') ?>">
        </div>
      </div>
      <div class="toolbar">
        <button type="submit" class="btn btn-primary">Save profile</button>
      </div>
    </form>
  </div>
</section>
