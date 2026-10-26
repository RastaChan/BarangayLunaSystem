<?php require_once __DIR__ . '/public-office-details.php'; $publicOfficeDetails = barangay_public_office_details(); ?>
        <div class="requirements-body modal-form">
          <div class="requirements-alert">
            <svg width="20" height="20" viewBox="0 0 256 256" fill="currentColor" stroke="none" aria-hidden="true" data-system-icon="badge-check" class="system-icon"><path d="M232,128c0,12.51-17.82,21.95-22.68,33.69-4.68,11.32,1.42,30.65-7.78,39.85s-28.53,3.1-39.85,7.78C150,214.18,140.5,232,128,232s-22-17.82-33.69-22.68c-11.32-4.68-30.65,1.42-39.85-7.78s-3.1-28.53-7.78-39.85C41.82,150,24,140.5,24,128s17.82-22,22.68-33.69C51.36,83,45.26,63.66,54.46,54.46S83,51.36,94.31,46.68C106.05,41.82,115.5,24,128,24S150,41.82,161.69,46.68c11.32,4.68,30.65-1.42,39.85,7.78s3.1,28.53,7.78,39.85C214.18,106.05,232,115.5,232,128Z" opacity="0.2"/><path d="M225.86,102.82c-3.77-3.94-7.67-8-9.14-11.57-1.36-3.27-1.44-8.69-1.52-13.94-.15-9.76-.31-20.82-8-28.51s-18.75-7.85-28.51-8c-5.25-.08-10.67-.16-13.94-1.52-3.56-1.47-7.63-5.37-11.57-9.14C146.28,23.51,138.44,16,128,16s-18.27,7.51-25.18,14.14c-3.94,3.77-8,7.67-11.57,9.14C88,40.64,82.56,40.72,77.31,40.8c-9.76.15-20.82.31-28.51,8S41,67.55,40.8,77.31c-.08,5.25-.16,10.67-1.52,13.94-1.47,3.56-5.37,7.63-9.14,11.57C23.51,109.72,16,117.56,16,128s7.51,18.27,14.14,25.18c3.77,3.94,7.67,8,9.14,11.57,1.36,3.27,1.44,8.69,1.52,13.94.15,9.76.31,20.82,8,28.51s18.75,7.85,28.51,8c5.25.08,10.67.16,13.94,1.52,3.56,1.47,7.63,5.37,11.57,9.14C109.72,232.49,117.56,240,128,240s18.27-7.51,25.18-14.14c3.94-3.77,8-7.67,11.57-9.14,3.27-1.36,8.69-1.44,13.94-1.52,9.76-.15,20.82-.31,28.51-8s7.85-18.75,8-28.51c.08-5.25.16-10.67,1.52-13.94,1.47-3.56,5.37-7.63,9.14-11.57C232.49,146.28,240,138.44,240,128S232.49,109.73,225.86,102.82Zm-11.55,39.29c-4.79,5-9.75,10.17-12.38,16.52-2.52,6.1-2.63,13.07-2.73,19.82-.1,7-.21,14.33-3.32,17.43s-10.39,3.22-17.43,3.32c-6.75.1-13.72.21-19.82,2.73-6.35,2.63-11.52,7.59-16.52,12.38S132,224,128,224s-9.15-4.92-14.11-9.69-10.17-9.75-16.52-12.38c-6.1-2.52-13.07-2.63-19.82-2.73-7-.1-14.33-.21-17.43-3.32s-3.22-10.39-3.32-17.43c-.1-6.75-.21-13.72-2.73-19.82-2.63-6.35-7.59-11.52-12.38-16.52S32,132,32,128s4.92-9.15,9.69-14.11,9.75-10.17,12.38-16.52c2.52-6.1,2.63-13.07,2.73-19.82.1-7,.21-14.33,3.32-17.43S70.51,56.9,77.55,56.8c6.75-.1,13.72-.21,19.82-2.73,6.35-2.63,11.52-7.59,16.52-12.38S124,32,128,32s9.15,4.92,14.11,9.69,10.17,9.75,16.52,12.38c6.1,2.52,13.07,2.63,19.82,2.73,7,.1,14.33.21,17.43,3.32s3.22,10.39,3.32,17.43c.1,6.75.21,13.72,2.73,19.82,2.63,6.35,7.59,11.52,12.38,16.52S224,124,224,128,219.08,137.15,214.31,142.11ZM173.66,98.34a8,8,0,0,1,0,11.32l-56,56a8,8,0,0,1-11.32,0l-24-24a8,8,0,0,1,11.32-11.32L112,148.69l50.34-50.35A8,8,0,0,1,173.66,98.34Z"/></svg>
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
                <svg  viewBox="0 0 256 256" fill="currentColor" stroke="none" aria-hidden="true" data-system-icon="id-card" class="system-icon"><path d="M216,48H40a8,8,0,0,0-8,8V200a8,8,0,0,0,8,8H216a8,8,0,0,0,8-8V56A8,8,0,0,0,216,48ZM96,144a24,24,0,1,1,24-24A24,24,0,0,1,96,144Z" opacity="0.2"/><path d="M200,112a8,8,0,0,1-8,8H152a8,8,0,0,1,0-16h40A8,8,0,0,1,200,112Zm-8,24H152a8,8,0,0,0,0,16h40a8,8,0,0,0,0-16Zm40-80V200a16,16,0,0,1-16,16H40a16,16,0,0,1-16-16V56A16,16,0,0,1,40,40H216A16,16,0,0,1,232,56ZM216,200V56H40V200H216Zm-80.26-34a8,8,0,1,1-15.5,4c-2.63-10.26-13.06-18-24.25-18s-21.61,7.74-24.25,18a8,8,0,1,1-15.5-4,39.84,39.84,0,0,1,17.19-23.34,32,32,0,1,1,45.12,0A39.76,39.76,0,0,1,135.75,166ZM96,136a16,16,0,1,0-16-16A16,16,0,0,0,96,136Z"/></svg>
                <?php elseif ($req['icon'] === 'home'): ?>
                <svg  viewBox="0 0 256 256" fill="currentColor" stroke="none" aria-hidden="true" data-system-icon="house" class="system-icon"><path d="M216,120v96H152V152H104v64H40V120a8,8,0,0,1,2.34-5.66l80-80a8,8,0,0,1,11.32,0l80,80A8,8,0,0,1,216,120Z" opacity="0.2"/><path d="M219.31,108.68l-80-80a16,16,0,0,0-22.62,0l-80,80A15.87,15.87,0,0,0,32,120v96a8,8,0,0,0,8,8h64a8,8,0,0,0,8-8V160h32v56a8,8,0,0,0,8,8h64a8,8,0,0,0,8-8V120A15.87,15.87,0,0,0,219.31,108.68ZM208,208H160V152a8,8,0,0,0-8-8H104a8,8,0,0,0-8,8v56H48V120l80-80,80,80Z"/></svg>
                <?php elseif ($req['icon'] === 'folder'): ?>
                <svg  viewBox="0 0 256 256" fill="currentColor" stroke="none" aria-hidden="true" data-system-icon="file-text" class="system-icon"><path d="M208,88H152V32Z" opacity="0.2"/><path d="M213.66,82.34l-56-56A8,8,0,0,0,152,24H56A16,16,0,0,0,40,40V216a16,16,0,0,0,16,16H200a16,16,0,0,0,16-16V88A8,8,0,0,0,213.66,82.34ZM160,51.31,188.69,80H160ZM200,216H56V40h88V88a8,8,0,0,0,8,8h48V216Zm-32-80a8,8,0,0,1-8,8H96a8,8,0,0,1,0-16h64A8,8,0,0,1,168,136Zm0,32a8,8,0,0,1-8,8H96a8,8,0,0,1,0-16h64A8,8,0,0,1,168,168Z"/></svg>
                <?php else: ?>
                <svg  viewBox="0 0 256 256" fill="currentColor" stroke="none" aria-hidden="true" data-system-icon="file-text" class="system-icon"><path d="M208,88H152V32Z" opacity="0.2"/><path d="M213.66,82.34l-56-56A8,8,0,0,0,152,24H56A16,16,0,0,0,40,40V216a16,16,0,0,0,16,16H200a16,16,0,0,0,16-16V88A8,8,0,0,0,213.66,82.34ZM160,51.31,188.69,80H160ZM200,216H56V40h88V88a8,8,0,0,0,8,8h48V216Zm-32-80a8,8,0,0,1-8,8H96a8,8,0,0,1,0-16h64A8,8,0,0,1,168,136Zm0,32a8,8,0,0,1-8,8H96a8,8,0,0,1,0-16h64A8,8,0,0,1,168,168Z"/></svg>
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
              <div><dt>Location</dt><dd><?= htmlspecialchars($publicOfficeDetails['barangay_address'] ?? 'Barangay Luna, Surigao City, Surigao del Norte, Philippines', ENT_QUOTES) ?></dd></div>
              <div><dt>Hours</dt><dd><?= htmlspecialchars($publicOfficeDetails['office_hours'] ?? 'Please contact the barangay office for office hours.', ENT_QUOTES) ?></dd></div>
              <div><dt>Contact</dt><dd><?= htmlspecialchars(barangay_public_contact_text($publicOfficeDetails), ENT_QUOTES) ?></dd></div>
            </dl>
          </div>

          <button type="button" class="btn btn-primary btn-block" data-modal-close>I understand</button>
        </div>
