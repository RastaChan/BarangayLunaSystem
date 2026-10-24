<?php
require_once __DIR__ . '/database.php';

function barangay_document_verification_schema(): void {
  static $ready = false;
  if ($ready) return;
  $columns = barangay_db()->query('SHOW COLUMNS FROM issued_documents')->fetchAll(PDO::FETCH_COLUMN);
  if (!in_array('verification_token', $columns, true)) barangay_db()->exec('ALTER TABLE issued_documents ADD verification_token CHAR(64) NULL UNIQUE');
  if (!in_array('verification_url', $columns, true)) barangay_db()->exec('ALTER TABLE issued_documents ADD verification_url VARCHAR(600) NULL');
  $ready = true;
}

function barangay_document_verification_url(string $token): string {
  $base = rtrim(trim((string) (getenv('BARANGAY_PUBLIC_URL') ?: '')), '/');
  if ($base === '') $base = rtrim(trim((string) barangay_db()->query("SELECT setting_value FROM system_settings WHERE setting_key = 'portal_public_url'")->fetchColumn()), '/');
  if ($base === '') {
    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    if (!preg_match('/^[a-zA-Z0-9.\-\[\]:]+$/D', $host)) throw new RuntimeException('Invalid portal address.');
    $path = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
    $base = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://') . $host . ($path === '/' ? '' : $path);
  }
  $parts = parse_url($base);
  if (!$parts || !in_array($parts['scheme'] ?? '', ['http','https'], true) || empty($parts['host'])
      || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
    throw new RuntimeException('Configure a valid public portal URL.');
  }
  // PHP may expose a decoded SCRIPT_NAME; encode each folder without double-encoding %20.
  $path = implode('/', array_map(static fn(string $segment): string => rawurlencode(rawurldecode($segment)), explode('/', $parts['path'] ?? '')));
  $base = $parts['scheme'] . '://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '') . rtrim($path, '/');
  if (!filter_var($base, FILTER_VALIDATE_URL)) throw new RuntimeException('Configure a valid public portal URL.');
  return $base . '/verify-document.php?token=' . $token;
}

function barangay_document_qr_png(string $url): string {
  require_once __DIR__ . '/vendor/qrcode.php';
  if (!function_exists('imagepng')) throw new RuntimeException('Enable the PHP GD extension to generate document QR codes.');
  // Select capacity from Reed-Solomon blocks (the library's shortcut only covers versions 1–10).
  for ($version = 1; $version <= 40; $version++) {
    $capacity = 0;
    foreach (QRRSBlock::getRSBlocks($version, QR_ERROR_CORRECT_LEVEL_M) as $block) $capacity += $block->getDataCount();
    if (strlen($url) * 8 + 4 + ($version < 10 ? 8 : 16) <= $capacity * 8) break;
  }
  if ($version > 40) throw new InvalidArgumentException('The document verification address is too long.');
  $qr = new QRCode(); $qr->setTypeNumber($version); $qr->setErrorCorrectLevel(QR_ERROR_CORRECT_LEVEL_M); $qr->addData($url, QR_MODE_8BIT_BYTE); $qr->make();
  // Margin is in pixels: preserve a four-module quiet zone.
  $image = $qr->createImage(6, 24);
  ob_start(); imagepng($image); $png = ob_get_clean(); imagedestroy($image);
  return $png;
}

function barangay_document_verification_public(string $token): ?array {
  if (!preg_match('/^[a-f0-9]{64}$/D', $token)) return null;
  $stmt = barangay_db()->prepare("SELECT document_number, document_type, copies, issuance_status, DATE(COALESCE(released_at, issued_at)) AS release_date FROM issued_documents WHERE verification_token = ? LIMIT 1");
  $stmt->execute([$token]);
  return $stmt->fetch() ?: null;
}

function barangay_embed_document_qr(string $docx, string $url): void {
  $zip = new ZipArchive();
  if ($zip->open($docx) !== true) throw new RuntimeException('Could not add document verification QR.');
  try {
    $xml = $zip->getFromName('word/document.xml');
    if (!$xml) throw new RuntimeException('Document template is invalid.');
    // A previously stamped historical document may already contain the verification image.
    if ($zip->locateName('word/media/barangay-verification.png') !== false && str_contains($xml, 'Document verification QR v2')) return;
    $document = new DOMDocument();
    if (!$document->loadXML($xml, LIBXML_NONET)) throw new RuntimeException('Document template is invalid.');
    $xpath = new DOMXPath($document);
    $xpath->registerNamespace('w','http://schemas.openxmlformats.org/wordprocessingml/2006/main');
    $xpath->registerNamespace('a','http://schemas.openxmlformats.org/drawingml/2006/main');
    $xpath->registerNamespace('r','http://schemas.openxmlformats.org/officeDocument/2006/relationships');
    // Replace only our own earlier stamp, preserving the document and its verification token.
    foreach ($xpath->query('//w:p[.//a:blip[@r:embed="rIdBarangayVerification"]] | //w:p[w:r/w:t="Scan to verify document release"]') as $oldStamp) $oldStamp->parentNode->removeChild($oldStamp);
    $xml = $document->saveXML();
    $png = barangay_document_qr_png($url);
    $rels = $zip->getFromName('word/_rels/document.xml.rels') ?: '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"></Relationships>';
    $id = 'rIdBarangayVerification';
    if (!str_contains($rels, 'Id="' . $id . '"')) $rels = str_replace('</Relationships>', '<Relationship Id="' . $id . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/barangay-verification.png"/></Relationships>', $rels);
    $types = $zip->getFromName('[Content_Types].xml');
    if (!str_contains(strtolower($types), 'extension="png"')) $types = str_replace('</Types>', '<Default Extension="png" ContentType="image/png"/></Types>', $types);
    $paragraph = '<w:p xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:pPr><w:jc w:val="right"/><w:spacing w:before="120" w:after="0"/></w:pPr><w:r><w:drawing><wp:inline xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing"><wp:extent cx="914400" cy="914400"/><wp:docPr id="998877" name="Document verification QR"/><a:graphic xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"><a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/picture"><pic:pic xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture"><pic:nvPicPr><pic:cNvPr id="998877" name="Document verification QR"/><pic:cNvPicPr/></pic:nvPicPr><pic:blipFill><a:blip xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" r:embed="' . $id . '"/><a:stretch><a:fillRect/></a:stretch></pic:blipFill><pic:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="914400" cy="914400"/></a:xfrm><a:prstGeom prst="rect"><a:avLst/></a:prstGeom></pic:spPr></pic:pic></a:graphicData></a:graphic></wp:inline></w:drawing></w:r></w:p><w:p xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:pPr><w:jc w:val="right"/></w:pPr><w:r><w:rPr><w:sz w:val="16"/></w:rPr><w:t>Scan to verify document release</w:t></w:r></w:p>';
    $paragraph = str_replace('name="Document verification QR"','name="Document verification QR v2"',$paragraph);
    if ($xpath->query('//w:txbxContent')->length > 0) {
      // These templates put the certificate in floating text boxes: normal flow starts in the header.
      // Anchor the QR in the clear lower-right area above the footer instead.
      $page = $xpath->query('(//w:sectPr/w:pgSz)[last()]')->item(0);
      $margin = $xpath->query('(//w:sectPr/w:pgMar)[last()]')->item(0);
      $height = (int) ($page?->getAttribute('w:h') ?: 16838);
      $bottom = (int) ($margin?->getAttribute('w:bottom') ?: 720);
      $offset = max(0, ($height - $bottom - 4200) * 635);
      $anchor = '<wp:anchor xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing" distT="0" distB="0" distL="0" distR="0" simplePos="0" relativeHeight="251659264" behindDoc="0" locked="0" layoutInCell="1" allowOverlap="1"><wp:simplePos x="0" y="0"/><wp:positionH relativeFrom="margin"><wp:align>right</wp:align></wp:positionH><wp:positionV relativeFrom="page"><wp:posOffset>' . $offset . '</wp:posOffset></wp:positionV>';
      $paragraph = str_replace('<wp:inline xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing">',$anchor,$paragraph);
      $paragraph = str_replace('<wp:docPr','<wp:wrapNone/><wp:docPr',$paragraph);
      $paragraph = str_replace('</wp:inline>','</wp:anchor>',$paragraph);
      $paragraph = substr($paragraph,0,strpos($paragraph,'</w:p>')+strlen('</w:p>'));
    }
    $position = strrpos($xml, '<w:sectPr');
    if ($position === false || strpos($xml, '</w:p>', $position) !== false) $position = strrpos($xml, '</w:body>');
    $xml = substr_replace($xml, $paragraph, $position, 0);
    $zip->addFromString('word/document.xml', $xml);
    $zip->addFromString('word/_rels/document.xml.rels', $rels);
    $zip->addFromString('[Content_Types].xml', $types);
    $zip->addFromString('word/media/barangay-verification.png', $png);
  } finally { if (!$zip->close()) throw new RuntimeException('Could not save the document verification QR.'); }
}

function barangay_document_attach_verification(int $id, string $docx): array {
  barangay_document_verification_schema();
  $lockName = 'document-qr-' . substr(hash('sha256', (string) barangay_db()->query('SELECT DATABASE()')->fetchColumn()),0,16) . '-' . $id;
  $lock = barangay_db()->prepare('SELECT GET_LOCK(?, 10)'); $lock->execute([$lockName]);
  if ((int) $lock->fetchColumn() !== 1) throw new RuntimeException('This document is being prepared. Please try again.');
  try {
  $candidate = bin2hex(random_bytes(32));
  barangay_db()->prepare('UPDATE issued_documents SET verification_token = COALESCE(verification_token, ?) WHERE id = ?')->execute([$candidate,$id]);
  $stmt = barangay_db()->prepare('SELECT verification_token, verification_url FROM issued_documents WHERE id = ?'); $stmt->execute([$id]); $row = $stmt->fetch();
  if (!$row) throw new InvalidArgumentException('Document not found.');
  $token = $row['verification_token'];
  $url = $row['verification_url'] ?: barangay_document_verification_url($token);
  barangay_embed_document_qr($docx, $url);
  barangay_db()->prepare('UPDATE issued_documents SET verification_token = ?, verification_url = ? WHERE id = ?')->execute([$token,$url,$id]);
  return ['token'=>$token,'url'=>$url];
  } finally { barangay_db()->prepare('SELECT RELEASE_LOCK(?)')->execute([$lockName]); }
}
