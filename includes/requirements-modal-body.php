        <div class="requirements-body modal-form">
          <div class="requirements-alert">
            <svg viewBox="0 0 24 24" fill="none" width="20" height="20" aria-hidden="true">
              <path d="M12 8v4M12 16h.01M4 6l8-4 8 4v6l-8 4-8-4V6Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
            </svg>
            <div>
              <strong>Register at the Barangay Office</strong>
              <p>Resident accounts are created in person for verification. Visit <strong>Barangay Luna, Surigao City, Surigao del Norte, Philippines</strong> during office hours with the documents below. Bring originals for validation and photocopies where applicable.</p>
            </div>
          </div>

          <ul class="requirements-list">
            <?php foreach ($registrationRequirements as $req): ?>
            <li class="requirement-item">
              <div class="requirement-icon" aria-hidden="true">
                <?php if ($req['icon'] === 'id'): ?>
                <svg viewBox="0 0 24 24" fill="none"><rect x="3" y="5" width="18" height="14" rx="2" stroke="currentColor" stroke-width="1.5"/><circle cx="9" cy="11" r="2" stroke="currentColor" stroke-width="1.5"/><path d="M14 10h4M14 14h3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                <?php elseif ($req['icon'] === 'home'): ?>
                <svg viewBox="0 0 24 24" fill="none"><path d="M4 10.5 12 4l8 6.5V20a1 1 0 0 1-1 1h-5v-6H10v6H5a1 1 0 0 1-1-1v-9.5Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/></svg>
                <?php elseif ($req['icon'] === 'folder'): ?>
                <svg viewBox="0 0 24 24" fill="none"><path d="M4 8a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V8Z" stroke="currentColor" stroke-width="1.5"/></svg>
                <?php else: ?>
                <svg viewBox="0 0 24 24" fill="none"><path d="M8 4h8l2 4v12a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1h1Z" stroke="currentColor" stroke-width="1.5"/><path d="M9 12h6M9 16h4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                <?php endif; ?>
              </div>
              <div class="requirement-content">
                <div class="requirement-head">
                  <h3><?= htmlspecialchars($req['title']) ?></h3>
                  <?php if ($req['required']): ?>
                  <span class="req-badge req-badge--required">Required</span>
                  <?php else: ?>
                  <span class="req-badge req-badge--optional">If applicable</span>
                  <?php endif; ?>
                </div>
                <p><?= htmlspecialchars($req['description']) ?></p>
                <?php if (!empty($req['subitems'])): ?>
                <ul class="requirement-sublist">
                  <?php foreach ($req['subitems'] as $sub): ?>
                  <li><?= htmlspecialchars($sub) ?></li>
                  <?php endforeach; ?>
                </ul>
                <?php endif; ?>
              </div>
            </li>
            <?php endforeach; ?>
          </ul>

          <div class="requirements-office">
            <h4>Office information</h4>
            <dl>
              <div><dt>Location</dt><dd>Barangay Luna, Surigao City, Surigao del Norte, Philippines</dd></div>
              <div><dt>Hours</dt><dd>Monday – Friday, 8:00 AM – 5:00 PM</dd></div>
              <div><dt>Contact</dt><dd>Tel: (000) 000-0000 · mis@barangayluna.gov.ph</dd></div>
            </dl>
          </div>

          <button type="button" class="btn btn-primary btn-block" data-modal-close>I understand</button>
        </div>
