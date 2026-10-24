<div class="page-intro"><h2>Submit Complaint</h2><p>File a barangay complaint or community concern.</p></div>
<section class="panel-card"><div class="panel-head"><h3>Complaint form</h3><span class="badge badge-open">Status after submission: Open</span></div><div class="panel-body">
  <form action="resident-actions.php" method="post" enctype="multipart/form-data" class="form-grid" data-resident-review="complaint">
    <input type="hidden" name="service_csrf" value="<?= htmlspecialchars($_SESSION['resident_service_csrf']) ?>">
    <input type="hidden" name="review_confirmed" value="0">
    <input type="hidden" name="action" value="submit_complaint">
    <input type="hidden" name="redirect" value="submit-complaint">
    <div class="form-grid-2"><div class="field"><label>Complaint subject</label><input type="text" name="subject" placeholder="Short subject" required></div><div class="field"><label>Complaint category</label><select name="category"><option>Garbage collection</option><option>Street lighting</option><option>Noise complaint</option><option>Drainage issue</option><option>Road concern</option><option>Neighbor concern</option><option>Other concern</option></select></div></div>
    <div class="field"><label>Complaint description</label><textarea name="details" rows="5" placeholder="Describe your concern" required></textarea></div>
    <div class="field"><label>Location of concern</label><input type="text" name="location" placeholder="Street, purok, or landmark"></div>
    <div class="field"><label>Photo/evidence (JPG, PNG, PDF; maximum 5 MB)</label><input type="file" name="complaint_evidence" accept=".jpg,.jpeg,.png,.pdf"></div>
    <button class="btn btn-primary" type="submit">Submit complaint</button>
  </form>
</div></section>
