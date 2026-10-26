<?php
/** Revenue exports share the same rows and totals regardless of file format. */
function barangay_revenue_export_rows(string $from, string $to, array $metrics, array $payments): array {
  $display = static fn(string $date) => (new DateTimeImmutable($date))->format('M/j/Y');
  foreach ($payments as &$payment) {
    if (!empty($payment[6])) $payment[6] = (new DateTimeImmutable($payment[6]))->format('M/j/Y g:i A');
  }
  unset($payment);
  return array_merge([
    ['Period start', $display($from)], ['Period end', $display($to)],
    ['Collected fees (PHP)', (float) $metrics['revenue']],
    ['Cash tendered (PHP)', (float) $metrics['cash_received']],
    ['Change due (PHP)', (float) $metrics['change_due']], [],
    ['Payment code', 'Document number', 'Document type', 'Fee collected (PHP)', 'Cash tendered (PHP)', 'Receipt', 'Payment date'],
  ], $payments);
}

/** Small native XLSX writer: typed text, numeric money, fixed widths and wrapped cells. */
function barangay_revenue_export_xlsx(array $rows): string {
  $escape = static fn($value) => htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
  $sheet = '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetViews><sheetView workbookViewId="0"><pane ySplit="7" topLeftCell="A8" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews><cols>';
  foreach ([30, 34, 32, 23, 23, 24, 26] as $i => $width) {
    $column = $i + 1;
    $sheet .= '<col min="'.$column.'" max="'.$column.'" width="'.$width.'" customWidth="1"/>';
  }
  $sheet .= '</cols><sheetData>';
  foreach ($rows as $index => $row) {
    $number = $index + 1;
    $sheet .= '<row r="'.$number.'" ht="30" customHeight="1">';
    foreach ($row as $column => $value) {
      $cell = chr(65 + $column).$number;
      // Identifiers and dates are explicitly text: never formulas or ambiguous Excel dates.
      $money = ($index >= 2 && $index <= 4 && $column === 1) || ($index >= 7 && in_array($column, [3,4], true));
      if ($money && is_numeric($value)) {
        $sheet .= '<c r="'.$cell.'" s="2"><v>'.(float) $value.'</v></c>';
      } else {
        $sheet .= '<c r="'.$cell.'" s="'.($index === 6 ? '1' : '0').'" t="inlineStr"><is><t xml:space="preserve">'.$escape($value).'</t></is></c>';
      }
    }
    $sheet .= '</row>';
  }
  $sheet .= '</sheetData><autoFilter ref="A7:G'.max(7,count($rows)).'"/></worksheet>';
  $parts = [
    '[Content_Types].xml' => '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>',
    '_rels/.rels' => '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>',
    'xl/workbook.xml' => '<?xml version="1.0"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Document revenue" sheetId="1" r:id="rId1"/></sheets></workbook>',
    'xl/_rels/workbook.xml.rels' => '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>',
    'xl/styles.xml' => '<?xml version="1.0"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF126744"/><bgColor indexed="64"/></patternFill></fill></fills><borders count="1"><border/></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="3"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf><xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf><xf numFmtId="4" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1" applyAlignment="1"><alignment vertical="center"/></xf></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>',
    'xl/worksheets/sheet1.xml' => $sheet,
  ];
  $path = tempnam(sys_get_temp_dir(), 'luna_revenue_');
  if ($path === false) throw new RuntimeException('Could not create the revenue export.');
  try {
    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::OVERWRITE) !== true) throw new RuntimeException('Could not open the revenue export.');
    foreach ($parts as $name => $xml) $zip->addFromString($name, $xml);
    if (!$zip->close()) throw new RuntimeException('Could not finish the revenue export.');
    $bytes = file_get_contents($path);
    if ($bytes === false) throw new RuntimeException('Could not read the revenue export.');
    return $bytes;
  } finally { if (is_file($path)) unlink($path); }
}
