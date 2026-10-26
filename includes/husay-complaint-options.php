<?php
$optionEscape = static fn($value) => htmlspecialchars((string)$value, ENT_QUOTES);
?>
<option value="">Select a complaint without an active Mediation session</option>
<?php foreach (husay_available_complaints() as $availableComplaint): ?>
<option value="<?= $optionEscape($availableComplaint['complaint_code']) ?>" data-resident="<?= $optionEscape($availableComplaint['resident_name']) ?>" data-mobile="<?= $optionEscape($availableComplaint['contact_mobile']) ?>"><?= $optionEscape($availableComplaint['complaint_code'] . ' · ' . $availableComplaint['resident_name'] . ' · ' . $availableComplaint['subject']) ?></option>
<?php endforeach; ?>
