<div class="page-intro">
  <h2>Resident Verification</h2>
  <p>Confirm resident identity, household details, and eligibility before issuing documents.</p>
</div>

<div class="grid-2">
  <section class="panel-card">
    <div class="panel-head"><h3>Search resident</h3></div>
    <div class="panel-body">
      <form class="form-grid" data-table-filter-panel data-table-filter-target="residentVerificationTable">
        <div class="field">
          <label>Name, resident ID, address, or household number</label>
          <input type="search" placeholder="Search resident records" data-table-text-filter>
        </div>
        <button type="submit" class="btn btn-primary">Search resident</button>
      </form>
    </div>
    <div class="table-wrap">
      <table class="data-table" id="residentVerificationTable">
        <thead><tr><th>ID</th><th>Name</th><th>Address</th><th>Status</th><th>Eligibility</th><th>Action</th></tr></thead>
        <tbody>
          <?php foreach ($residentRecords as $resident): ?>
          <tr data-search-text="<?= htmlspecialchars($resident['household'] ?? '', ENT_QUOTES) ?>">
            <td><?= htmlspecialchars($resident['id']) ?></td>
            <td><?= htmlspecialchars($resident['name']) ?></td>
            <td><?= htmlspecialchars($resident['address']) ?></td>
            <td><span class="badge <?= $resident['status'] === 'Active' ? 'badge-active' : 'badge-inactive' ?>"><?= htmlspecialchars($resident['status']) ?></span></td>
            <td><?= htmlspecialchars($resident['eligible']) ?></td>
            <td class="actions">
              <button type="button" class="btn-icon" data-view-verification-resident data-json='<?= htmlspecialchars(json_encode($resident), ENT_QUOTES) ?>'>View profile</button>
              <form action="staff-actions.php" method="post" class="inline-form">
                <input type="hidden" name="action" value="verify_resident">
                <input type="hidden" name="redirect" value="resident-verification">
                <input type="hidden" name="id" value="<?= htmlspecialchars($resident['id']) ?>">
                <button type="submit" class="btn-icon">Verify</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (!$residentRecords): ?>
          <tr><td colspan="6">No resident records yet.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>

  <section class="panel-card">
    <div class="panel-head"><h3>Basic resident profile</h3></div>
    <div class="panel-body">
      <div class="profile-summary">
        <strong data-verification-resident-name><?= htmlspecialchars($residentRecords[0]['name'] ?? 'No resident selected') ?></strong>
        <span data-verification-resident-id><?= htmlspecialchars($residentRecords[0]['id'] ?? '') ?></span>
      </div>
      <dl class="detail-list">
        <dt>Address</dt><dd data-verification-resident-address><?= htmlspecialchars($residentRecords[0]['address'] ?? '') ?></dd>
        <dt>Household number</dt><dd data-verification-resident-household><?= htmlspecialchars($residentRecords[0]['household'] ?? '') ?></dd>
        <dt>Status</dt><dd data-verification-resident-status><?= htmlspecialchars($residentRecords[0]['status'] ?? '') ?></dd>
        <dt>Eligibility</dt><dd data-verification-resident-eligibility><?= htmlspecialchars($residentRecords[0]['eligible'] ?? '') ?></dd>
      </dl>
      <?php if ($residentRecords): ?>
      <form action="staff-actions.php" method="post" data-verification-resident-form>
        <input type="hidden" name="action" value="verify_resident">
        <input type="hidden" name="redirect" value="resident-verification">
        <input type="hidden" name="id" value="<?= htmlspecialchars($residentRecords[0]['id']) ?>" data-verification-resident-input>
        <button type="submit" class="btn btn-primary">Confirm eligibility</button>
      </form>
      <?php endif; ?>
    </div>
  </section>
</div>
