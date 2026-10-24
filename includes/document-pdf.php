<?php
/** Run Word outside the web request, with a bounded wait and atomic PDF publication. */
function barangay_pdf_valid(string $path): bool {
  if (!is_file($path) || filesize($path) < 20) return false;
  $handle = fopen($path, 'rb');
  if (!$handle) return false;
  try {
    if (fread($handle, 5) !== '%PDF-') return false;
    fseek($handle, max(0, filesize($path) - 1024));
    return str_contains((string) stream_get_contents($handle), '%%EOF');
  } finally { fclose($handle); }
}

function barangay_pdf_cache(string $source): array {
  $digest = hash_file('sha256', $source);
  if (!$digest) throw new RuntimeException('Could not read the document for printing.');
  $name = 'print-' . substr($digest, 0, 32) . '.pdf';
  $dir = dirname(__DIR__) . '/storage/document-previews';
  if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) throw new RuntimeException('Could not create the print preview folder.');
  return ['path'=>$dir . '/' . $name, 'url'=>'storage/document-previews/' . $name];
}

function barangay_pdf_php_binary(): ?string {
  $name = PHP_OS_FAMILY === 'Windows' ? 'php.exe' : 'php';
  // Windows builds retain C:\php as PHP_BINDIR even when Laragon installs PHP elsewhere.
  $directories = [php_ini_loaded_file() ? dirname(php_ini_loaded_file()) : '', dirname(PHP_BINARY), PHP_BINDIR];
  foreach ($directories as $directory) {
    if ($directory !== '' && is_file($directory . DIRECTORY_SEPARATOR . $name)) return $directory . DIRECTORY_SEPARATOR . $name;
  }
  return PHP_SAPI === 'cli' && is_file(PHP_BINARY) ? PHP_BINARY : null;
}

/** $command is an internal test seam; web callers cannot supply it. */
function barangay_pdf_run_worker(array $command, string $output, float $timeout): array {
  if (!function_exists('proc_open')) return ['ok'=>false,'code'=>'unavailable'];
  $log = $output . '.log';
  $process = null;
  try {
    $process = @proc_open($command, [0=>['file', PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null','r'],1=>['file',$log,'w'],2=>['file',$log,'a']], $pipes, dirname(__DIR__), null, ['bypass_shell'=>true]);
    if (!is_resource($process)) return ['ok'=>false,'code'=>'unavailable'];
    $deadline = microtime(true) + $timeout;
    do {
      $status = proc_get_status($process);
      if (!$status['running']) {
        if ((int) $status['exitcode'] === 0 && barangay_pdf_valid($output)) return ['ok'=>true,'code'=>'ready'];
        return ['ok'=>false,'code'=>(int) $status['exitcode'] === 3 ? 'unavailable' : 'failed'];
      }
      if (microtime(true) >= $deadline) {
        proc_terminate($process);
        // Word may finish exporting but stall while closing. Keep a complete PDF in that case.
        return barangay_pdf_valid($output) ? ['ok'=>true,'code'=>'ready'] : ['ok'=>false,'code'=>'timeout'];
      }
      usleep(100000);
    } while (true);
  } finally {
    if (is_resource($process)) {
      if (proc_get_status($process)['running']) proc_terminate($process);
      proc_close($process);
    }
    if (is_file($log)) @unlink($log);
  }
}

function barangay_pdf_message(string $code): string {
  return match ($code) {
    'timeout'=>'The print preview took too long. Retry once, or download the DOCX and print it in Microsoft Word.',
    'busy'=>'Another print preview is being prepared. Please retry shortly.',
    'unavailable'=>'Automatic PDF preview is unavailable on this server. Download the DOCX and print it in Microsoft Word.',
    'changed'=>'The document changed while preparing its preview. Please retry.',
    default=>'The PDF preview could not be prepared. Retry, or download the DOCX and print it in Microsoft Word.',
  };
}

function barangay_prepare_document_pdf(string $source, float $timeout = 20): array {
  $source = realpath($source);
  if (!$source || strtolower(pathinfo($source, PATHINFO_EXTENSION)) !== 'docx') throw new InvalidArgumentException('Printable document not found.');
  $root = realpath(dirname(__DIR__) . '/storage');
  $normalized = str_replace('\\','/',$source);
  $allowed = false;
  foreach (['generated-documents','document-previews'] as $folder) {
    if (str_starts_with(strtolower($normalized), strtolower(str_replace('\\','/',$root) . '/' . $folder . '/'))) $allowed = true;
  }
  if (!$allowed) throw new InvalidArgumentException('Invalid printable document.');
  $cache = barangay_pdf_cache($source);
  if (barangay_pdf_valid($cache['path'])) return ['ok'=>true,'url'=>$cache['url'],'cached'=>true];
  $php = barangay_pdf_php_binary();
  if (!$php || !function_exists('proc_open')) return ['ok'=>false,'code'=>'unavailable','message'=>barangay_pdf_message('unavailable')];
  $lock = fopen(dirname($cache['path']) . '/.word-conversion.lock','c');
  if (!$lock) throw new RuntimeException('Could not prepare the print preview.');
  if (!flock($lock, LOCK_EX | LOCK_NB)) { fclose($lock); return ['ok'=>false,'code'=>'busy','message'=>barangay_pdf_message('busy')]; }
  $temporary = dirname($cache['path']) . '/.conversion-' . bin2hex(random_bytes(12)) . '.pdf';
  try {
    if (barangay_pdf_valid($cache['path'])) return ['ok'=>true,'url'=>$cache['url'],'cached'=>true];
    $command = [$php];
    if (php_ini_loaded_file()) { $command[]='-c'; $command[]=php_ini_loaded_file(); }
    $command[] = __DIR__ . '/document-pdf-worker.php'; $command[]=$source; $command[]=$temporary;
    $result = barangay_pdf_run_worker($command, $temporary, max(1,min(30,$timeout)));
    if ($result['ok']) {
      if (barangay_pdf_cache($source)['path'] !== $cache['path']) $result=['ok'=>false,'code'=>'changed'];
      else {
        if (!rename($temporary,$cache['path'])) throw new RuntimeException('Could not save the print preview.');
        return ['ok'=>true,'url'=>$cache['url'],'cached'=>false];
      }
    }
    return ['ok'=>false,'code'=>$result['code'],'message'=>barangay_pdf_message($result['code'])];
  } finally {
    if (is_file($temporary)) @unlink($temporary);
    flock($lock, LOCK_UN); fclose($lock);
  }
}
