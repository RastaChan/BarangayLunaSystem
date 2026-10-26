<?php
require_once __DIR__ . '/document-payment-helpers.php';
require_once __DIR__ . '/feedback-helpers.php';
require_once __DIR__ . '/document-issuance-workflow.php';

function barangay_service_metrics(string $start, string $end): array {
  [$start, $end] = barangay_report_dates(['from'=>$start, 'to'=>$end]);
  barangay_issuance_schema();
  $db = barangay_db();
  $query = $db->prepare("SELECT COUNT(*) AS measured, AVG(TIMESTAMPDIFF(SECOND, requested_at, ready_at)) AS seconds
    FROM document_requests WHERE ready_at >= ? AND ready_at < DATE_ADD(?, INTERVAL 1 DAY) AND ready_at >= requested_at");
  $query->execute([$start,$end]); $ready = $query->fetch();
  $query = $db->prepare("SELECT COUNT(*) AS released, COUNT(CASE WHEN i.released_at >= r.requested_at THEN 1 END) AS measured,
    AVG(CASE WHEN i.released_at >= r.requested_at THEN TIMESTAMPDIFF(SECOND,r.requested_at,i.released_at) END) AS seconds
    FROM issued_documents i JOIN document_requests r ON r.request_code = i.request_code
    WHERE i.issuance_status = 'Released' AND i.released_at >= ? AND i.released_at < DATE_ADD(?,INTERVAL 1 DAY)");
  $query->execute([$start,$end]); $release = $query->fetch();
  $query = $db->prepare("SELECT COUNT(*) AS released, COUNT(CASE WHEN released_at >= prepared_at THEN 1 END) AS measured,
    AVG(CASE WHEN released_at >= prepared_at THEN TIMESTAMPDIFF(SECOND,prepared_at,released_at) END) AS seconds
    FROM issued_documents WHERE (request_code IS NULL OR request_code = '') AND issuance_status = 'Released'
    AND released_at >= ? AND released_at < DATE_ADD(?,INTERVAL 1 DAY)");
  $query->execute([$start,$end]); $walkin = $query->fetch();
  $pending = $db->query("SELECT COUNT(*) AS total, MAX(GREATEST(TIMESTAMPDIFF(SECOND,requested_at,NOW()),0)) AS oldest_seconds
    FROM document_requests WHERE archived_at IS NULL AND status IN ('Pending','Processing','Approved','Ready for Pickup')")->fetch();
  $open = $db->query("SELECT request_code, document_type, status, requested_at,
    GREATEST(TIMESTAMPDIFF(SECOND,requested_at,NOW()),0) AS age_seconds
    FROM document_requests WHERE archived_at IS NULL AND status IN ('Pending','Processing','Approved','Ready for Pickup')
    ORDER BY requested_at, id LIMIT 20")->fetchAll();
  return ['ready'=>$ready, 'release'=>$release, 'walkin'=>$walkin, 'pending'=>$pending, 'open'=>$open];
}

function barangay_service_duration($seconds): string {
  if ($seconds === null) return 'Not measured';
  $minutes = max(0, (int) round((float) $seconds / 60));
  if ($minutes < 1) return 'Less than 1 minute';
  if ($minutes < 60) return $minutes . ' min';
  if ($minutes < 1440) return intdiv($minutes,60) . ' hr ' . ($minutes % 60) . ' min';
  return intdiv($minutes,1440) . ' days ' . intdiv($minutes % 1440,60) . ' hr';
}

function barangay_report_dates(array $input): array {
  $start = is_string($input['from'] ?? null) ? $input['from'] : date('Y-m-01');
  $end = is_string($input['to'] ?? null) ? $input['to'] : date('Y-m-d');
  foreach ([$start, $end] as $date) {
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    if (!$parsed || $parsed->format('Y-m-d') !== $date) throw new InvalidArgumentException('Select valid report dates.');
  }
  if ($start > $end) throw new InvalidArgumentException('The start date must be before the end date.');
  return [$start, $end];
}

function barangay_report_date_display(string $date): string {
  return (new DateTimeImmutable($date))->format('M/j/Y');
}

function barangay_report_date_field(string $name, string $value): string {
  $display = htmlspecialchars(barangay_report_date_display($value), ENT_QUOTES);
  $name = htmlspecialchars($name, ENT_QUOTES);
  $value = htmlspecialchars($value, ENT_QUOTES);
  return '<span class="report-date-field"><span data-report-date-text>'.$display.'</span><span class="report-date-calendar" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 256 256" fill="currentColor" stroke="none" aria-hidden="true" class="system-icon" data-system-icon="calendar-days"><path d="M216,48V88H40V48a8,8,0,0,1,8-8H208A8,8,0,0,1,216,48Z" opacity="0.2"/><path d="M208,32H184V24a8,8,0,0,0-16,0v8H88V24a8,8,0,0,0-16,0v8H48A16,16,0,0,0,32,48V208a16,16,0,0,0,16,16H208a16,16,0,0,0,16-16V48A16,16,0,0,0,208,32ZM72,48v8a8,8,0,0,0,16,0V48h80v8a8,8,0,0,0,16,0V48h24V80H48V48ZM208,208H48V96H208V208Z"/></svg></span><input type="date" name="'.$name.'" value="'.$value.'" required data-report-date-picker></span>';
}

function barangay_revenue_metrics(string $start, string $end): array {
  barangay_ensure_document_payment_schema();
  [$start, $end] = barangay_report_dates(['from'=>$start, 'to'=>$end]);
  $statement = barangay_db()->prepare("SELECT COUNT(*) AS transactions, COALESCE(SUM(amount_due),0) AS revenue,
    COALESCE(SUM(amount_paid),0) AS cash_received, COALESCE(SUM(GREATEST(amount_paid - amount_due,0)),0) AS change_due
    FROM document_payments WHERE status = 'Paid' AND received_at >= ? AND received_at < DATE_ADD(?, INTERVAL 1 DAY)");
  $statement->execute([$start, $end]); $summary = $statement->fetch();
  $statement = barangay_db()->prepare("SELECT document_type, COUNT(*) AS transactions, SUM(amount_due) AS revenue
    FROM document_payments WHERE status = 'Paid' AND received_at >= ? AND received_at < DATE_ADD(?, INTERVAL 1 DAY)
    GROUP BY document_type ORDER BY revenue DESC, document_type");
  $statement->execute([$start,$end]); $summary['types'] = $statement->fetchAll();
  $statement = barangay_db()->prepare("SELECT DATE(received_at) AS date, COUNT(*) AS transactions, SUM(amount_due) AS revenue
    FROM document_payments WHERE status = 'Paid' AND received_at >= ? AND received_at < DATE_ADD(?, INTERVAL 1 DAY)
    GROUP BY DATE(received_at) ORDER BY date");
  $statement->execute([$start,$end]); $summary['daily'] = $statement->fetchAll();
  $summary['unpaid_balance'] = barangay_db()->query("SELECT COALESCE(SUM(amount_due),0) FROM document_payments WHERE status = 'Pending'")->fetchColumn();
  return $summary;
}

function barangay_feedback_metrics(): array {
  barangay_ensure_feedback_image_columns();
  $result = ['count'=>0,'average'=>null,'unrated'=>0,'distribution'=>array_fill(1,5,0)];
  foreach (barangay_db()->query('SELECT rating, COUNT(*) AS total FROM feedbacks WHERE archived_at IS NULL GROUP BY rating')->fetchAll() as $row) {
    $rating = (int) ($row['rating'] ?? 0);
    if ($rating >= 1 && $rating <= 5) { $result['distribution'][$rating] = (int) $row['total']; $result['count'] += (int) $row['total']; }
    else $result['unrated'] += (int) $row['total'];
  }
  if ($result['count']) { $sum=0; foreach ($result['distribution'] as $score=>$count) $sum += $score*$count; $result['average'] = $sum / $result['count']; }
  return $result;
}
