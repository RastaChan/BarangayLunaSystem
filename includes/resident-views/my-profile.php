<div class="page-intro"><h2>My Profile</h2><p>View your resident profile and update allowed account information.</p></div>
<section class="panel-card"><div class="panel-head"><h3>Resident profile</h3></div><div class="panel-body">
  <form action="resident-actions.php" method="post" enctype="multipart/form-data" class="form-grid">
    <input type="hidden" name="action" value="update_profile">
    <input type="hidden" name="redirect" value="my-profile">
    <div class="form-grid-2"><div class="field"><label>Resident ID</label><input type="text" value="<?= htmlspecialchars($residentProfile['id']) ?>" readonly></div><div class="field"><label>Full name</label><input type="text" value="<?= htmlspecialchars($residentProfile['name']) ?>" readonly></div></div>
    <div class="form-grid-2"><div class="field"><label>Birthdate</label><input type="date" value="<?= htmlspecialchars($residentProfile['birthdate']) ?>" readonly></div><div class="field"><label>Gender</label><input type="text" value="<?= htmlspecialchars($residentProfile['gender']) ?>" readonly></div></div>
    <div class="form-grid-2"><div class="field"><label>Civil status</label><input type="text" value="<?= htmlspecialchars($residentProfile['civil_status']) ?>" readonly></div><div class="field"><label>Contact number</label><input type="tel" name="mobile" value="<?= htmlspecialchars($residentProfile['contact']) ?>"></div></div>
    <div class="form-grid-2"><div class="field"><label>Username</label><input type="text" value="<?= htmlspecialchars($loggedInUser['username'] ?? '') ?>" readonly></div><div class="field"><label for="residentProfilePhoto">Profile photo</label><input id="residentProfilePhoto" type="file" name="profile_photo" accept=".jpg,.jpeg,.png"></div></div>
    <div class="field"><label>Complete address</label><input type="text" value="<?= htmlspecialchars($residentProfile['address']) ?>" readonly></div>
    <div class="form-grid-2"><div class="field"><label>Purok/Sitio</label><input type="text" value="<?= htmlspecialchars($residentProfile['purok']) ?>" readonly></div><div class="field"><label>Household number</label><input type="text" value="<?= htmlspecialchars($residentProfile['household']) ?>" readonly></div></div>
    <div class="form-grid-2"><div class="field"><label>Account status</label><input type="text" value="<?= htmlspecialchars($residentProfile['account_status']) ?>" readonly></div></div>
    <button type="submit" class="btn btn-primary">Save allowed changes</button>
  </form>
</div></section>
