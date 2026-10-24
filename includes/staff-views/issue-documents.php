<?php
$selectedRequestId = trim($_GET['request'] ?? '');
$issueMode = $selectedRequestId !== '' || ($_GET['mode'] ?? '') === 'resident' ? 'resident' : 'walkin';
$selectedRequest = null;
foreach ($staffRequests as $request) {
  if (($request['id'] ?? '') === $selectedRequestId && !in_array($request['status'] ?? '', ['Ready for Pickup', 'Released', 'Rejected'], true)) {
    $selectedRequest = $request;
    break;
  }
}

$selectedResident = $issueMode === 'walkin' ? ['id' => '__walkin__', 'name' => '', 'address' => '', 'age' => '', 'gender' => '', 'civil_status' => ''] : null;
if ($selectedRequest) {
  foreach ($residentRecords as $resident) {
    if (!empty($selectedRequest['resident_code']) && $resident['id'] === $selectedRequest['resident_code']) {
      $selectedResident = $resident;
      break;
    }
  }
}
$selectedDocumentType = staff_preferred_document_type_label($selectedRequest['type'] ?? ($documentTypes[0] ?? 'Barangay Certification'));
$documentPurpose = $selectedRequest['purpose'] ?? 'Barangay service request';
$dateIssued = date('Y-m-d');
$barangayCaptain = 'Hon. Barangay Captain';
$barangayLocation = 'Barangay Luna, Surigao City, Surigao del Norte, Philippines';
$documentNumber = 'BL-' . date('Ymd-His') . '-' . strtoupper(bin2hex(random_bytes(3)));
$requestBeneficiaryName = $selectedRequest['beneficiary_name'] ?? '';
$requestBeneficiaryRelationship = $selectedRequest['beneficiary_relationship'] ?? '';
$requestResidencyYears = $selectedRequest['residency_years'] ?? '';
$requestPartnerName = $selectedRequest['partner_name'] ?? '';
$requestCohabitationStartDate = $selectedRequest['cohabitation_start_date'] ?? '';
$selectedDocumentFee = $selectedRequest
  ? (float) ($selectedRequest['fee_amount'] ?? 0)
  : (float) ($documentFees[barangay_document_fee_key($selectedDocumentType)] ?? 0);
$activeTemplate = $documentTemplates[0] ?? ['year' => date('Y'), 'content' => ''];
$templateFallback = 'This is to certify that {{resident_name}}, {{age}} years old, {{civil_status}}, residing at {{resident_address}}, {{barangay_name}}, is issued this document for {{purpose}} on {{date_issued}}. Document No. {{document_number}}.';
$templatesByType = [];
foreach ($documentTemplates as $template) {
  $type = $template['type'] ?? '';
  if ($type !== '' && !isset($templatesByType[$type])) {
    $previewContent = trim((string) ($template['preview_content'] ?? ''));
    $manualContent = trim((string) ($template['content'] ?? ''));
    $templatesByType[$type] = [
      'year' => $template['year'] ?? date('Y'),
      'content' => $previewContent !== '' ? $previewContent : ($manualContent !== '' ? $manualContent : $templateFallback),
      'html' => (string) ($template['preview_html'] ?? ''),
      'file' => $template['template_file_name'] ?? '',
      'status' => $template['status'] ?? '',
    ];
  }
}
$activeTemplate = $templatesByType[$selectedDocumentType] ?? ['year' => date('Y'), 'content' => $templateFallback, 'html' => '', 'file' => '', 'status' => ''];
$residentDocumentData = array_map(static function (array $resident): array {
  return [
    'id' => $resident['id'] ?? '',
    'name' => $resident['name'] ?? '',
    'address' => $resident['address'] ?? '',
    'age' => (int) ($resident['age'] ?? 0),
    'gender' => $resident['gender'] ?? '',
    'civil_status' => $resident['civil_status'] ?? '',
  ];
}, $selectedRequest && $selectedResident ? [$selectedResident] : []);
$documentTemplateData = $templatesByType;
$formatDateForDisplay = static function (string $date): string {
  $timestamp = strtotime($date);
  return $timestamp ? date('F j, Y', $timestamp) : $date;
};
if (!function_exists('staff_issue_view_ordinal_day')) {
  function staff_issue_view_ordinal_day(int $day): string {
    if ($day >= 11 && $day <= 13) {
      return $day . 'th';
    }
    return $day . match ($day % 10) {
      1 => 'st',
      2 => 'nd',
      3 => 'rd',
      default => 'th',
    };
  }
}
if (!function_exists('staff_issue_view_number_words')) {
  function staff_issue_view_number_words(int $number): string {
    return staff_number_to_words($number);
  }
}
$renderDocumentTemplate = static function (string $template, array $resident, string $purpose, string $date, string $captain, string $number, string $beneficiaryName = '', string $beneficiaryRelationship = '', int $residencyYears = 0, string $partnerName = '', string $cohabitationStartDate = '') use ($barangayLocation): string {
  $timestamp = strtotime($date) ?: time();
  $residentName = $resident['name'] ?? '';
  $beneficiaryName = trim($beneficiaryName);
  $beneficiaryRelationship = trim($beneficiaryRelationship);
  $residencyYears = max(0, $residencyYears);
  $residencyYearsWords = staff_issue_view_number_words($residencyYears);
  $partnerName = trim($partnerName);
  $cohabitationTimestamp = strtotime($cohabitationStartDate) ?: $timestamp;
  $cohabitationYears = 0;
  if ($cohabitationStartDate !== '' && strtotime($cohabitationStartDate) !== false) {
    $start = new DateTime(date('Y-m-d', $cohabitationTimestamp));
    $issued = new DateTime(date('Y-m-d', $timestamp));
    $cohabitationYears = max(0, (int) $start->diff($issued)->y);
  }
  $cohabitationYearsWords = staff_issue_view_number_words($cohabitationYears);
  $gender = strtolower(trim((string) ($resident['gender'] ?? '')));
  $pronouns = match ($gender) {
    'male' => ['He', 'he', 'him', 'his'],
    'female' => ['She', 'she', 'her', 'her'],
    default => ['They', 'they', 'them', 'their'],
  };
  return strtr($template, [
    '{{resident_name}}' => function_exists('mb_strtoupper') ? mb_strtoupper($residentName, 'UTF-8') : strtoupper($residentName),
    '{{resident_name_proper}}' => $residentName,
    '{{resident_address}}' => $resident['address'] ?? '',
    '{{age}}' => (string) (int) ($resident['age'] ?? 0),
    '{{gender}}' => $resident['gender'] ?? '',
    '{{civil_status}}' => $resident['civil_status'] ?? '',
    '{{pronoun_subject}}' => $pronouns[0],
    '{{pronoun_subject_lower}}' => $pronouns[1],
    '{{pronoun_object}}' => $pronouns[2],
    '{{pronoun_possessive}}' => $pronouns[3],
    '{{beneficiary_name}}' => function_exists('mb_strtoupper') ? mb_strtoupper($beneficiaryName, 'UTF-8') : strtoupper($beneficiaryName),
    '{{beneficiary_name_proper}}' => $beneficiaryName,
    '{{beneficiary_relationship}}' => strtolower($beneficiaryRelationship),
    '{{beneficiary_relationship_upper}}' => function_exists('mb_strtoupper') ? mb_strtoupper($beneficiaryRelationship, 'UTF-8') : strtoupper($beneficiaryRelationship),
    '{{residency_years}}' => (string) $residencyYears,
    '{{residency_years_words}}' => $residencyYearsWords,
    '{{residency_years_text}}' => $residencyYearsWords . ' [' . $residencyYears . ']',
    '{{partner_name}}' => function_exists('mb_strtoupper') ? mb_strtoupper($partnerName, 'UTF-8') : strtoupper($partnerName),
    '{{partner_name_proper}}' => $partnerName,
    '{{cohabitation_start_date}}' => date('F j, Y', $cohabitationTimestamp),
    '{{cohabitation_start_day}}' => date('j', $cohabitationTimestamp),
    '{{cohabitation_start_day_ordinal}}' => staff_issue_view_ordinal_day((int) date('j', $cohabitationTimestamp)),
    '{{cohabitation_start_month}}' => date('F', $cohabitationTimestamp),
    '{{cohabitation_start_month_upper}}' => strtoupper(date('F', $cohabitationTimestamp)),
    '{{cohabitation_start_year}}' => date('Y', $cohabitationTimestamp),
    '{{cohabitation_years}}' => (string) $cohabitationYears,
    '{{cohabitation_years_words}}' => $cohabitationYearsWords,
    '{{cohabitation_years_text}}' => $cohabitationYearsWords . ' (' . $cohabitationYears . ')',
    '{{purpose}}' => function_exists('mb_strtoupper') ? mb_strtoupper($purpose, 'UTF-8') : strtoupper($purpose),
    '{{date_issued}}' => date('F j, Y', $timestamp),
    '{{date_day}}' => date('j', $timestamp),
    '{{date_day_ordinal}}' => staff_issue_view_ordinal_day((int) date('j', $timestamp)),
    '{{date_month}}' => date('F', $timestamp),
    '{{date_month_upper}}' => strtoupper(date('F', $timestamp)),
    '{{date_year}}' => date('Y', $timestamp),
    '{{barangay_name}}' => $barangayLocation,
    '{{barangay_captain}}' => $captain,
    '{{document_number}}' => $number,
  ]);
};
$previewBody = $selectedResident
  ? $renderDocumentTemplate($activeTemplate['content'], $selectedResident, $documentPurpose, $formatDateForDisplay($dateIssued), $barangayCaptain, $documentNumber)
  : '';
if (!function_exists('staff_issue_preview_lines')) {
  function staff_issue_preview_lines(string $text): array {
    $text = str_replace("\u{00a0}", ' ', $text);
    $text = preg_replace('/[ \t]+/', ' ', $text) ?? $text;
    $text = preg_replace('/(TO WHOM IT MAY CONCERN:)\s*/i', "$1\n", $text) ?? $text;
    $text = preg_replace('/([,.;:!?])(?=\S)/', '$1 ', $text) ?? $text;
    $lines = [];
    foreach (preg_split('/\R+/', trim($text)) as $line) {
      $line = trim($line);
      if ($line !== '') {
        $lines[] = $line;
      }
    }
    return $lines;
  }
}
?>

<div class="page-intro">
  <h2>Issue Documents</h2>
  <p>Review details, check requirements, record payment, and generate the document in one place. Walk-ins do not need a portal account.</p>
</div>

<div class="toolbar" id="template-management">
  <div>
    <button type="button" class="btn btn-outline" data-scroll-document-preview>Preview document</button>
  </div>
  <button type="button" class="btn btn-outline" data-modal-open="documentRequestsModal" data-nav-view="<?= staff_portal_page() === 'dashboard.php' ? 'documents' : 'staff-document-requests' ?>">Document Requests <span class="nav-count" data-notification-side-count hidden></span></button>
  <?php if ($canManageTemplates): ?>
  <button type="button" class="btn btn-outline" data-modal-open="templateManagementModal">Template Management</button>
  <?php endif; ?>
</div>

<div id="documentRequestsModal" class="modal" aria-hidden="true" role="dialog" aria-labelledby="documentRequestsTitle">
  <div class="modal-backdrop" data-modal-close></div>
  <div class="modal-panel document-requests-panel">
    <header class="modal-header"><h2 id="documentRequestsTitle">Document Requests</h2><button type="button" class="modal-close" data-modal-close aria-label="Close">&times;</button></header>
    <div class="modal-body">
      <?php $requestsEmbedded = true; require __DIR__ . '/staff-document-requests.php'; unset($requestsEmbedded); ?>
    </div>
  </div>
</div>

<div class="form-grid-2" style="margin-bottom: 20px">
  <div class="field">
    <label for="issueWorkflow">Issue documents</label>
    <select id="issueWorkflow" onchange="window.location.href=this.value">
      <option value="<?= htmlspecialchars(staff_view_url('issue-documents', ['mode' => 'walkin'])) ?>" <?= $issueMode === 'walkin' ? 'selected' : '' ?>>Walk-in Documents</option>
      <option value="<?= htmlspecialchars(staff_view_url('issue-documents', ['mode' => 'resident'])) ?>" <?= $issueMode === 'resident' ? 'selected' : '' ?>>Resident Requests</option>
    </select>
  </div>
  <?php if ($issueMode === 'resident'): ?>
  <form action="staff-actions.php" method="post" class="field">
    <input type="hidden" name="action" value="document_request_event">
    <input type="hidden" name="event" value="prepare">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['document_request_csrf']) ?>">
    <label for="issueRequest">Resident request</label>
    <select id="issueRequest" name="id" onchange="if(this.value) this.form.requestSubmit()">
      <option value="">Select a request</option>
      <?php foreach ($staffRequests as $request): ?>
      <?php if (in_array($request['status'] ?? '', ['Ready for Pickup', 'Released', 'Rejected'], true)) continue; ?>
      <option value="<?= htmlspecialchars($request['id']) ?>" <?= $selectedRequestId === $request['id'] ? 'selected' : '' ?>><?= htmlspecialchars($request['id'] . ' - ' . $request['resident'] . ' - ' . $request['type']) ?></option>
      <?php endforeach; ?>
    </select>
  </form>
  <?php endif; ?>
</div>

<div class="grid-2 <?= $issueMode === 'resident' ? 'request-review-layout' : '' ?>">
  <section class="panel-card <?= $issueMode === 'resident' ? 'request-confirmation-panel' : '' ?>">
    <div class="panel-head">
      <h3><?= $issueMode === 'resident' ? 'Request details' : 'Document applicant' ?></h3>
      <span class="badge badge-active" data-template-year-badge>Template year: <?= htmlspecialchars((string) $activeTemplate['year']) ?></span>
    </div>
    <div class="panel-body">
      <?php if ($selectedResident): ?>
      <?php if ($selectedRequest): ?>
      <div class="request-confirmation">
        <span class="request-confirmation-reference"><?= htmlspecialchars($selectedRequest['id']) ?></span>
        <h4><?= htmlspecialchars($selectedResident['name']) ?></h4>
        <p class="request-confirmation-address"><?= htmlspecialchars($selectedResident['address']) ?></p>
        <dl>
          <div><dt>Document</dt><dd><?= htmlspecialchars($selectedDocumentType) ?></dd></div>
          <div><dt>Purpose</dt><dd><?= htmlspecialchars($documentPurpose) ?></dd></div>
          <div class="request-confirmation-pair"><div><dt>Copies</dt><dd class="request-confirmation-copies"><?= (int) ($selectedRequest['copies'] ?? 1) ?></dd></div><div><dt>Total fee</dt><dd>PHP <?= number_format($selectedDocumentFee, 2) ?></dd></div></div>
          <div class="request-confirmation-demographics">
            <div><dt>Age</dt><dd><?= htmlspecialchars((string) ($selectedResident['age'] ?? '-')) ?></dd></div>
            <div><dt>Gender</dt><dd><?= htmlspecialchars(($selectedResident['gender'] ?? '-') ?: '-') ?></dd></div>
            <div><dt>Civil status</dt><dd><?= htmlspecialchars($selectedResident['civil_status'] ?: '-') ?></dd></div>
          </div>
          <?php
          $confirmationExtras = [];
          if (stripos($selectedDocumentType, 'indigency') !== false || stripos($selectedDocumentType, 'barangay certification') !== false) $confirmationExtras['Years as resident'] = $requestResidencyYears;
          if (stripos($selectedDocumentType, 'indigency') !== false) {
            $confirmationExtras['Beneficiary'] = $requestBeneficiaryName;
            $confirmationExtras['Relationship'] = $requestBeneficiaryRelationship;
          }
          if (stripos($selectedDocumentType, 'cohabitation') !== false) {
            $confirmationExtras['Partner'] = $requestPartnerName;
            $confirmationExtras['Living together since'] = $requestCohabitationStartDate;
          }
          $confirmationExtras['Contact'] = $selectedRequest['contact_mobile'] ?? '';
          $confirmationExtras['Remarks'] = $selectedRequest['remarks'] ?? '';
          foreach ($confirmationExtras as $label => $value): if ((string) $value === '') continue;
          ?>
          <div><dt><?= htmlspecialchars($label) ?></dt><dd><?= htmlspecialchars((string) $value) ?></dd></div>
          <?php endforeach; ?>
          <div><dt>Date issued</dt><dd><?= htmlspecialchars($dateIssued) ?></dd></div>
          <div><dt>Control number</dt><dd><?= htmlspecialchars($documentNumber) ?></dd></div>
        </dl>
      </div>
      <?php endif; ?>
      <form id="issueDocumentForm" action="staff-actions.php" method="post" class="form-grid" data-issue-document-form <?= $selectedRequest ? 'hidden style="display:none"' : '' ?>>
        <input type="hidden" name="action" value="issue_document">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['document_request_csrf']) ?>">
        <input type="hidden" name="after_issue" value="print">
        <input type="hidden" name="redirect" value="issue-documents">
        <?php if ($selectedRequest): ?>
        <input type="hidden" name="request_code" value="<?= htmlspecialchars($selectedRequest['id']) ?>">
        <?php endif; ?>
        <input type="hidden" name="resident_code" value="<?= htmlspecialchars($selectedRequest ? ($selectedResident['id'] ?? '') : '__walkin__') ?>" data-autofill-resident>
        <?php if (!$selectedRequest): ?>
        <div class="field" data-issue-resident-lookup>
          <label for="issueResidentQr">Search resident (optional)</label>
          <div class="issue-lookup-row">
            <div class="issue-resident-search">
              <input id="issueResidentQr" type="search" maxlength="150" autocomplete="off" spellcheck="false" placeholder="Search name or Resident ID" aria-describedby="issueResidentQrHint" aria-controls="issueResidentSuggestions" aria-expanded="false">
              <div id="issueResidentSuggestions" class="issue-resident-results" data-issue-resident-results hidden role="group" aria-label="Matching registered residents"></div>
            </div>
            <button type="button" class="btn btn-primary" data-issue-find-resident>Search</button>
          </div>
          <p id="issueResidentQrHint" class="field-hint">Search if the resident has an account. Otherwise, leave this empty and enter their details below.</p>
          <div class="issue-qr-capture" data-issue-qr-capture>
            <div class="issue-qr-actions">
              <button type="button" class="btn btn-primary" data-issue-camera-start>Scan QR</button>
              <button type="button" class="btn btn-outline" data-issue-photo-open>Read QR photo</button>
              <input type="file" accept="image/png,image/jpeg,image/webp" data-issue-photo-input hidden aria-label="Choose resident QR photo">
            </div>
            <div class="issue-qr-camera" data-issue-camera-panel hidden>
              <video muted playsinline autoplay aria-label="Resident QR camera preview"></video>
              <button type="button" class="btn btn-outline" data-issue-camera-stop>Stop camera</button>
            </div>
            <p class="field-hint" role="status" aria-live="polite" data-issue-camera-status></p>
          </div>
          <p role="status" aria-live="polite" data-issue-lookup-status></p>
        </div>
        <?php endif; ?>
        <?php if ($issueMode === 'walkin'): ?>
        <div class="form-grid-2" data-walkin-fields>
          <div class="field"><label for="walkinName">Resident full name</label><input id="walkinName" name="walkin_name" maxlength="150" required></div>
          <div class="field"><label for="walkinAddress">Address</label><input id="walkinAddress" name="walkin_address" maxlength="500" required></div>
          <div class="field"><label for="walkinAge">Age</label><input id="walkinAge" name="walkin_age" type="number" min="0" max="130" step="1" required></div>
          <div class="field"><label for="walkinGender">Gender</label><select id="walkinGender" name="walkin_gender" required><option value="">Select gender</option><option>Male</option><option>Female</option></select></div>
          <div class="field"><label for="walkinCivil">Civil status</label><select id="walkinCivil" name="walkin_civil_status" required><option value="">Select civil status</option><option>Single</option><option>Married</option><option>Widowed</option><option>Separated</option></select></div>
          <div class="field"><label for="walkinMobile">Mobile number (optional)</label><input id="walkinMobile" name="walkin_mobile" type="tel" maxlength="13" pattern="(09[0-9]{9}|\+639[0-9]{9})"></div>
        </div>
        <?php endif; ?>
        <div class="form-grid-2">
          <div class="field">
            <label>Document type</label>
            <select name="document_type" required data-autofill-document-type <?= $selectedRequest ? 'disabled' : '' ?>>
              <?php if ($selectedRequest && !in_array($selectedDocumentType, $documentTypes, true)): ?>
              <option selected><?= htmlspecialchars($selectedDocumentType) ?></option>
              <?php endif; ?>
              <?php foreach ($documentTypes as $type): ?>
              <option <?= $type === $selectedDocumentType ? 'selected' : '' ?>><?= htmlspecialchars($type) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field">
            <label>Purpose</label>
            <input type="text" name="purpose" value="<?= htmlspecialchars($documentPurpose) ?>" placeholder="Employment, school, business permit..." data-autofill-purpose <?= $selectedRequest ? 'readonly' : '' ?>>
          </div>
        </div>
        <div class="form-grid-2">
          <div class="field residency-years-fields" data-residency-years-fields hidden>
            <label>Years as resident</label>
            <input type="number" name="residency_years" value="<?= htmlspecialchars((string) $requestResidencyYears) ?>" min="0" step="1" placeholder="8" data-autofill-residency-years>
            <p class="field-hint">Used for text like eight [8] years.</p>
          </div>
          <div class="field">
            <label for="issueCopies">Number of copies</label>
            <input id="issueCopies" type="number" name="copies" min="1" max="100" step="1" value="<?= (int) ($selectedRequest['copies'] ?? 1) ?>" required <?= $selectedRequest ? 'readonly' : '' ?>>
            <p class="field-hint" data-issue-unit-fee></p>
          </div>
        </div>
        <div class="field">
          <label>Total fee</label>
          <input type="text" value="PHP <?= number_format($selectedDocumentFee, 2) ?>" readonly data-autofill-document-fee>
          <p class="field-hint">Fee per copy multiplied by the requested copies. Record payment below or collect it before release.</p>
        </div>
        <input type="hidden" name="date_issued" value="<?= htmlspecialchars($dateIssued) ?>" data-autofill-date>
        <input type="hidden" name="document_number" value="<?= htmlspecialchars($documentNumber) ?>" data-autofill-document-number>
        <div class="form-grid-2 indigency-fields" data-indigency-fields hidden>
          <div class="field">
            <label>Beneficiary name</label>
            <input type="text" name="beneficiary_name" value="<?= htmlspecialchars((string) $requestBeneficiaryName) ?>" placeholder="Enter beneficiary name" data-autofill-beneficiary-name>
            <p class="field-hint">Use this when the Indigency Certificate is requested for a child or dependent.</p>
          </div>
          <div class="field">
            <label>Relationship</label>
            <input type="text" name="beneficiary_relationship" value="<?= htmlspecialchars((string) $requestBeneficiaryRelationship) ?>" placeholder="son, daughter, spouse..." data-autofill-beneficiary-relationship><p class="field-hint">Relationship to the resident, such as son or daughter. The resident's gender comes from their own details above.</p>
          </div>

        </div>
        <div class="form-grid-2 cohabitation-fields" data-cohabitation-fields hidden>
          <div class="field">
            <label>Partner name</label>
            <input type="text" name="partner_name" value="<?= htmlspecialchars((string) $requestPartnerName) ?>" placeholder="Enter partner name" data-autofill-partner-name>
          </div>
          <div class="field">
            <label for="issueCohabitationStart">Living together since</label>
            <div class="calendar-date-field">
              <input id="issueCohabitationStart" type="date" name="cohabitation_start_date" value="<?= htmlspecialchars((string) $requestCohabitationStartDate) ?>" data-autofill-cohabitation-start>
              <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 5h16v16H4V5Zm4-3v6m8-6v6M4 11h16m-12 4h2m4 0h2m-8 3h2" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </div>
          </div>
        </div>
        <div class="auto-fill-grid issuance-resident-summary" role="group" aria-label="Selected resident details" data-resident-summary <?= $issueMode === 'walkin' ? 'hidden style="display:none"' : '' ?>>
          <div class="resident-detail-wide"><span>Resident full name</span><strong data-autofill-name><?= htmlspecialchars($selectedResident['name']) ?></strong></div>
          <div class="resident-detail-wide"><span>Address</span><strong data-autofill-address><?= htmlspecialchars($selectedResident['address']) ?></strong></div>
          <div class="resident-detail-age"><span>Age</span><strong data-autofill-age><?= (int) ($selectedResident['age'] ?? 0) ?></strong></div>
          <div class="resident-detail-gender"><span>Gender</span><strong data-autofill-gender><?= htmlspecialchars($selectedResident['gender'] ?? '') ?></strong></div>
          <div class="resident-detail-civil"><span>Civil status</span><strong data-autofill-civil-status><?= htmlspecialchars($selectedResident['civil_status']) ?></strong></div>
          <div class="resident-detail-full"><span>Location</span><strong><?= htmlspecialchars($barangayLocation) ?></strong></div>
          <div class="resident-detail-full"><span>Barangay Captain</span><strong><?= htmlspecialchars($barangayCaptain) ?></strong></div>
        </div>
      </form>
      <?php include __DIR__ . '/../issuance-review-controls.php'; ?>
      <?php else: ?>
      <p><?= $issueMode === 'resident' ? 'Select an available resident request above.' : 'Enter the walk-in applicant details to continue.' ?></p>
      <?php endif; ?>
    </div>
  </section>

  <div class="issuance-preview-column">
  <section class="panel-card">
    <div class="panel-head"><h3>Generated document preview</h3></div>
    <div class="panel-body">
      <?php if ($selectedResident): ?>
      <div class="document-preview document-preview--official" data-document-preview>
        <strong>Republic of the Philippines</strong>
        <span><?= htmlspecialchars($barangayLocation) ?></span>
        <h3 data-preview-document-type><?= htmlspecialchars($selectedDocumentType) ?></h3>
        <p data-preview-document-number>Document No. <?= htmlspecialchars($documentNumber) ?></p>
        <div class="document-preview-body" data-preview-body>
          <?php foreach (staff_issue_preview_lines($previewBody) as $line): ?>
          <p class="document-preview-paragraph"><?= htmlspecialchars($line) ?></p>
          <?php endforeach; ?>
        </div>
        <div class="word-preview-shell" data-word-preview-shell hidden>
          <iframe title="Microsoft Word document preview" data-word-preview-frame></iframe>
          <p class="field-hint" data-word-preview-status></p>
        </div>
        <p class="signature-line"><?= htmlspecialchars($barangayCaptain) ?><br><span>Barangay Captain</span></p>
      </div>
      <?php else: ?>
      <p><?= $issueMode === 'resident' ? 'Select a request to preview its document.' : 'Enter the applicant details and choose a document type to preview.' ?></p>
      <?php endif; ?>
    </div>
  </section>
  <?php include __DIR__ . '/../issuance-prepared-documents.php'; ?>
  </div>
</div>
<?php include __DIR__ . '/../issuance-print-modal.php'; ?>

<?php if ($selectedResident): ?>
<script type="application/json" id="issueDocumentResidentsData"><?= json_encode($residentDocumentData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<script type="application/json" id="issueDocumentTemplatesData"><?= json_encode($documentTemplateData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<script type="application/json" id="issueDocumentFeesData"><?= json_encode($documentFees, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<script>
(function () {
  'use strict';

  const form = document.querySelector('[data-issue-document-form]');
  const preview = document.querySelector('[data-document-preview]');
  if (!form || !preview) return;

  document.querySelectorAll('[data-scroll-document-preview]').forEach(function (button) {
    button.addEventListener('click', function () {
      preview.scrollIntoView({ behavior: 'smooth', block: 'start' });
      preview.classList.add('is-preview-focused');
      window.setTimeout(function () {
        preview.classList.remove('is-preview-focused');
      }, 1200);
    });
  });

  const residentDataNode = document.getElementById('issueDocumentResidentsData');
  const templateDataNode = document.getElementById('issueDocumentTemplatesData');
  const documentFeesNode = document.getElementById('issueDocumentFeesData');
  const residentData = JSON.parse((residentDataNode && residentDataNode.textContent) || '[]');
  const templateData = JSON.parse((templateDataNode && templateDataNode.textContent) || '{}');
  const documentFees = JSON.parse((documentFeesNode && documentFeesNode.textContent) || '{}');
  const requestFee = <?= $selectedRequest ? json_encode($selectedDocumentFee) : 'null' ?>;
  const copiesField = form.querySelector('[name="copies"]');
  const savedUnitFee = <?= $selectedRequest ? json_encode((float) ($selectedRequest['unit_fee'] ?? $selectedDocumentFee)) : 'null' ?>;
  if (requestFee !== null) {
    ['beneficiary_name', 'beneficiary_relationship', 'residency_years', 'partner_name', 'cohabitation_start_date'].forEach(function (name) {
      const field = form.elements.namedItem(name);
      if (field.tagName === 'SELECT') field.disabled = true;
      else field.readOnly = true;
    });
  }
  copiesField.addEventListener('input', updateAutofill);
  const fallbackTemplate = <?= json_encode($templateFallback, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
  const barangayCaptain = <?= json_encode($barangayCaptain, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
  const barangayName = <?= json_encode($barangayLocation) ?>;
  let lastResidentId = '';
  let wordPreviewTimer = null;
  let wordPreviewRequestId = 0;
  let wordPreviewPending = null;
  let wordPreviewRunning = false;
  let previewDragging = false;
  let previewDragStartX = 0;
  let previewDragStartY = 0;
  let previewScrollStartLeft = 0;
  let previewScrollStartTop = 0;
  let previewZoom = 1;

  const fields = {
    resident: form.querySelector('[data-autofill-resident]'),
    type: form.querySelector('[data-autofill-document-type]'),
    fee: form.querySelector('[data-autofill-document-fee]'),
    purpose: form.querySelector('[data-autofill-purpose]'),
    date: form.querySelector('[data-autofill-date]'),
    number: form.querySelector('[data-autofill-document-number]'),
    beneficiaryName: form.querySelector('[data-autofill-beneficiary-name]'),
    beneficiaryRelationship: form.querySelector('[data-autofill-beneficiary-relationship]'),
    residencyYears: form.querySelector('[data-autofill-residency-years]'),
    partnerName: form.querySelector('[data-autofill-partner-name]'),
    cohabitationStart: form.querySelector('[data-autofill-cohabitation-start]'),
    indigencyFields: form.querySelector('[data-indigency-fields]'),
    residencyYearsFields: form.querySelector('[data-residency-years-fields]'),
    cohabitationFields: form.querySelector('[data-cohabitation-fields]'),
    name: form.querySelector('[data-autofill-name]'),
    address: form.querySelector('[data-autofill-address]'),
    age: form.querySelector('[data-autofill-age]'),
    gender: form.querySelector('[data-autofill-gender]'),
    civilStatus: form.querySelector('[data-autofill-civil-status]'),
    previewType: preview.querySelector('[data-preview-document-type]'),
    previewNumber: preview.querySelector('[data-preview-document-number]'),
    previewBody: preview.querySelector('[data-preview-body]'),
    wordPreviewShell: preview.querySelector('[data-word-preview-shell]'),
    wordPreviewFrame: preview.querySelector('[data-word-preview-frame]'),
    wordPreviewStatus: preview.querySelector('[data-word-preview-status]'),
    templateYearBadge: document.querySelector('[data-template-year-badge]'),
  };

  function formatDate(value) {
    if (!value) return '';
    const parts = value.split('-').map(Number);
    if (parts.length !== 3) return value;
    const date = new Date(parts[0], parts[1] - 1, parts[2]);
    return date.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
  }

  function dateParts(value) {
    const parts = value ? value.split('-').map(Number) : [];
    const date = parts.length === 3 ? new Date(parts[0], parts[1] - 1, parts[2]) : new Date();
    const day = date.getDate();
    const suffix = day >= 11 && day <= 13 ? 'th' : ({ 1: 'st', 2: 'nd', 3: 'rd' }[day % 10] || 'th');
    const month = date.toLocaleDateString('en-US', { month: 'long' });
    return {
      day: String(day),
      dayOrdinal: `${day}${suffix}`,
      month,
      monthUpper: month.toUpperCase(),
      year: String(date.getFullYear()),
      full: date.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }),
    };
  }

  function residentById(id) {
    if (id === '__walkin__') {
      const value = (name) => form.elements.namedItem('walkin_' + name).value.trim();
      return {id, name: value('name'), address: value('address'), age: value('age'), gender: value('gender'), civil_status: value('civil_status')};
    }
    return residentData.find((resident) => resident.id === id) || {};
  }

  function pronounsForGender(gender) {
    const normalized = String(gender || '').trim().toLowerCase();
    if (normalized === 'male') return { subject: 'He', subjectLower: 'he', object: 'him', possessive: 'his' };
    if (normalized === 'female') return { subject: 'She', subjectLower: 'she', object: 'her', possessive: 'her' };
    return { subject: 'They', subjectLower: 'they', object: 'them', possessive: 'their' };
  }

  function numberToWords(number) {
    const value = Math.max(0, parseInt(number, 10) || 0);
    const ones = ['zero', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten', 'eleven', 'twelve', 'thirteen', 'fourteen', 'fifteen', 'sixteen', 'seventeen', 'eighteen', 'nineteen'];
    const tens = { 20: 'twenty', 30: 'thirty', 40: 'forty', 50: 'fifty', 60: 'sixty', 70: 'seventy', 80: 'eighty', 90: 'ninety' };
    if (value < 20) return ones[value];
    if (value < 100) {
      const ten = Math.floor(value / 10) * 10;
      const rest = value % 10;
      return tens[ten] + (rest ? '-' + ones[rest] : '');
    }
    if (value < 1000) {
      const hundred = Math.floor(value / 100);
      const rest = value % 100;
      return ones[hundred] + ' hundred' + (rest ? ' ' + numberToWords(rest) : '');
    }
    return String(value);
  }

  function isIndigencyType(type) {
    return String(type || '').toLowerCase().includes('indigency');
  }

  function normalizedDocumentType(type) {
    return String(type || '').trim().toLowerCase().replace(/\s+/g, ' ');
  }

  function isBarangayCertificationType(type) {
    const value = normalizedDocumentType(type);
    return value === 'barangay certification' || value === 'certificate of barangay certification';
  }

  function isCohabitationType(type) {
    return normalizedDocumentType(type).includes('cohabitation');
  }

  function usesResidencyYears(type) {
    return isIndigencyType(type) || isBarangayCertificationType(type);
  }

  function toggleSection(section, show) {
    if (!section) return;
    section.hidden = !show;
    section.style.display = show ? '' : 'none';
  }


  function escapeHtml(value) {
    return String(value ?? '')
      .replaceAll('&', '&amp;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;')
      .replaceAll('"', '&quot;')
      .replaceAll("'", '&#039;');
  }

  function renderTemplate(template, resident, purpose, date, number, beneficiaryName, beneficiaryRelationship, residencyYears, partnerName, cohabitationStartDate, escapeValues) {
    const issued = dateParts(fields.date.value);
    const residentName = resident.name || '';
    const beneficiary = String(beneficiaryName || '').trim();
    const relationship = String(beneficiaryRelationship || '').trim();
    const years = Math.max(0, parseInt(residencyYears, 10) || 0);
    const yearsWords = numberToWords(years);
    const partner = String(partnerName || '').trim();
    const cohabitationParts = dateParts(cohabitationStartDate || fields.date.value);
    const startDate = cohabitationStartDate ? new Date(cohabitationStartDate) : new Date(fields.date.value);
    const issuedDate = fields.date.value ? new Date(fields.date.value) : new Date();
    let cohabitationYears = issuedDate.getFullYear() - startDate.getFullYear();
    const hasNotReachedAnniversary = issuedDate.getMonth() < startDate.getMonth() || (issuedDate.getMonth() === startDate.getMonth() && issuedDate.getDate() < startDate.getDate());
    if (hasNotReachedAnniversary) cohabitationYears -= 1;
    cohabitationYears = Math.max(0, cohabitationYears || 0);
    const cohabitationYearsWords = numberToWords(cohabitationYears);
    const pronouns = pronounsForGender(resident.gender);
    const clean = escapeValues ? escapeHtml : (value) => String(value ?? '');
    const replacements = {
      '{{resident_name}}': residentName.toUpperCase(),
      '{{resident_name_proper}}': residentName,
      '{{resident_address}}': resident.address || '',
      '{{age}}': String(resident.age || 0),
      '{{gender}}': resident.gender || '',
      '{{civil_status}}': resident.civil_status || '',
      '{{pronoun_subject}}': pronouns.subject,
      '{{pronoun_subject_lower}}': pronouns.subjectLower,
      '{{pronoun_object}}': pronouns.object,
      '{{pronoun_possessive}}': pronouns.possessive,
      '{{beneficiary_name}}': beneficiary.toUpperCase(),
      '{{beneficiary_name_proper}}': beneficiary,
      '{{beneficiary_relationship}}': relationship.toLowerCase(),
      '{{beneficiary_relationship_upper}}': relationship.toUpperCase(),
      '{{residency_years}}': String(years),
      '{{residency_years_words}}': yearsWords,
      '{{residency_years_text}}': `${yearsWords} [${years}]`,
      '{{partner_name}}': partner.toUpperCase(),
      '{{partner_name_proper}}': partner,
      '{{cohabitation_start_date}}': cohabitationParts.full,
      '{{cohabitation_start_day}}': cohabitationParts.day,
      '{{cohabitation_start_day_ordinal}}': cohabitationParts.dayOrdinal,
      '{{cohabitation_start_month}}': cohabitationParts.month,
      '{{cohabitation_start_month_upper}}': cohabitationParts.monthUpper,
      '{{cohabitation_start_year}}': cohabitationParts.year,
      '{{cohabitation_years}}': String(cohabitationYears),
      '{{cohabitation_years_words}}': cohabitationYearsWords,
      '{{cohabitation_years_text}}': `${cohabitationYearsWords} (${cohabitationYears})`,
      '{{purpose}}': (purpose || '').toUpperCase(),
      '{{date_issued}}': issued.full || date || '',
      '{{date_day}}': issued.day,
      '{{date_day_ordinal}}': issued.dayOrdinal,
      '{{date_month}}': issued.month,
      '{{date_month_upper}}': issued.monthUpper,
      '{{date_year}}': issued.year,
      '{{barangay_name}}': barangayName,
      '{{barangay_captain}}': barangayCaptain,
      '{{document_number}}': number || '',
    };
    let rendered = String(template || '');
    Object.entries(replacements).forEach(([placeholder, value]) => {
      rendered = rendered.split(placeholder).join(clean(value));
    });
    if (escapeValues) {
      return rendered.replace(/(TO WHOM IT MAY CONCERN:)(?=\S)/gi, '$1<br>');
    }
    return rendered
      .replace(/(TO WHOM IT MAY CONCERN:)(?=\S)/gi, '$1<br>')
      .replace(/([,.;:!?])(?=[^\s<])/g, '$1 ');
  }

  function setPreviewBody(text) {
    fields.previewBody.replaceChildren();
    const cleanText = String(text || '')
      .replace(/\u00a0/g, ' ')
      .replace(/[ \t]+/g, ' ')
      .replace(/(TO WHOM IT MAY CONCERN:)\s*/i, '$1\n')
      .replace(/([,.;:!?])(?=\S)/g, '$1 ')
      .trim();
    const lines = cleanText.split(/\r?\n+/).map((line) => line.trim()).filter(Boolean);
    (lines.length ? lines : ['']).forEach((line) => {
      const paragraph = document.createElement('p');
      paragraph.className = 'document-preview-paragraph';
      paragraph.textContent = line;
      fields.previewBody.appendChild(paragraph);
    });
  }

  function setPreviewHtml(html) {
    showFallbackPreview();
    fields.previewBody.replaceChildren();
    fields.previewBody.innerHTML = html;
  }

  function showFallbackPreview() {
    preview.classList.remove('is-word-preview', 'is-word-preview-loading');
    if (fields.wordPreviewShell) fields.wordPreviewShell.hidden = true;
  }

  function showWordPreviewLoading() {
    if (!fields.wordPreviewShell) return;
    preview.classList.add('is-word-preview', 'is-word-preview-loading');
    fields.wordPreviewShell.hidden = false;
    if (fields.wordPreviewFrame) {
      fields.wordPreviewFrame.removeAttribute('src');
    }
    setWordPreviewStatus('Preparing Microsoft Word preview...');
  }

  function showWordPreview(url) {
    if (!fields.wordPreviewShell || !fields.wordPreviewFrame) return;
    fields.wordPreviewFrame.src = 'document-preview-viewer.html?file=' + encodeURIComponent(url);
    fields.wordPreviewShell.hidden = false;
    fields.wordPreviewShell.style.setProperty('--word-preview-zoom', String(previewZoom));
    preview.classList.add('is-word-preview');
    preview.classList.remove('is-word-preview-loading');
    if (fields.wordPreviewStatus) fields.wordPreviewStatus.textContent = '';
  }

  function setWordPreviewStatus(message) {
    if (!fields.wordPreviewStatus) return;
    fields.wordPreviewStatus.textContent = message || '';
  }

  function scheduleWordPreview(templateRecord) {
    const requestId = ++wordPreviewRequestId;
    if (wordPreviewTimer) {
      clearTimeout(wordPreviewTimer);
    }
    showWordPreviewLoading();
    wordPreviewTimer = setTimeout(function () {
      wordPreviewPending = {requestId, payload: new FormData(form)};
      drainWordPreviews();
    }, 700);
  }

  async function drainWordPreviews() {
    if (wordPreviewRunning) return;
    wordPreviewRunning = true;
    try {
      while (wordPreviewPending) {
        const job = wordPreviewPending;
        wordPreviewPending = null;
        if (job.requestId !== wordPreviewRequestId) continue;
        try {
          let data;
          for (let attempt = 0; attempt < 17; attempt++) {
            const response = await fetch('staff-document-preview.php', {
              method: 'POST', body: job.payload, credentials: 'same-origin',
              headers: {'X-Requested-With': 'XMLHttpRequest'},
            });
            data = await response.json();
            if (job.requestId !== wordPreviewRequestId) break;
            if (data.code !== 'busy' || attempt === 16) break;
            setWordPreviewStatus('Waiting for the current preview to finish…');
            await new Promise(resolve => setTimeout(resolve, 1500));
            if (job.requestId !== wordPreviewRequestId) break;
          }
          if (job.requestId !== wordPreviewRequestId) continue;
          if (data && data.success && data.url) showWordPreview(data.url);
          else {
            preview.classList.remove('is-word-preview-loading');
            setWordPreviewStatus((data && data.message) || 'The Word preview could not be prepared.');
          }
        } catch (_) {
          if (job.requestId !== wordPreviewRequestId) continue;
          preview.classList.remove('is-word-preview-loading');
          setWordPreviewStatus('The Word preview could not be loaded. Check the connection and try again.');
        }
      }
    } finally { wordPreviewRunning = false; }
  }

  function updateAutofill() {
    const unregistered = fields.resident.value === '__walkin__';
    const walkinFields = form.querySelector('[data-walkin-fields]');
    if (walkinFields) {
      walkinFields.hidden = !unregistered;
      walkinFields.style.display = unregistered ? '' : 'none';
      walkinFields.querySelectorAll('input, select').forEach(field => { field.disabled = !unregistered; });
    }
    const summary = form.querySelector('[data-resident-summary]');
    summary.hidden = unregistered || !fields.resident.value;
    summary.style.display = summary.hidden ? 'none' : '';
    const resident = residentById(fields.resident.value);
    const residentChanged = fields.resident.value !== lastResidentId;
    lastResidentId = fields.resident.value;
    const type = fields.type.value;
    const fee = type === <?= json_encode($selectedDocumentType) ?> && requestFee !== null
      ? requestFee
      : Math.round((Number(documentFees[normalizedDocumentType(type)]) || 0) * 100) * (Number(copiesField.value) || 1) / 100;
    const unitFee = savedUnitFee !== null ? savedUnitFee : (Number(documentFees[normalizedDocumentType(type)]) || 0);
    form.querySelector('[data-issue-unit-fee]').textContent = `PHP ${unitFee.toFixed(2)} per copy`;
    const purpose = fields.purpose.value || 'Barangay service request';
    const issuedDate = formatDate(fields.date.value);
    const number = fields.number.value;
    const beneficiaryName = fields.beneficiaryName ? fields.beneficiaryName.value : '';
    const beneficiaryRelationship = fields.beneficiaryRelationship ? fields.beneficiaryRelationship.value : '';
    const residencyYears = fields.residencyYears ? fields.residencyYears.value : '';
    const partnerName = fields.partnerName ? fields.partnerName.value : '';
    const cohabitationStart = fields.cohabitationStart ? fields.cohabitationStart.value : '';
    const templateRecord = templateData[type] || {};
    const template = templateRecord.content || fallbackTemplate;
    const templateHtml = templateRecord.html || '';
    const showIndigency = isIndigencyType(type);

    toggleSection(fields.indigencyFields, showIndigency);
    toggleSection(fields.residencyYearsFields, usesResidencyYears(type));
    toggleSection(fields.cohabitationFields, isCohabitationType(type));

    fields.name.textContent = resident.name || '';
    fields.address.textContent = resident.address || '';
    fields.age.textContent = resident.age || 0;
    fields.gender.textContent = resident.gender || '';
    fields.civilStatus.textContent = resident.civil_status || '';
    fields.previewType.textContent = type;
    if (fields.fee) fields.fee.value = `PHP ${fee.toFixed(2)}`;
    fields.previewNumber.textContent = 'Document No. ' + number;
    if (fields.templateYearBadge) {
      fields.templateYearBadge.textContent = 'Template year: ' + ((templateData[type] && templateData[type].year) || <?= json_encode(date('Y')) ?>);
    }
    if (templateHtml) {
      setPreviewHtml(renderTemplate(templateHtml, resident, purpose, issuedDate, number, beneficiaryName, beneficiaryRelationship, residencyYears, partnerName, cohabitationStart, true));
    } else {
      showFallbackPreview();
      setPreviewBody(renderTemplate(template, resident, purpose, issuedDate, number, beneficiaryName, beneficiaryRelationship, residencyYears, partnerName, cohabitationStart, false));
    }
    scheduleWordPreview(templateRecord);
  }

  form.addEventListener('resident-details-selected', function (event) {
    const details = event.detail;
    if (!details || !details.id) return;
    const previous = residentData.findIndex(resident => resident.id === details.id);
    if (previous >= 0) residentData[previous] = details;
    else residentData.push(details);
  });

  ['change', 'input'].forEach((eventName) => {
    form.querySelectorAll('[data-walkin-fields] input, [data-walkin-fields] select').forEach(field => field.addEventListener(eventName, updateAutofill));
    [fields.resident, fields.type, fields.purpose, fields.date, fields.number, fields.beneficiaryName, fields.beneficiaryRelationship, fields.residencyYears, fields.partnerName, fields.cohabitationStart].forEach((field) => {
      if (field) field.addEventListener(eventName, updateAutofill);
    });
  });

  preview.addEventListener('pointerdown', function (event) {
    if (!preview.classList.contains('is-word-preview')) return;
    previewDragging = true;
    previewDragStartX = event.clientX;
    previewDragStartY = event.clientY;
    previewScrollStartLeft = preview.scrollLeft;
    previewScrollStartTop = preview.scrollTop;
    preview.classList.add('is-dragging-preview');
    preview.setPointerCapture(event.pointerId);
    event.preventDefault();
  });

  preview.addEventListener('pointermove', function (event) {
    if (!previewDragging) return;
    preview.scrollLeft = previewScrollStartLeft - (event.clientX - previewDragStartX);
    preview.scrollTop = previewScrollStartTop - (event.clientY - previewDragStartY);
  });

  function stopPreviewDrag(event) {
    if (!previewDragging) return;
    previewDragging = false;
    preview.classList.remove('is-dragging-preview');
    if (event && preview.hasPointerCapture(event.pointerId)) {
      preview.releasePointerCapture(event.pointerId);
    }
  }

  preview.addEventListener('pointerup', stopPreviewDrag);
  preview.addEventListener('pointercancel', stopPreviewDrag);
  preview.addEventListener('lostpointercapture', stopPreviewDrag);

  preview.addEventListener('wheel', function (event) {
    if (!preview.classList.contains('is-word-preview') || !event.ctrlKey || !fields.wordPreviewShell) return;
    event.preventDefault();
    event.stopPropagation();

    const previousZoom = previewZoom;
    const direction = event.deltaY < 0 ? 1 : -1;
    previewZoom = Math.min(2.2, Math.max(0.65, previewZoom + direction * 0.1));
    if (previewZoom === previousZoom) return;

    const rect = preview.getBoundingClientRect();
    const pointerX = event.clientX - rect.left;
    const pointerY = event.clientY - rect.top;
    const ratio = previewZoom / previousZoom;
    fields.wordPreviewShell.style.setProperty('--word-preview-zoom', String(previewZoom));
    preview.scrollLeft = (preview.scrollLeft + pointerX) * ratio - pointerX;
    preview.scrollTop = (preview.scrollTop + pointerY) * ratio - pointerY;
  }, { passive: false });
  updateAutofill();
})();
</script>
<?php endif; ?>

<script>
(function () {
  'use strict';

  const form = document.querySelector('[data-issue-document-form]');
  if (!form) return;

  const typeField = form.querySelector('[data-autofill-document-type]');
  const indigencyFields = form.querySelector('[data-indigency-fields]');
  const residencyYearsFields = form.querySelector('[data-residency-years-fields]');
  const cohabitationFields = form.querySelector('[data-cohabitation-fields]');
  if (!typeField || !indigencyFields || !residencyYearsFields || !cohabitationFields) return;

  function isIndigency(value) {
    return String(value || '').toLowerCase().indexOf('indigency') !== -1;
  }

  function normalizedDocumentType(value) {
    return String(value || '').trim().toLowerCase().replace(/\s+/g, ' ');
  }

  function isBarangayCertification(value) {
    const normalized = normalizedDocumentType(value);
    return normalized === 'barangay certification' || normalized === 'certificate of barangay certification';
  }

  function isCohabitation(value) {
    return normalizedDocumentType(value).indexOf('cohabitation') !== -1;
  }

  function hasResidencyYears(value) {
    return isIndigency(value) || isBarangayCertification(value);
  }

  function toggleSection(section, show) {
    section.hidden = !show;
    section.style.display = show ? '' : 'none';
  }

  function syncIndigencyFields() {
    toggleSection(indigencyFields, isIndigency(typeField.value));
    toggleSection(residencyYearsFields, hasResidencyYears(typeField.value));
    toggleSection(cohabitationFields, isCohabitation(typeField.value));
  }

  typeField.addEventListener('change', syncIndigencyFields);
  typeField.addEventListener('input', syncIndigencyFields);
  syncIndigencyFields();
})();
</script>

<?php $savedDocumentTemplates = staff_saved_uploaded_templates($documentTemplates); ?>
<div id="templateManagementModal" class="modal" aria-hidden="true" role="dialog">
  <div class="modal-backdrop" data-modal-close></div>
  <div class="modal-panel modal-panel--wide">
    <header class="modal-header">
      <h2>Template Management</h2>
      <button type="button" class="modal-close" data-modal-close aria-label="Close">&times;</button>
    </header>
    <details class="template-management-records">
      <summary>Saved templates (<?= count($savedDocumentTemplates) ?>)</summary>
      <p class="field-hint">To replace a file, choose its document type below and upload the correct DOCX. Saving replaces the template for the current year.</p>
      <?php foreach ($savedDocumentTemplates as $template): ?>
      <div class="template-management-record">
        <div class="template-record-heading"><strong><?= htmlspecialchars(staff_preferred_document_type_label($template['type'])) ?> · <?= (int) $template['year'] ?></strong><span class="badge badge-<?= $template['status'] === 'Active' ? 'active' : 'pending' ?>"><?= htmlspecialchars($template['status']) ?></span></div>
        <p class="field-hint">Word file: <?= htmlspecialchars($template['template_file_name'] ?: 'No DOCX uploaded yet') ?></p>
        <div class="template-management-actions">
          <?php if (!empty($template['template_file_path'])): ?>
          <a href="<?= htmlspecialchars($template['template_file_path']) ?>" class="btn btn-outline btn-sm" download>Download DOCX</a>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
      <?php if (!$savedDocumentTemplates): ?><p class="field-hint">No Word templates uploaded yet.</p><?php endif; ?>
    </details>
<form action="staff-actions.php" method="post" enctype="multipart/form-data" class="modal-body form-grid" data-template-auto data-template-current-year="<?= staff_current_template_year() ?>">
      <input type="hidden" name="action" value="save_template">
      <input type="hidden" name="redirect" value="issue-documents">
      <div class="form-grid-2">
        <div class="field">
          <label>Document type</label>
          <div class="document-type-picker" data-document-type-picker>
            <input type="hidden" name="document_type" value="<?= htmlspecialchars($selectedDocumentType) ?>" required data-template-document-select>
            <button type="button" class="document-type-trigger" data-document-type-trigger aria-expanded="false">
              <span data-document-type-label><?= htmlspecialchars($selectedDocumentType) ?></span>
              <span aria-hidden="true">v</span>
            </button>
            <div class="document-type-menu" data-document-type-options hidden>
              <?php foreach ($documentTypes as $type): ?>
              <div class="document-type-option">
                <button type="button" class="document-type-choice" data-document-type-choice data-value="<?= htmlspecialchars($type) ?>"><?= htmlspecialchars($type) ?></button>
                <button type="button" class="document-type-row-remove" data-document-type-remove-option data-value="<?= htmlspecialchars($type) ?>">Remove</button>
              </div>
              <?php endforeach; ?>
              <button type="button" class="document-type-choice document-type-choice--new" data-document-type-choice data-value="__new__">Add new document type</button>
            </div>
          </div>
          <input type="text" name="document_type_custom" placeholder="Type new document type" data-template-document-custom hidden>
          <p class="field-hint">Choose an existing type or select Add new document type.</p>
        </div>
        <div class="field">
          <label>Template name</label>
          <input type="text" name="name" value="<?= htmlspecialchars(staff_auto_template_name($selectedDocumentType, staff_current_template_year())) ?>" readonly required><p class="field-hint">Named automatically from the document type and year.</p>
        </div>
      </div>
      <div class="form-grid-2">
        <div class="field">
          <label>Effective year</label>
          <input type="number" name="effective_year" value="<?= staff_current_template_year() ?>" readonly><p class="field-hint">Current year, filled automatically.</p>
        </div>
        <div class="field">
          <label>Template status</label>
          <select name="status">
            <option>Active</option>
            <option>Draft</option>
            <option>Archived</option>
          </select>
        </div>
      </div>
      <div class="field">
        <label>Upload Word template (.docx)</label>
        <div class="file-upload-field">
          <input type="file" id="templateDocxUpload" name="template_docx" accept=".docx,application/vnd.openxmlformats-officedocument.wordprocessingml.document" data-file-input>
          <label for="templateDocxUpload" class="btn btn-outline btn-sm">Upload DOCX</label>
          <span data-file-name>No Word file selected</span>
        </div>
        <p class="field-hint">Upload a new Word document each year when the barangay format changes. For Indigency certificates requested for a child or dependent, use: This certification is issued upon the request of the above-mentioned person as requirement for the {{purpose}} for {{pronoun_possessive}} {{beneficiary_relationship}} {{beneficiary_name}}.</p>
      </div>
      <div class="field">
        <label>Edit template content</label>
        <textarea name="content" rows="8">This is to certify that {{resident_name}}, {{age}} years old, {{civil_status}}, residing at {{resident_address}}, {{barangay_name}}, is issued this document for {{purpose}} on {{date_issued}}. Document No. {{document_number}}.</textarea>
      </div>
      <footer class="modal-footer">
        <button type="button" class="btn btn-outline" data-modal-close>Cancel</button>
        <button type="submit" class="btn btn-primary">Save / replace template</button>
      </footer>
    </form>


  </div>
</div>

<style>
.template-management-records { padding: 1rem 1.25rem; border-bottom: 1px solid var(--border); }
.template-management-records > summary { cursor: pointer; font-weight: 600; }
.template-management-record { padding: 1rem 0; border-bottom: 1px solid var(--border); overflow-wrap: anywhere; }
.template-management-record:last-child { border-bottom: 0; }
.template-management-actions { display: flex; flex-wrap: wrap; gap: .5rem; margin-top: .75rem; }
.template-management-actions a { text-decoration: none; }
</style>

<script>
document.addEventListener('DOMContentLoaded', () => {
  if (window.location.hash !== '#template-management') return;
  const modal = document.getElementById('templateManagementModal');
  if (!modal) return;
  window.barangayOpenModal('templateManagementModal');
});
</script>

<div id="deleteDocumentTypeModal" class="modal" aria-hidden="true" role="dialog">
  <div class="modal-backdrop" data-modal-close></div>
  <div class="modal-panel">
    <header class="modal-header">
      <h2>Remove Document Type</h2>
      <button type="button" class="modal-close" data-modal-close aria-label="Close">&times;</button>
    </header>
    <div class="modal-body">
      <p>Remove <strong data-document-type-remove-label>this document type</strong>?</p>
      <p class="field-hint" data-document-type-remove-help>This removes the saved templates under that document type.</p>
    </div>
    <form id="deleteDocumentTypeForm" action="staff-actions.php" method="post">
      <input type="hidden" name="action" value="delete_document_type">
      <input type="hidden" name="redirect" value="issue-documents">
      <input type="hidden" name="document_type" value="" data-document-type-remove-value>
    </form>
    <footer class="modal-footer">
      <button type="button" class="btn btn-outline" data-modal-close>Cancel</button>
      <button type="submit" form="deleteDocumentTypeForm" class="btn btn-danger">Remove</button>
    </footer>
  </div>
</div>

<script>
(function () {
  'use strict';

  const select = document.querySelector('[data-template-document-select]');
  const custom = document.querySelector('[data-template-document-custom]');
  const picker = document.querySelector('[data-document-type-picker]');
  const trigger = document.querySelector('[data-document-type-trigger]');
  const options = document.querySelector('[data-document-type-options]');
  const label = document.querySelector('[data-document-type-label]');
  const removeLabel = document.querySelector('[data-document-type-remove-label]');
  const removeValue = document.querySelector('[data-document-type-remove-value]');
  const defaultTypes = <?= json_encode(array_map('strtolower', staff_default_document_types()), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
  const removeHelp = document.querySelector('[data-document-type-remove-help]');
  const removeSubmit = document.querySelector('#deleteDocumentTypeModal .btn-danger');
  if (!select || !custom || !picker || !trigger || !options || !label) return;

  function selectedType() {
    return String(select.value || '').trim();
  }

  function isDefaultType(value) {
    return defaultTypes.includes(String(value || '').trim().toLowerCase());
  }

  function openModal(id) {
    window.barangayOpenModal(id);
  }

  function closeOptions() {
    options.hidden = true;
    trigger.setAttribute('aria-expanded', 'false');
  }

  function openOptions() {
    options.hidden = false;
    trigger.setAttribute('aria-expanded', 'true');
  }

  function chooseType(value) {
    select.value = value;
    label.textContent = value === '__new__' ? 'Add new document type' : value;
    select.dispatchEvent(new Event('change', { bubbles: true }));
    syncCustomDocumentType();
    closeOptions();
  }

  function prepareRemove(value) {
    if (removeLabel) {
      removeLabel.textContent = value || 'this document type';
    }
    if (removeValue) {
      removeValue.value = value;
    }
    if (removeHelp) {
      removeHelp.textContent = isDefaultType(value)
        ? 'This is a default document type, so it cannot be removed here. You can still edit or delete its saved templates in Template Management.'
        : 'This removes the saved templates under that document type.';
    }
    if (removeSubmit) {
      removeSubmit.disabled = isDefaultType(value);
      removeSubmit.textContent = isDefaultType(value) ? 'Protected' : 'Remove';
    }
  }

  function syncCustomDocumentType() {
    const isNew = select.value === '__new__';
    custom.hidden = !isNew;
    custom.required = isNew;
    custom.disabled = !isNew;
    prepareRemove(selectedType());
    if (isNew) {
      custom.focus();
    } else {
      custom.value = '';
    }
  }

  trigger.addEventListener('click', function () {
    if (options.hidden) {
      openOptions();
    } else {
      closeOptions();
    }
  });
  document.querySelectorAll('[data-document-type-choice]').forEach(function (button) {
    button.addEventListener('click', function () {
      chooseType(button.getAttribute('data-value') || '');
    });
  });
  document.querySelectorAll('[data-document-type-remove-option]').forEach(function (button) {
    button.addEventListener('click', function (event) {
      event.stopPropagation();
      const value = button.getAttribute('data-value') || '';
      prepareRemove(value);
      closeOptions();
      openModal('deleteDocumentTypeModal');
    });
  });
  document.addEventListener('click', function (event) {
    if (!picker.contains(event.target)) {
      closeOptions();
    }
  });
  select.addEventListener('change', function () {
    label.textContent = selectedType() === '__new__' ? 'Add new document type' : selectedType();
    syncCustomDocumentType();
  });
  syncCustomDocumentType();
})();
</script>

<script src="js/resident-service-tools.js?v=<?= filemtime(__DIR__ . '/../../js/resident-service-tools.js') ?>" defer></script>
<script src="js/vendor/jsQR.js?v=<?= filemtime(__DIR__ . '/../../js/vendor/jsQR.js') ?>" defer></script>
<script src="js/issue-qr-capture.js?v=<?= filemtime(__DIR__ . '/../../js/issue-qr-capture.js') ?>" defer></script>


<script src="js/template-auto-fields.js?v=<?= filemtime(__DIR__ . '/../../js/template-auto-fields.js') ?>" defer></script>

