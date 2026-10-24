<?php
$placeholders = [
  '{{resident_name}}',
  '{{resident_name_proper}}',
  '{{resident_address}}',
  '{{age}}',
  '{{gender}}',
  '{{civil_status}}',
  '{{pronoun_subject}}',
  '{{pronoun_subject_lower}}',
  '{{pronoun_object}}',
  '{{pronoun_possessive}}',
  '{{beneficiary_name}}',
  '{{beneficiary_name_proper}}',
  '{{beneficiary_relationship}}',
  '{{beneficiary_relationship_upper}}',
  '{{residency_years}}',
  '{{residency_years_words}}',
  '{{residency_years_text}}',
  '{{partner_name}}',
  '{{partner_name_proper}}',
  '{{cohabitation_start_date}}',
  '{{cohabitation_start_day}}',
  '{{cohabitation_start_day_ordinal}}',
  '{{cohabitation_start_month}}',
  '{{cohabitation_start_month_upper}}',
  '{{cohabitation_start_year}}',
  '{{cohabitation_years}}',
  '{{cohabitation_years_words}}',
  '{{cohabitation_years_text}}',
  '{{purpose}}',
  '{{date_issued}}',
  '{{date_day}}',
  '{{date_day_ordinal}}',
  '{{date_month}}',
  '{{date_month_upper}}',
  '{{date_year}}',
  '{{barangay_name}}',
  '{{barangay_captain}}',
  '{{document_number}}',
];
?>
<div class="page-intro"><h2>Template Placeholders</h2></div>
<section class="document-settings-page" id="template-placeholders">
  <div class="panel-body">
    <div class="placeholder-list">
      <?php foreach ($placeholders as $placeholder): ?>
      <code><?= htmlspecialchars($placeholder) ?></code>
      <?php endforeach; ?>
    </div>
  </div>
</section>
