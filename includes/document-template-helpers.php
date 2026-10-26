<?php
require_once __DIR__ . '/document-custom-fields.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/document-pdf.php';

function staff_current_template_year(): int {
  return (int) (new DateTimeImmutable('now', new DateTimeZone('Asia/Manila')))->format('Y');
}

function staff_auto_template_name(string $type, int $year): string {
  return trim($type) . ' ' . $year . ' Official Format';
}

function staff_saved_uploaded_templates(array $templates): array {
  $saved = [];
  foreach ($templates as $template) {
    if (empty($template['template_file_path'])) continue;
    $key = staff_document_type_key($template['type']) . ':' . (int) $template['year'];
    // Input is ordered by active status, then newest update.
    if (!isset($saved[$key])) $saved[$key] = $template;
  }
  return array_values($saved);
}

function staff_save_template_replacement(PDO $db, array $values): int {
  barangay_custom_document_schema($db);
  $customFields = array_key_exists('custom_fields', $values) ? barangay_custom_field_definitions($values['custom_fields']) : null;
  // Serialize uploads, including simultaneous saves of a previously unseen type.
  $lockName = 'template-save-' . hash('sha256', (string) $db->query('SELECT DATABASE()')->fetchColumn());
  $lockName = substr($lockName, 0, 64);
  $lock = $db->prepare('SELECT GET_LOCK(?, 10)');
  $lock->execute([$lockName]);
  if ((int) $lock->fetchColumn() !== 1) throw new RuntimeException('Template save is busy. Please try again.');
  try {
    $db->beginTransaction();
    $rows = $db->query("SELECT id, document_type, effective_year, template_file_name, template_file_path, custom_fields_json FROM document_templates ORDER BY FIELD(status, 'Active', 'Draft', 'Archived'), updated_at DESC, id DESC FOR UPDATE")->fetchAll();
    $matching = array_values(array_filter($rows, static fn($row) => staff_document_type_key($row['document_type']) === staff_document_type_key($values['type']) && (int) $row['effective_year'] === (int) $values['year']));
    $existing = $matching[0] ?? null;
    if ($values['status'] === 'Active') {
      $clear = $db->prepare("UPDATE document_templates SET status = 'Draft' WHERE id = ? AND status = 'Active'");
      foreach ($rows as $row) {
        if (staff_document_type_key($row['document_type']) === staff_document_type_key($values['type'])) $clear->execute([$row['id']]);
      }
    }
    $params = [$values['name'], $values['type'], $values['year'], $values['content'], $values['file_name'] ?? ($existing['template_file_name'] ?? null), $values['file_path'] ?? ($existing['template_file_path'] ?? null), $values['status'], $values['user_id']];
    $params[] = json_encode($customFields ?? barangay_custom_json($existing['custom_fields_json'] ?? null), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    if ($existing) {
      $id = (int) $existing['id'];
      $stmt = $db->prepare('UPDATE document_templates SET name=?, document_type=?, effective_year=?, content=?, template_file_name=?, template_file_path=?, status=?, updated_by=?, custom_fields_json=?, updated_at=NOW() WHERE id=?');
      $stmt->execute(array_merge($params, [$id]));
      // Preserve old duplicate records/files as history, but retire them from use.
      $archive = $db->prepare("UPDATE document_templates SET status='Archived' WHERE id=?");
      foreach (array_slice($matching, 1) as $duplicate) $archive->execute([$duplicate['id']]);
    } else {
      $stmt = $db->prepare('INSERT INTO document_templates (name,document_type,effective_year,content,template_file_name,template_file_path,status,updated_by,custom_fields_json,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,NOW(),NOW())');
      $stmt->execute($params);
      $id = (int) $db->lastInsertId();
    }
    $db->commit();
    return $id;
  } catch (Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    throw $e;
  } finally {
    $release = $db->prepare('SELECT RELEASE_LOCK(?)');
    $release->execute([$lockName]);
  }
}

function staff_ensure_issued_document_file_columns(): void {
  $stmt = barangay_db()->prepare(
    "SELECT COLUMN_NAME
     FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'issued_documents'
       AND COLUMN_NAME IN ('generated_file_name', 'generated_file_path')"
  );
  $stmt->execute();
  $existing = array_column($stmt->fetchAll(), 'COLUMN_NAME');

  if (!in_array('generated_file_name', $existing, true)) {
    barangay_db()->exec('ALTER TABLE issued_documents ADD COLUMN generated_file_name VARCHAR(255) DEFAULT NULL AFTER purpose');
  }
  if (!in_array('generated_file_path', $existing, true)) {
    barangay_db()->exec('ALTER TABLE issued_documents ADD COLUMN generated_file_path VARCHAR(255) DEFAULT NULL AFTER generated_file_name');
  }
}

function staff_document_output_dir(): string {
  $dir = dirname(__DIR__) . '/storage/generated-documents';
  if (!is_dir($dir)) {
    mkdir($dir, 0775, true);
  }
  return $dir;
}

function staff_document_preview_dir(): string {
  $dir = dirname(__DIR__) . '/storage/document-previews';
  if (!is_dir($dir)) {
    mkdir($dir, 0775, true);
  }
  return $dir;
}

function staff_document_placeholder_keys(): array {
  return [
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
}

function staff_upper_text(string $value): string {
  return function_exists('mb_strtoupper') ? mb_strtoupper($value, 'UTF-8') : strtoupper($value);
}

function staff_ordinal_day(int $day): string {
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

function staff_pronoun_values(string $gender): array {
  $gender = strtolower(trim($gender));
  if ($gender === 'male') {
    return ['He', 'he', 'him', 'his'];
  }
  if ($gender === 'female') {
    return ['She', 'she', 'her', 'her'];
  }
  return ['They', 'they', 'them', 'their'];
}

function staff_number_to_words(int $number): string {
  $ones = [
    0 => 'zero',
    1 => 'one',
    2 => 'two',
    3 => 'three',
    4 => 'four',
    5 => 'five',
    6 => 'six',
    7 => 'seven',
    8 => 'eight',
    9 => 'nine',
    10 => 'ten',
    11 => 'eleven',
    12 => 'twelve',
    13 => 'thirteen',
    14 => 'fourteen',
    15 => 'fifteen',
    16 => 'sixteen',
    17 => 'seventeen',
    18 => 'eighteen',
    19 => 'nineteen',
  ];
  $tens = [
    20 => 'twenty',
    30 => 'thirty',
    40 => 'forty',
    50 => 'fifty',
    60 => 'sixty',
    70 => 'seventy',
    80 => 'eighty',
    90 => 'ninety',
  ];

  if ($number < 0) {
    return 'zero';
  }
  if ($number < 20) {
    return $ones[$number] ?? 'zero';
  }
  if ($number < 100) {
    $ten = (int) (floor($number / 10) * 10);
    $rest = $number % 10;
    return $tens[$ten] . ($rest ? '-' . $ones[$rest] : '');
  }
  if ($number < 1000) {
    $hundreds = intdiv($number, 100);
    $rest = $number % 100;
    return $ones[$hundreds] . ' hundred' . ($rest ? ' ' . staff_number_to_words($rest) : '');
  }
  return (string) $number;
}

function staff_document_template_values(array $resident, string $purpose, string $dateIssued, string $captain, string $documentNumber, array $extra = []): array {
  $timestamp = strtotime($dateIssued) ?: time();
  $dateText = date('F j, Y', $timestamp);
  $residentName = $resident['full_name'] ?? $resident['name'] ?? '';
  $beneficiaryName = trim((string) ($extra['beneficiary_name'] ?? ''));
  $beneficiaryRelationship = trim((string) ($extra['beneficiary_relationship'] ?? ''));
  $residencyYears = max(0, (int) ($extra['residency_years'] ?? 0));
  $residencyYearsWords = staff_number_to_words($residencyYears);
  $partnerName = trim((string) ($extra['partner_name'] ?? ''));
  $cohabitationStartDate = trim((string) ($extra['cohabitation_start_date'] ?? ''));
  $cohabitationTimestamp = strtotime($cohabitationStartDate) ?: $timestamp;
  $cohabitationYears = 0;
  if ($cohabitationStartDate !== '' && strtotime($cohabitationStartDate) !== false) {
    $start = new DateTime(date('Y-m-d', $cohabitationTimestamp));
    $issued = new DateTime(date('Y-m-d', $timestamp));
    $cohabitationYears = max(0, (int) $start->diff($issued)->y);
  }
  $cohabitationYearsWords = staff_number_to_words($cohabitationYears);
  [$pronounSubject, $pronounSubjectLower, $pronounObject, $pronounPossessive] = staff_pronoun_values((string) ($resident['gender'] ?? ''));
  return [
    '{{resident_name}}' => staff_upper_text($residentName),
    '{{resident_name_proper}}' => $residentName,
    '{{resident_address}}' => $resident['address'] ?? '',
    '{{age}}' => (string) (int) ($resident['age'] ?? 0),
    '{{gender}}' => $resident['gender'] ?? '',
    '{{civil_status}}' => $resident['civil_status'] ?? '',
    '{{pronoun_subject}}' => $pronounSubject,
    '{{pronoun_subject_lower}}' => $pronounSubjectLower,
    '{{pronoun_object}}' => $pronounObject,
    '{{pronoun_possessive}}' => $pronounPossessive,
    '{{beneficiary_name}}' => staff_upper_text($beneficiaryName),
    '{{beneficiary_name_proper}}' => $beneficiaryName,
    '{{beneficiary_relationship}}' => strtolower($beneficiaryRelationship),
    '{{beneficiary_relationship_upper}}' => staff_upper_text($beneficiaryRelationship),
    '{{residency_years}}' => (string) $residencyYears,
    '{{residency_years_words}}' => $residencyYearsWords,
    '{{residency_years_text}}' => $residencyYearsWords . ' [' . $residencyYears . ']',
    '{{partner_name}}' => staff_upper_text($partnerName),
    '{{partner_name_proper}}' => $partnerName,
    '{{cohabitation_start_date}}' => date('F j, Y', $cohabitationTimestamp),
    '{{cohabitation_start_day}}' => date('j', $cohabitationTimestamp),
    '{{cohabitation_start_day_ordinal}}' => staff_ordinal_day((int) date('j', $cohabitationTimestamp)),
    '{{cohabitation_start_month}}' => date('F', $cohabitationTimestamp),
    '{{cohabitation_start_month_upper}}' => strtoupper(date('F', $cohabitationTimestamp)),
    '{{cohabitation_start_year}}' => date('Y', $cohabitationTimestamp),
    '{{cohabitation_years}}' => (string) $cohabitationYears,
    '{{cohabitation_years_words}}' => $cohabitationYearsWords,
    '{{cohabitation_years_text}}' => $cohabitationYearsWords . ' (' . $cohabitationYears . ')',
    '{{purpose}}' => staff_upper_text($purpose),
    '{{date_issued}}' => $dateText,
    '{{date_day}}' => date('j', $timestamp),
    '{{date_day_ordinal}}' => staff_ordinal_day((int) date('j', $timestamp)),
    '{{date_month}}' => date('F', $timestamp),
    '{{date_month_upper}}' => strtoupper(date('F', $timestamp)),
    '{{date_year}}' => date('Y', $timestamp),
    '{{barangay_name}}' => 'Barangay Luna, Surigao City, Surigao del Norte, Philippines',
    '{{barangay_captain}}' => $captain,
    '{{document_number}}' => $documentNumber,
  ];
}

function staff_xml_escape(string $value): string {
  return htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
}

function staff_placeholder_pattern(string $placeholder): string {
  $chars = preg_split('//u', $placeholder, -1, PREG_SPLIT_NO_EMPTY);
  $pattern = '';
  foreach ($chars as $char) {
    $pattern .= preg_quote($char, '/') . '(?:<[^>]+>)*';
  }
  return '/' . $pattern . '/u';
}

function staff_preserve_docx_text_spaces(string $xml): string {
  return preg_replace_callback('/(<w:t\b[^>]*>)(.*?)(<\/w:t>)/su', static function (array $matches): string {
    $open = $matches[1];
    $text = $matches[2];
    if (!preg_match('/^\s|\s$/u', html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8'))) {
      return $matches[0];
    }
    if (!str_contains($open, 'xml:space=')) {
      $open = preg_replace('/<w:t\b/', '<w:t xml:space="preserve"', $open, 1) ?? $open;
    }
    return $open . $text . $matches[3];
  }, $xml) ?? $xml;
}

function staff_separate_docx_placeholder_marker_joins(string $xml, string $marker): string {
  if ($marker === '') {
    return $xml;
  }
  $escapedMarker = preg_quote($marker, '/');
  $runGap = '<\/w:t><\/w:r><w:r\b[^>]*><w:t\b[^>]*>';

  $xml = preg_replace('/([A-Za-z0-9])(' . $escapedMarker . ')/u', '$1 $2', $xml) ?? $xml;
  $xml = preg_replace('/(' . $escapedMarker . ')([A-Za-z0-9])/u', '$1 $2', $xml) ?? $xml;
  $xml = preg_replace('/([A-Za-z0-9])(?=' . $runGap . $escapedMarker . ')/u', '$1 ', $xml) ?? $xml;
  $xml = preg_replace('/(' . $escapedMarker . ')(?=' . $runGap . '[A-Za-z0-9])/u', '$1 ', $xml) ?? $xml;
  return $xml;
}

function staff_docx_paragraph_text(DOMElement $paragraph, DOMXPath $xpath): string {
  $text = '';
  foreach ($xpath->query('.//w:t', $paragraph) ?: [] as $node) {
    $text .= $node->nodeValue;
  }
  return $text;
}

function staff_merge_docx_issued_date_paragraphs(string $xml, string $dateMonthMarker): string {
  if ($dateMonthMarker === '' || !class_exists('DOMDocument') || !class_exists('DOMXPath')) {
    return $xml;
  }

  $previous = libxml_use_internal_errors(true);
  $document = new DOMDocument();
  $loaded = $document->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
  libxml_clear_errors();
  libxml_use_internal_errors($previous);
  if (!$loaded) {
    return $xml;
  }

  $xpath = new DOMXPath($document);
  $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
  $paragraphs = [];
  foreach ($xpath->query('//w:p') ?: [] as $paragraph) {
    if ($paragraph instanceof DOMElement) {
      $paragraphs[] = $paragraph;
    }
  }

  $changed = false;
  $wordNamespace = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
  for ($i = 0; $i < count($paragraphs) - 1; $i++) {
    $current = $paragraphs[$i];
    $next = $paragraphs[$i + 1];
    $currentText = trim(staff_docx_paragraph_text($current, $xpath));
    $nextText = ltrim(staff_docx_paragraph_text($next, $xpath));

    if (!preg_match('/day of$/i', $currentText) || !str_starts_with($nextText, $dateMonthMarker)) {
      continue;
    }

    $run = $document->createElementNS($wordNamespace, 'w:r');
    $text = $document->createElementNS($wordNamespace, 'w:t', ' ');
    $text->setAttributeNS('http://www.w3.org/XML/1998/namespace', 'xml:space', 'preserve');
    $run->appendChild($text);
    $current->appendChild($run);

    while ($next->firstChild) {
      $child = $next->firstChild;
      if ($child instanceof DOMElement && $child->localName === 'pPr') {
        $next->removeChild($child);
        continue;
      }
      $current->appendChild($child);
    }

    if ($next->parentNode) {
      $next->parentNode->removeChild($next);
    }
    $changed = true;
  }

  return $changed ? ($document->saveXML() ?: $xml) : $xml;
}

function staff_fill_docx_xml(string $xml, array $values): string {
  $markers = [];
  $placeholderMarkers = [];
  $index = 0;
  foreach ($values as $placeholder => $value) {
    $marker = 'BLDOCXPLACEHOLDER' . $index . 'TOKEN';
    $markers[$marker] = staff_xml_escape((string) $value);
    $placeholderMarkers[$placeholder] = $marker;
    $xml = str_replace($placeholder, $marker, $xml);
    $xml = preg_replace(staff_placeholder_pattern($placeholder), $marker, $xml);
    $xml = staff_separate_docx_placeholder_marker_joins($xml, $marker);
    $index++;
  }

  $xml = staff_merge_docx_issued_date_paragraphs($xml, $placeholderMarkers['{{date_month_upper}}'] ?? '');

  foreach ($markers as $marker => $replacement) {
    $xml = str_replace($marker, $replacement, $xml);
  }
  return staff_preserve_docx_text_spaces($xml);
}

function staff_docx_absolute_path(string $templateRelativePath): string {
  return dirname(__DIR__) . '/' . ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $templateRelativePath), DIRECTORY_SEPARATOR);
}

function staff_extract_text_from_docx_xml(string $xml): string {
  $lines = [];

  if (class_exists('DOMDocument') && class_exists('DOMXPath')) {
    $previous = libxml_use_internal_errors(true);
    $document = new DOMDocument();
    $loaded = $document->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    if ($loaded) {
      $xpath = new DOMXPath($document);
      $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
      foreach ($xpath->query('//w:p') ?: [] as $paragraph) {
        $text = '';
        foreach ($xpath->query('.//w:t|.//w:tab|.//w:br|.//w:cr', $paragraph) ?: [] as $node) {
          if ($node->localName === 'tab') {
            $text .= ' ';
          } elseif ($node->localName === 'br' || $node->localName === 'cr') {
            $text .= "\n";
          } else {
            $text .= $node->nodeValue;
          }
        }
        foreach (preg_split('/\R+/', $text) as $line) {
          $line = trim(preg_replace('/[ \t]+/', ' ', $line));
          if ($line !== '') {
            $lines[] = $line;
          }
        }
      }
    }
  }

  if (!$lines && preg_match_all('/<w:t\b[^>]*>(.*?)<\/w:t>/su', $xml, $matches)) {
    foreach ($matches[1] as $text) {
      $text = trim(html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_XML1, 'UTF-8'));
      if ($text !== '') {
        $lines[] = $text;
      }
    }
  }

  return implode("\n", $lines);
}

function staff_docx_entry_contents(string $templatePath, string $pattern): array {
  $entries = [];
  if (class_exists('ZipArchive')) {
    $zip = new ZipArchive();
    if ($zip->open($templatePath) !== true) {
      return [];
    }
    for ($i = 0; $i < $zip->numFiles; $i++) {
      $name = $zip->getNameIndex($i);
      if ($name === false || !preg_match($pattern, $name)) {
        continue;
      }
      $content = $zip->getFromIndex($i);
      if ($content !== false) {
        $entries[$name] = $content;
      }
    }
    $zip->close();
    return $entries;
  }

  if (!function_exists('shell_exec')) {
    return [];
  }

  $list = shell_exec('tar -tf ' . escapeshellarg($templatePath));
  foreach (preg_split('/\R+/', (string) $list) as $file) {
    $file = trim($file);
    if ($file === '' || !preg_match($pattern, $file)) {
      continue;
    }
    $content = shell_exec('tar -xOf ' . escapeshellarg($templatePath) . ' ' . escapeshellarg($file));
    if ($content !== null && $content !== '') {
      $entries[$file] = $content;
    }
  }
  return $entries;
}

function staff_docx_relationships(array $entries): array {
  $relationships = [];
  $relsXml = $entries['word/_rels/document.xml.rels'] ?? '';
  if ($relsXml === '' || !class_exists('DOMDocument')) {
    return $relationships;
  }

  $previous = libxml_use_internal_errors(true);
  $document = new DOMDocument();
  $loaded = $document->loadXML($relsXml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
  libxml_clear_errors();
  libxml_use_internal_errors($previous);
  if (!$loaded) {
    return $relationships;
  }

  foreach ($document->getElementsByTagName('Relationship') as $relationship) {
    $id = $relationship->getAttribute('Id');
    $target = $relationship->getAttribute('Target');
    if ($id !== '' && $target !== '') {
      $relationships[$id] = str_starts_with($target, 'word/') ? $target : 'word/' . ltrim($target, '/');
    }
  }
  return $relationships;
}

function staff_docx_html_class_for_alignment(string $alignment): string {
  return match ($alignment) {
    'center' => ' docx-align-center',
    'right' => ' docx-align-right',
    'both', 'distribute', 'thaiDistribute' => ' docx-align-justify',
    default => '',
  };
}

function staff_render_docx_paragraph_html(DOMElement $paragraph, DOMXPath $xpath, array $relationships, array $entries): string {
  $alignment = '';
  $alignmentNode = $xpath->query('./w:pPr/w:jc', $paragraph)->item(0);
  if ($alignmentNode instanceof DOMElement) {
    $alignment = $alignmentNode->getAttribute('w:val') ?: $alignmentNode->getAttributeNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'val');
  }

  $parts = [];
  foreach ($xpath->query('.//w:t|.//w:tab|.//w:br|.//w:cr', $paragraph) ?: [] as $node) {
    if ($node->localName === 'tab') {
      $parts[] = ' ';
    } elseif ($node->localName === 'br' || $node->localName === 'cr') {
      $parts[] = '<br>';
    } else {
      $parts[] = htmlspecialchars($node->nodeValue, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
  }

  foreach ($xpath->query('.//a:blip/@r:embed|.//v:imagedata/@r:id', $paragraph) ?: [] as $imageRef) {
    $relationshipId = $imageRef->nodeValue;
    $target = $relationships[$relationshipId] ?? '';
    if ($target === '' || empty($entries[$target])) {
      continue;
    }
    $extension = strtolower(pathinfo($target, PATHINFO_EXTENSION));
    $mime = match ($extension) {
      'jpg', 'jpeg' => 'image/jpeg',
      'gif' => 'image/gif',
      'bmp' => 'image/bmp',
      default => 'image/png',
    };
    $parts[] = '<img class="docx-preview-image" src="data:' . $mime . ';base64,' . base64_encode($entries[$target]) . '" alt="">';
  }

  $content = trim(implode('', $parts));
  if ($content === '') {
    return '';
  }

  $class = 'docx-preview-paragraph' . staff_docx_html_class_for_alignment($alignment);
  return '<p class="' . $class . '">' . $content . '</p>';
}

function staff_render_docx_table_html(DOMElement $table, DOMXPath $xpath, array $relationships, array $entries): string {
  $rows = [];
  foreach ($xpath->query('./w:tr', $table) ?: [] as $row) {
    $cells = [];
    foreach ($xpath->query('./w:tc', $row) ?: [] as $cell) {
      $cellParts = [];
      foreach ($xpath->query('./w:p', $cell) ?: [] as $paragraph) {
        if ($paragraph instanceof DOMElement) {
          $cellParts[] = staff_render_docx_paragraph_html($paragraph, $xpath, $relationships, $entries);
        }
      }
      $cells[] = '<td>' . implode('', array_filter($cellParts)) . '</td>';
    }
    if ($cells) {
      $rows[] = '<tr>' . implode('', $cells) . '</tr>';
    }
  }
  return $rows ? '<table class="docx-preview-table">' . implode('', $rows) . '</table>' : '';
}

function staff_extract_docx_html_from_template(string $templateRelativePath): string {
  $templatePath = staff_docx_absolute_path($templateRelativePath);
  if (!is_file($templatePath) || !class_exists('DOMDocument') || !class_exists('DOMXPath')) {
    return '';
  }

  $entryPattern = class_exists('ZipArchive')
    ? '/^(word\/document\.xml|word\/_rels\/document\.xml\.rels|word\/media\/.+)$/'
    : '/^(word\/document\.xml|word\/_rels\/document\.xml\.rels)$/';
  $entries = staff_docx_entry_contents($templatePath, $entryPattern);
  $xml = $entries['word/document.xml'] ?? '';
  if ($xml === '') {
    return '';
  }

  $previous = libxml_use_internal_errors(true);
  $document = new DOMDocument();
  $loaded = $document->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
  libxml_clear_errors();
  libxml_use_internal_errors($previous);
  if (!$loaded) {
    return '';
  }

  $xpath = new DOMXPath($document);
  $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
  $xpath->registerNamespace('a', 'http://schemas.openxmlformats.org/drawingml/2006/main');
  $xpath->registerNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');
  $xpath->registerNamespace('v', 'urn:schemas-microsoft-com:vml');

  $relationships = staff_docx_relationships($entries);
  $parts = [];
  foreach ($xpath->query('/w:document/w:body/*') ?: [] as $node) {
    if (!$node instanceof DOMElement) {
      continue;
    }
    if ($node->localName === 'p') {
      $parts[] = staff_render_docx_paragraph_html($node, $xpath, $relationships, $entries);
    } elseif ($node->localName === 'tbl') {
      $parts[] = staff_render_docx_table_html($node, $xpath, $relationships, $entries);
    }
  }

  $body = implode('', array_filter($parts));
  return $body !== '' ? '<div class="docx-preview-page">' . $body . '</div>' : '';
}

function staff_extract_docx_text_from_template(string $templateRelativePath): string {
  $templatePath = staff_docx_absolute_path($templateRelativePath);
  if (!is_file($templatePath)) {
    return '';
  }

  $xmlParts = [];
  if (class_exists('ZipArchive')) {
    $zip = new ZipArchive();
    if ($zip->open($templatePath) !== true) {
      return '';
    }
    for ($i = 0; $i < $zip->numFiles; $i++) {
      $name = $zip->getNameIndex($i);
      if ($name === false || !preg_match('/^word\/(document|header\d*|footer\d*)\.xml$/', $name)) {
        continue;
      }
      $content = $zip->getFromIndex($i);
      if ($content !== false) {
        $xmlParts[$name] = $content;
      }
    }
    $zip->close();
  } elseif (function_exists('shell_exec')) {
    $list = shell_exec('tar -tf ' . escapeshellarg($templatePath));
    foreach (preg_split('/\R+/', (string) $list) as $file) {
      $file = trim($file);
      if (!preg_match('/^word\/(document|header\d*|footer\d*)\.xml$/', $file)) {
        continue;
      }
      $content = shell_exec('tar -xOf ' . escapeshellarg($templatePath) . ' ' . escapeshellarg($file));
      if ($content !== null && $content !== '') {
        $xmlParts[$file] = $content;
      }
    }
  }

  if (!$xmlParts) {
    return '';
  }

  uksort($xmlParts, static function (string $left, string $right): int {
    if ($left === 'word/document.xml') return -1;
    if ($right === 'word/document.xml') return 1;
    return strcmp($left, $right);
  });

  $textParts = [];
  foreach ($xmlParts as $xml) {
    $text = staff_extract_text_from_docx_xml($xml);
    if ($text !== '') {
      $textParts[] = $text;
    }
  }

  return implode("\n", $textParts);
}

/** Explain unmatched tags before a template is published or replaced. */
function staff_template_tag_problems(string $text, array $fields): array {
  $problems=[]; $used=[]; $unknown=[]; $spacing=[];
  $known=array_fill_keys(array_merge(BARANGAY_STANDARD_DOCUMENT_KEYS,array_column($fields,'key')),true);
  preg_match_all('/\{\{([^{}]*)\}\}/u',$text,$matches,PREG_SET_ORDER);
  foreach ($matches as $match) {
    $key=trim($match[1]);$used[$key]=true;
    if (!isset($known[$key])) $unknown[]='{{'.$key.'}}';
    elseif ($match[0] !== '{{'.$key.'}}') $spacing[]='{{'.$key.'}}';
  }
  $unmatched=preg_replace('/\{\{[^{}]*\}\}/u','',$text);
  if (str_contains($unmatched,'{{') || str_contains($unmatched,'}}')) $problems[]='A Word tag is incomplete. Copy the complete tag, including both pairs of braces, into Word.';
  if (!$matches) $problems[]='No fill-in tags were found in the Word file. Choose Analyze uploaded DOCX to prepare it automatically, or add a tag such as {{resident_name}} in Word and upload the DOCX again.';
  if ($unknown) $problems[]='These Word tags have no matching field: '.implode(', ',array_unique($unknown)).'. Add a field for each one or replace it with a listed tag.';
  if ($spacing) $problems[]='Remove the extra spaces inside these Word tags. Copy them exactly as shown: '.implode(', ',array_unique($spacing)).'.';
  $missing=[];
  foreach ($fields as $field) if (!isset($used[$field['key']])) $missing[]=$field['label'].' ({{'.$field['key'].'}})';
  if ($missing) $problems[]='Add these field tags to the Word document where their answers should appear: '.implode(', ',$missing).'. You can also remove fields you do not need.';
  return $problems;
}
function staff_check_template_custom_tags(string $path, array $fields): void {
  if ($path==='') {
    if ($fields) throw new InvalidArgumentException('Upload a DOCX template and paste each field tag into it before saving these additional fields.');
    return;
  }
  if (!is_file(staff_docx_absolute_path($path))) throw new InvalidArgumentException('The saved Word file is unavailable. Upload the DOCX again.');
  $problems=staff_template_tag_problems(staff_extract_docx_text_from_template($path),$fields);
  if ($problems) throw new InvalidArgumentException(implode("\n",$problems));
}

function staff_xml_contains_supported_placeholder(string $xml, array $placeholders): bool {
  foreach ($placeholders as $placeholder) {
    if (str_contains($xml, $placeholder) || preg_match(staff_placeholder_pattern($placeholder), $xml)) {
      return true;
    }
  }
  return false;
}

function staff_docx_contains_placeholders(string $templateRelativePath, ?array $placeholders = null): bool {
  $templatePath = staff_docx_absolute_path($templateRelativePath);
  if (!is_file($templatePath)) {
    return false;
  }

  $placeholders = $placeholders ?: staff_document_placeholder_keys();
  if (class_exists('ZipArchive')) {
    $zip = new ZipArchive();
    if ($zip->open($templatePath) !== true) {
      return false;
    }
    for ($i = 0; $i < $zip->numFiles; $i++) {
      $name = $zip->getNameIndex($i);
      if ($name === false || !preg_match('/^word\/(document|header\d*|footer\d*)\.xml$/', $name)) {
        continue;
      }
      $content = $zip->getFromIndex($i);
      if ($content !== false && staff_xml_contains_supported_placeholder($content, $placeholders)) {
        $zip->close();
        return true;
      }
    }
    $zip->close();
    return false;
  }

  if (!function_exists('shell_exec')) {
    return false;
  }

  $list = shell_exec('tar -tf ' . escapeshellarg($templatePath));
  foreach (preg_split('/\R+/', (string) $list) as $file) {
    $file = trim($file);
    if (!preg_match('/^word\/(document|header\d*|footer\d*)\.xml$/', $file)) {
      continue;
    }
    $content = shell_exec('tar -xOf ' . escapeshellarg($templatePath) . ' ' . escapeshellarg($file));
    if ($content !== null && staff_xml_contains_supported_placeholder($content, $placeholders)) {
      return true;
    }
  }
  return false;
}

function staff_rrmdir(string $dir): void {
  if (!is_dir($dir)) return;
  $items = scandir($dir);
  foreach ($items ?: [] as $item) {
    if ($item === '.' || $item === '..') continue;
    $path = $dir . DIRECTORY_SEPARATOR . $item;
    if (is_dir($path)) {
      staff_rrmdir($path);
    } else {
      @unlink($path);
    }
  }
  @rmdir($dir);
}

function staff_relative_docx_files(string $dir): array {
  $files = [];
  $baseLength = strlen(rtrim($dir, DIRECTORY_SEPARATOR)) + 1;
  $iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::LEAVES_ONLY
  );

  foreach ($iterator as $file) {
    if (!$file->isFile()) {
      continue;
    }
    $relative = substr($file->getPathname(), $baseLength);
    $files[] = str_replace(DIRECTORY_SEPARATOR, '/', $relative);
  }

  usort($files, static function (string $left, string $right): int {
    if ($left === '[Content_Types].xml') return -1;
    if ($right === '[Content_Types].xml') return 1;
    return strcmp($left, $right);
  });

  return $files;
}

function staff_generate_docx_with_ziparchive(string $templatePath, string $targetPath, array $values): bool {
  if (!class_exists('ZipArchive')) {
    return false;
  }
  $zip = new ZipArchive();
  if ($zip->open($templatePath) !== true) {
    return false;
  }
  $targetZip = new ZipArchive();
  if ($targetZip->open($targetPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    $zip->close();
    return false;
  }
  for ($i = 0; $i < $zip->numFiles; $i++) {
    $name = $zip->getNameIndex($i);
    $content = $zip->getFromIndex($i);
    if ($name === false || $content === false) continue;
    if (preg_match('/^word\/(document|header\d*|footer\d*)\.xml$/', $name)) {
      $content = staff_fill_docx_xml($content, $values);
    }
    $targetZip->addFromString($name, $content);
  }
  $zip->close();
  return $targetZip->close();
}

function staff_generate_docx_with_tar(string $templatePath, string $targetPath, array $values): bool {
  if (!function_exists('shell_exec')) {
    return false;
  }

  $tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'barangay-docx-' . bin2hex(random_bytes(6));
  if (!mkdir($tempDir, 0775, true)) {
    return false;
  }

  $extractCmd = 'tar -xf ' . escapeshellarg($templatePath) . ' -C ' . escapeshellarg($tempDir);
  shell_exec($extractCmd);

  $wordDir = $tempDir . DIRECTORY_SEPARATOR . 'word';
  if (!is_dir($wordDir)) {
    staff_rrmdir($tempDir);
    return false;
  }

  $files = glob($wordDir . DIRECTORY_SEPARATOR . '*.xml') ?: [];
  foreach ($files as $file) {
    $base = basename($file);
    if (!preg_match('/^(document|header\d*|footer\d*)\.xml$/', $base)) {
      continue;
    }
    $xml = file_get_contents($file);
    if ($xml === false) continue;
    file_put_contents($file, staff_fill_docx_xml($xml, $values));
  }

  $targetDir = dirname($targetPath);
  if (!is_dir($targetDir)) {
    mkdir($targetDir, 0775, true);
  }
  if (is_file($targetPath)) {
    @unlink($targetPath);
  }

  $files = staff_relative_docx_files($tempDir);
  if (!$files) {
    staff_rrmdir($tempDir);
    return false;
  }

  $createCmd = 'tar --format zip -cf ' . escapeshellarg($targetPath) . ' -C ' . escapeshellarg($tempDir);
  foreach ($files as $file) {
    $createCmd .= ' ' . escapeshellarg($file);
  }
  shell_exec($createCmd);
  $ok = is_file($targetPath) && filesize($targetPath) > 0;
  staff_rrmdir($tempDir);
  return $ok;
}

function staff_repack_docx_file(string $docxPath): bool {
  if (!is_file($docxPath) || !function_exists('shell_exec')) {
    return false;
  }

  $tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'barangay-docx-repack-' . bin2hex(random_bytes(6));
  if (!mkdir($tempDir, 0775, true)) {
    return false;
  }

  shell_exec('tar -xf ' . escapeshellarg($docxPath) . ' -C ' . escapeshellarg($tempDir));
  if (!is_file($tempDir . DIRECTORY_SEPARATOR . '[Content_Types].xml') || !is_dir($tempDir . DIRECTORY_SEPARATOR . 'word')) {
    staff_rrmdir($tempDir);
    return false;
  }

  $files = staff_relative_docx_files($tempDir);
  if (!$files) {
    staff_rrmdir($tempDir);
    return false;
  }

  $targetPath = $docxPath . '.fixed';
  if (is_file($targetPath)) {
    @unlink($targetPath);
  }

  $createCmd = 'tar --format zip -cf ' . escapeshellarg($targetPath) . ' -C ' . escapeshellarg($tempDir);
  foreach ($files as $file) {
    $createCmd .= ' ' . escapeshellarg($file);
  }
  shell_exec($createCmd);

  $ok = is_file($targetPath) && filesize($targetPath) > 0;
  if ($ok) {
    @unlink($docxPath);
    $ok = @rename($targetPath, $docxPath);
  }
  if (is_file($targetPath)) {
    @unlink($targetPath);
  }
  staff_rrmdir($tempDir);
  return $ok;
}

function staff_generate_docx_from_template(string $templateRelativePath, array $values, string $documentNumber): ?array {
  $templatePath = staff_docx_absolute_path($templateRelativePath);
  if (!is_file($templatePath)) {
    return null;
  }

  $safeNumber = preg_replace('/[^a-z0-9-]+/i', '-', strtolower($documentNumber));
  $filename = 'issued-' . trim($safeNumber, '-') . '-' . date('YmdHis') . '.docx';
  $targetPath = staff_document_output_dir() . '/' . $filename;
  $ok = staff_generate_docx_with_ziparchive($templatePath, $targetPath, $values);
  if (!$ok) {
    $ok = staff_generate_docx_with_tar($templatePath, $targetPath, $values);
  }

  if (!$ok) {
    return null;
  }

  return [
    'name' => $filename,
    'path' => 'storage/generated-documents/' . $filename,
  ];
}

function staff_word_com_available(): bool {
  $disabled = array_map('strtolower', array_map('trim', explode(',', (string) ini_get('disable_classes'))));
  return class_exists('COM') && !in_array('com', $disabled, true);
}

function staff_generate_word_pdf_preview(string $templateRelativePath, array $values, string $documentNumber, ?string &$previewError = null, ?string &$previewCode = null): ?array {
  if (!staff_word_com_available()) {
    $previewError = barangay_pdf_message('unavailable');
    return null;
  }

  $templatePath = staff_docx_absolute_path($templateRelativePath);
  if (!is_file($templatePath)) {
    return null;
  }

  $safeNumber = preg_replace('/[^a-z0-9-]+/i', '-', strtolower($documentNumber ?: ('preview-' . date('YmdHis'))));
  $baseName = 'preview-' . trim($safeNumber, '-') . '-' . bin2hex(random_bytes(4));
  $docxPath = staff_document_preview_dir() . '/' . $baseName . '.docx';
  $ok = staff_generate_docx_with_ziparchive($templatePath, $docxPath, $values);
  if (!$ok) {
    $ok = staff_generate_docx_with_tar($templatePath, $docxPath, $values);
  }
  if (!$ok || !is_file($docxPath)) {
    return null;
  }

  try {
    $result = barangay_prepare_document_pdf($docxPath);
    if (!$result['ok']) { $previewError = $result['message']; $previewCode = $result['code']; return null; }
    return ['name'=>basename($result['url']), 'path'=>$result['url']];
  } catch (Throwable $error) {
    $previewError = barangay_pdf_message('failed');
    return null;
  } finally { if (is_file($docxPath)) unlink($docxPath); }
}
?>
