      <div class="page-intro">
        <h2>System Settings</h2>
        <p>Configure barangay information, SMS settings, document types, and security options.</p>
      </div>

      <?php $_SESSION['settings_csrf'] ??= bin2hex(random_bytes(32)); ?>
      <form class="settings-form" action="admin-actions.php" method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['settings_csrf']) ?>">
        <input type="hidden" name="action" value="save_settings">
        <input type="hidden" name="redirect" value="system-settings">
        <!-- Barangay Information -->
        <section class="panel-card">
          <div class="panel-head"><h3>Barangay Information</h3></div>
          <div class="panel-body">
            <div class="form-grid-2">
              <div class="form-group">
                <label for="barangayName">Barangay Name</label>
                <input type="text" id="barangayName" name="settings[barangay_name]" value="<?= htmlspecialchars($systemSettings['barangay_name'] ?? 'Barangay Luna') ?>" required>
              </div>
              <div class="form-group">
                <label for="barangayCode">Barangay Code</label>
                <input type="text" id="barangayCode" name="settings[barangay_code]" value="<?= htmlspecialchars($systemSettings['barangay_code'] ?? 'BL-001') ?>" required>
              </div>
            </div>
            <div class="form-group">
              <label for="barangayAddress">Complete Address</label>
              <input type="text" id="barangayAddress" name="settings[barangay_address]" value="<?= htmlspecialchars($systemSettings['barangay_address'] ?? 'Barangay Luna, Surigao City, Surigao del Norte, Philippines') ?>" required>
            </div>
            <div class="form-grid-2">
              <div class="form-group">
                <label for="contactNumber">Contact Number</label>
                <input type="tel" id="contactNumber" name="settings[contact_number]" value="<?= htmlspecialchars($systemSettings['contact_number'] ?? '') ?>" required>
              </div>
              <div class="form-group">
                <label for="hotlineNumber">Hotline Number</label>
                <input type="tel" id="hotlineNumber" name="settings[hotline_number]" value="<?= htmlspecialchars($systemSettings['hotline_number'] ?? '') ?>" required>
              </div>
            </div>
            <div class="form-grid-2">
              <div class="form-group">
                <label for="officeHours">Office Hours</label>
                <input type="text" id="officeHours" name="settings[office_hours]" value="<?= htmlspecialchars($systemSettings['office_hours'] ?? 'Monday - Friday, 8:00 AM - 5:00 PM') ?>" placeholder="e.g., Monday - Friday, 8:00 AM - 5:00 PM">
              </div>
              <div class="form-group">
                <label for="holidaySchedule">Holiday Schedule</label>
                <?php $holidaySchedule = $systemSettings['holiday_schedule'] ?? 'Philippine National Holidays'; ?>
                <select id="holidaySchedule" name="settings[holiday_schedule]">
                  <option <?= $holidaySchedule === 'Philippine National Holidays' ? 'selected' : '' ?>>Philippine National Holidays</option>
                  <option <?= $holidaySchedule === 'Custom Holidays' ? 'selected' : '' ?>>Custom Holidays</option>
                </select>
              </div>
            </div>
          </div>
        </section>

        <!-- Logo & Branding -->
        <section class="panel-card">
          <div class="panel-head"><h3>Logo & Branding</h3></div>
          <div class="panel-body">
            <div class="logo-upload">
              <div class="logo-preview">
                <img src="branding-logo.php" alt="Current logo" style="max-width:100px;max-height:100px">
              </div>
              <div class="logo-controls">
                <input type="file" name="logo" accept="image/png,image/jpeg" id="logoUpload">
                <p class="field-hint">PNG or JPG, up to 2 MB. Save Changes to apply.</p>
                <button type="button" class="btn btn-outline btn-sm" onclick="document.getElementById('logoUpload').click()">
                  <svg width="16" height="16" viewBox="0 0 256 256" fill="currentColor" stroke="none" aria-hidden="true" data-system-icon="upload" class="system-icon"><path d="M216,48V208H40V48A16,16,0,0,1,56,32H200A16,16,0,0,1,216,48Z" opacity="0.2"/><path d="M224,144v64a8,8,0,0,1-8,8H40a8,8,0,0,1-8-8V144a8,8,0,0,1,16,0v56H208V144a8,8,0,0,1,16,0ZM93.66,77.66,120,51.31V144a8,8,0,0,0,16,0V51.31l26.34,26.35a8,8,0,0,0,11.32-11.32l-40-40a8,8,0,0,0-11.32,0l-40,40A8,8,0,0,0,93.66,77.66Z"/></svg>
                  Upload New Logo
                </button>
                <label class="checkbox-label"><input type="checkbox" name="remove_logo" value="1"> Remove uploaded logo and restore the default on save</label>
              </div>
            </div>
          </div>
        </section>

        <section class="panel-card"><div class="panel-head"><h3>Document QR verification</h3></div><div class="panel-body"><div class="form-group">
          <label for="portalPublicUrl">Portal address for document QR codes</label>
          <input type="url" id="portalPublicUrl" name="settings[portal_public_url]" maxlength="480" value="<?= htmlspecialchars($systemSettings['portal_public_url'] ?? '', ENT_QUOTES) ?>" placeholder="https://your-portal.example.com">
          <p class="field-hint">Use the full portal address, including its folder, that phones can open. For local use, enter the server's LAN address. Leave blank to use the current browser address. Saved document QR links remain unchanged.</p>
        </div></div></section>
        <!-- SMS Configuration -->
        <section class="panel-card">
          <div class="panel-head"><h3>SMS Configuration</h3></div>

          <div class="panel-body">
            <div class="form-group">
              <label for="smsProvider">SMS Provider</label>
              <?php $smsProvider = $systemSettings['sms_provider'] ?? 'httpSMS'; ?>
              <select id="smsProvider" name="settings[sms_provider]">
                <option <?= $smsProvider === 'httpSMS' ? 'selected' : '' ?>>httpSMS</option>
              </select>
            </div>
            <div class="form-grid-2">
              <div class="form-group">
                <label for="smsSenderName">SMS Sender Name</label>
                <input type="text" id="smsSenderName" name="settings[sms_sender_name]" maxlength="20" placeholder="BRGY LUNA" value="<?= htmlspecialchars($systemSettings['sms_sender_name'] ?? 'BRGY LUNA') ?>">
              </div>
              <div class="form-group">
                <label for="smsApiKey">httpSMS API Key</label>
                <input type="password" id="smsApiKey" name="settings[sms_api_key]" autocomplete="new-password" placeholder="<?= empty($systemSettings['sms_api_key']) ? 'Enter your httpSMS API key' : 'Leave blank to keep the current API key' ?>">
              </div>
            </div>
            <div class="form-grid-2">
              <div class="form-group">
                <label for="smsPhoneNumber">httpSMS Sender Number</label>
                <input type="tel" id="smsPhoneNumber" name="settings[sms_sender_number]" placeholder="+639XXXXXXXXX" value="<?= htmlspecialchars($systemSettings['sms_sender_number'] ?? '') ?>">
              </div>
            </div>
            <p class="field-hint">Use the mobile number connected to your httpSMS Android device, for example +639XXXXXXXXX.</p>
            <div class="form-group">
              <label class="checkbox-label">
                <input type="hidden" name="settings[sms_enabled]" value="0">
                <input type="checkbox" name="settings[sms_enabled]" value="1" <?= ($systemSettings['sms_enabled'] ?? '1') === '1' ? 'checked' : '' ?>>
                <span>Enable SMS Alerts</span>
              </label>
            </div>
          </div>
        </section>

        <section class="panel-card">
          <div class="panel-head"><h3>Document Templates</h3></div>
          <div class="panel-body">
            <p class="field-hint" style="margin:0">
              Document types and Word templates are managed in the staff Issue Documents page, so residents and staff stay synchronized.
            </p>
          </div>
        </section>

        <!-- Security Settings -->
        <section class="panel-card">
          <div class="panel-head"><h3>Security Settings</h3></div>
          <div class="panel-body">
            <div class="form-group">
              <label for="sessionTimeout">Session Timeout (minutes)</label>
              <input type="number" id="sessionTimeout" name="settings[session_timeout]" value="<?= htmlspecialchars($systemSettings['session_timeout'] ?? '30') ?>" min="10" max="240">
            </div>
            <p class="field-hint">Passwords must contain 8 to 72 bytes. Additional rules apply when creating or changing passwords.</p>
            <?php foreach (['password_uppercase' => 'Require uppercase letters', 'password_numbers' => 'Require numbers', 'password_special' => 'Require special characters', 'sms_2fa' => 'Require an SMS code at every sign-in', 'mobile_verification' => 'Verify mobile number at first sign-in and after number changes'] as $key => $label): ?>
            <div class="form-group"><label class="checkbox-label">
              <input type="hidden" name="settings[<?= $key ?>]" value="0">
              <input type="checkbox" name="settings[<?= $key ?>]" value="1" <?= ($systemSettings[$key] ?? '0') === '1' ? 'checked' : '' ?>>
              <span><?= htmlspecialchars($label) ?></span>
            </label></div>
            <?php endforeach; ?>
            <p class="field-hint">SMS verification requires access to the registered mobile number and a working httpSMS sender phone. New security settings apply to future sign-ins.</p>
          </div>
        </section>

        <!-- Actions -->
        <div class="settings-actions">
          <button type="submit" class="btn btn-primary">
            <svg width="16" height="16" viewBox="0 0 256 256" fill="currentColor" stroke="none" aria-hidden="true" data-system-icon="save" class="system-icon"><path d="M216,83.31V208a8,8,0,0,1-8,8H176V152a8,8,0,0,0-8-8H88a8,8,0,0,0-8,8v64H48a8,8,0,0,1-8-8V48a8,8,0,0,1,8-8H172.69a8,8,0,0,1,5.65,2.34l35.32,35.32A8,8,0,0,1,216,83.31Z" opacity="0.2"/><path d="M219.31,72,184,36.69A15.86,15.86,0,0,0,172.69,32H48A16,16,0,0,0,32,48V208a16,16,0,0,0,16,16H208a16,16,0,0,0,16-16V83.31A15.86,15.86,0,0,0,219.31,72ZM168,208H88V152h80Zm40,0H184V152a16,16,0,0,0-16-16H88a16,16,0,0,0-16,16v56H48V48H172.69L208,83.31ZM160,72a8,8,0,0,1-8,8H96a8,8,0,0,1,0-16h56A8,8,0,0,1,160,72Z"/></svg>
            Save Changes
          </button>
          <button type="reset" class="btn btn-outline">
            <svg width="16" height="16" viewBox="0 0 256 256" fill="currentColor" stroke="none" aria-hidden="true" data-system-icon="refresh-cw" class="system-icon"><path d="M216,128a88,88,0,1,1-88-88A88,88,0,0,1,216,128Z" opacity="0.2"/><path d="M224,48V96a8,8,0,0,1-8,8H168a8,8,0,0,1,0-16h28.69L182.06,73.37a79.56,79.56,0,0,0-56.13-23.43h-.45A79.52,79.52,0,0,0,69.59,72.71,8,8,0,0,1,58.41,61.27a96,96,0,0,1,135,.79L208,76.69V48a8,8,0,0,1,16,0ZM186.41,183.29a80,80,0,0,1-112.47-.66L59.31,168H88a8,8,0,0,0,0-16H40a8,8,0,0,0-8,8v48a8,8,0,0,0,16,0V179.31l14.63,14.63A95.43,95.43,0,0,0,130,222.06h.53a95.36,95.36,0,0,0,67.07-27.33,8,8,0,0,0-11.18-11.44Z"/></svg>
            Reset
          </button>
        </div>
      </form>

<style>
.settings-form {
  display: grid;
  gap: 1.5rem;
  width: 100%;
  min-width: 0;
}

.settings-form .form-grid-2 {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 1.5rem;
}

.form-group {
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.form-group label {
  font-size: 0.9rem;
  font-weight: 600;
  color: var(--text);
}

.form-group input[type="text"],
.form-group input[type="tel"],
.form-group input[type="tel"],
.form-group input[type="password"],
.form-group input[type="number"],
.form-group select {
  width: 100%;
  min-width: 0;
  padding: 0.75rem 0.9rem;
  border: 1px solid var(--border);
  border-radius: var(--radius);
  font-size: 0.9rem;
  background: var(--white);
  color: var(--text);
  font-family: inherit;
}

.form-group input:focus,
.form-group select:focus {
  outline: none;
  border-color: var(--green-600);
  box-shadow: 0 0 0 3px rgba(22, 163, 74, 0.12);
}

.checkbox-label {
  display: flex;
  align-items: flex-start;
  gap: 0.65rem;
  cursor: pointer;
  font-size: 0.9rem;
  color: var(--text);
}

.checkbox-label input[type="checkbox"] {
  width: 18px;
  height: 18px;
  margin-top: 2px;
  accent-color: var(--green-600);
  cursor: pointer;
  flex-shrink: 0;
}

.logo-upload {
  display: grid;
  grid-template-columns: 120px minmax(0, 1fr);
  gap: 2rem;
  align-items: flex-start;
}

.logo-preview {
  width: 120px;
  height: 120px;
  border: 2px solid var(--border);
  border-radius: var(--radius-lg);
  display: grid;
  place-items: center;
  background: var(--green-50);
}

.logo-controls {
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.settings-actions {
  display: flex;
  gap: 1rem;
  margin-top: 1rem;
  flex-wrap: wrap;
}

.settings-form .btn-text {
  background: transparent;
  color: var(--text-muted);
  font-weight: 500;
  padding: 0.65rem 1rem;
}

.settings-form .btn-text:hover {
  color: var(--text);
  background: var(--green-50);
}

.logo-controls input[type="file"] { width: 100%; min-width: 0; }
.logo-controls .btn { align-self: flex-start; }
.logo-preview img { object-fit: contain; }
.settings-form .panel-body { display: grid; gap: 1rem; }

@media (max-width: 900px) {
  .settings-form .form-grid-2 {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 640px) {
  .settings-form .form-group input,
  .settings-form .form-group select { font-size: 16px; }
  .logo-upload {
    grid-template-columns: 1fr;
  }

  .settings-actions {
    flex-direction: column;
  }
}
</style>
