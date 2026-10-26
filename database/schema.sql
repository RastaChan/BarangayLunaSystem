CREATE DATABASE IF NOT EXISTS barangay_luna_system
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE barangay_luna_system;

CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_code VARCHAR(30) NOT NULL UNIQUE,
  role ENUM('admin', 'staff', 'resident') NOT NULL,
  full_name VARCHAR(150) NOT NULL,
  position_title VARCHAR(100) DEFAULT NULL,
  username VARCHAR(80) NOT NULL UNIQUE,
  mobile VARCHAR(30) DEFAULT NULL,
  address VARCHAR(255) DEFAULT NULL,
  purok VARCHAR(80) DEFAULT NULL,
  district_id INT UNSIGNED DEFAULT NULL,
  purok_id INT UNSIGNED DEFAULT NULL,
  household VARCHAR(80) DEFAULT NULL,
  birthdate DATE DEFAULT NULL,
  gender VARCHAR(40) DEFAULT NULL,
  civil_status VARCHAR(40) DEFAULT NULL,
  verification_status ENUM('Pending', 'Verified', 'Rejected') NOT NULL DEFAULT 'Pending',
  qr_status ENUM('Inactive', 'Active') NOT NULL DEFAULT 'Inactive',
  qr_code VARCHAR(100) DEFAULT NULL UNIQUE,
  qr_generated_at DATETIME DEFAULT NULL,
  profile_photo_path VARCHAR(255) DEFAULT NULL,
  registered_by INT UNSIGNED DEFAULT NULL,
  password_hash VARCHAR(255) NOT NULL,
  status ENUM('Active', 'Inactive', 'Pending') NOT NULL DEFAULT 'Active',
  last_login_at DATETIME DEFAULT NULL,
  last_active_at DATETIME DEFAULT NULL,
  inactive_reason VARCHAR(40) DEFAULT NULL,
  inactivated_at DATETIME DEFAULT NULL,
  reactivated_at DATETIME DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_users_role_status (role, status),
  INDEX idx_users_login (username),
  CONSTRAINT fk_users_registered_by FOREIGN KEY (registered_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS resident_districts (
  id INT UNSIGNED PRIMARY KEY,
  name VARCHAR(80) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS resident_puroks (
  id INT UNSIGNED PRIMARY KEY,
  name VARCHAR(80) NOT NULL,
  chairman VARCHAR(150) NOT NULL DEFAULT '',
  district_id INT UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO resident_districts (id, name) VALUES
  (1, 'District 1'),
  (2, 'District 2'),
  (3, 'District 3'),
  (4, 'District 4'),
  (5, 'District 5'),
  (6, 'District 6'),
  (7, 'District 7');
-- Admin adds puroks to each district through Resident Management.

CREATE TABLE IF NOT EXISTS announcements (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(180) NOT NULL,
  body TEXT NOT NULL,
  post_type VARCHAR(20) NOT NULL DEFAULT 'announcement',
  event_at DATETIME DEFAULT NULL,
  event_location VARCHAR(255) DEFAULT NULL,
  publish_date DATE NOT NULL,
  status ENUM('Published', 'Draft') NOT NULL DEFAULT 'Draft',
  author_id INT UNSIGNED DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  archived_at DATETIME DEFAULT NULL,
  INDEX idx_announcements_status_date (status, publish_date),
  CONSTRAINT fk_announcements_author FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS document_requests (
  custom_values_json MEDIUMTEXT NULL,
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  request_code VARCHAR(30) NOT NULL UNIQUE,
  resident_user_id INT UNSIGNED DEFAULT NULL,
  resident_name VARCHAR(150) NOT NULL,
  contact_mobile VARCHAR(30) DEFAULT NULL,
  document_type VARCHAR(100) NOT NULL,
  fee_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  purpose VARCHAR(255) DEFAULT NULL,
  copies INT UNSIGNED NOT NULL DEFAULT 1,
  unit_fee DECIMAL(10,2) DEFAULT NULL,
  pickup_preference VARCHAR(100) DEFAULT NULL,
  remarks TEXT DEFAULT NULL,
  beneficiary_name VARCHAR(150) DEFAULT NULL,
  beneficiary_relationship VARCHAR(80) DEFAULT NULL,
  residency_years INT UNSIGNED DEFAULT NULL,
  partner_name VARCHAR(150) DEFAULT NULL,
  cohabitation_start_date DATE DEFAULT NULL,
  resident_gender_override VARCHAR(20) DEFAULT NULL,
  status ENUM('Pending', 'Processing', 'Approved', 'Ready for Pickup', 'Released', 'Rejected') NOT NULL DEFAULT 'Pending',
  released_sms_sent_at DATETIME DEFAULT NULL,
  ready_at DATETIME DEFAULT NULL,
  requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  archived_at DATETIME DEFAULT NULL,
  INDEX idx_document_requests_status (status),
  INDEX idx_document_requests_resident (resident_user_id),
  CONSTRAINT fk_document_requests_resident FOREIGN KEY (resident_user_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS document_templates (
  custom_fields_json MEDIUMTEXT NULL,
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(180) NOT NULL,
  document_type VARCHAR(100) NOT NULL,
  effective_year YEAR NOT NULL,
  content TEXT DEFAULT NULL,
  template_file_name VARCHAR(255) DEFAULT NULL,
  template_file_path VARCHAR(255) DEFAULT NULL,
  status ENUM('Active', 'Draft', 'Archived') NOT NULL DEFAULT 'Draft',
  updated_by INT UNSIGNED DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_document_templates_type_status (document_type, status),
  CONSTRAINT fk_document_templates_user FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS document_fees (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  document_type VARCHAR(100) NOT NULL,
  fee_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  updated_by INT UNSIGNED DEFAULT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_document_fees_type (document_type),
  CONSTRAINT fk_document_fees_user FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS issued_documents (
  custom_values_json MEDIUMTEXT NULL,
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  document_number VARCHAR(60) NOT NULL UNIQUE,
  resident_user_id INT UNSIGNED DEFAULT NULL,
  resident_name VARCHAR(150) NOT NULL,
  document_type VARCHAR(100) NOT NULL,
  purpose VARCHAR(255) DEFAULT NULL,
  fee_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  copies INT UNSIGNED NOT NULL DEFAULT 1,
  unit_fee DECIMAL(10,2) DEFAULT NULL,
  verification_token CHAR(64) NULL UNIQUE,
  verification_url VARCHAR(600) NULL,
  generated_file_name VARCHAR(255) DEFAULT NULL,
  generated_file_path VARCHAR(255) DEFAULT NULL,
  issued_by INT UNSIGNED DEFAULT NULL,
  issued_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  request_code VARCHAR(30) DEFAULT NULL,
  issuance_status VARCHAR(30) NOT NULL DEFAULT 'Released',
  applicant_details TEXT DEFAULT NULL,
  contact_mobile VARCHAR(30) DEFAULT NULL,
  requirements_checked TINYINT NOT NULL DEFAULT 0,
  released_by INT UNSIGNED DEFAULT NULL,
  released_at DATETIME DEFAULT NULL,
  prepared_at DATETIME DEFAULT NULL,
  INDEX idx_issued_documents_issued_at (issued_at),
  CONSTRAINT fk_issued_documents_resident FOREIGN KEY (resident_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_issued_documents_staff FOREIGN KEY (issued_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS document_payments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  payment_code VARCHAR(40) NOT NULL UNIQUE,
  request_code VARCHAR(30) DEFAULT NULL,
  document_number VARCHAR(60) DEFAULT NULL,
  resident_user_id INT UNSIGNED DEFAULT NULL,
  resident_name VARCHAR(150) NOT NULL,
  document_type VARCHAR(100) NOT NULL,
  amount_due DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  amount_paid DECIMAL(10,2) DEFAULT NULL,
  payment_method VARCHAR(40) NOT NULL DEFAULT 'Walk-in Cash',
  receipt_number VARCHAR(80) DEFAULT NULL,
  status ENUM('Pending', 'Paid', 'Voided') NOT NULL DEFAULT 'Pending',
  received_by INT UNSIGNED DEFAULT NULL,
  received_at DATETIME DEFAULT NULL,
  notes TEXT DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_document_payments_status (status),
  INDEX idx_document_payments_request (request_code),
  INDEX idx_document_payments_document (document_number),
  CONSTRAINT fk_document_payments_resident FOREIGN KEY (resident_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_document_payments_receiver FOREIGN KEY (received_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS resident_requirements (
  resident_id INT UNSIGNED NOT NULL,
  requirement_key VARCHAR(40) NOT NULL,
  status VARCHAR(30) NOT NULL DEFAULT 'Not Submitted',
  remarks TEXT NOT NULL,
  updated_by INT UNSIGNED NOT NULL,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (resident_id, requirement_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS sms_verifications (
  user_id INT UNSIGNED PRIMARY KEY,
  code_hash VARCHAR(255) NOT NULL DEFAULT '',
  token_hash VARCHAR(64) NOT NULL DEFAULT '',
  mobile VARCHAR(30) NOT NULL DEFAULT '',
  expires_at BIGINT NOT NULL DEFAULT 0,
  last_sent BIGINT NOT NULL DEFAULT 0,
  window_start BIGINT NOT NULL DEFAULT 0,
  sends INT NOT NULL DEFAULT 0,
  attempts INT NOT NULL DEFAULT 0,
  verified_mobile VARCHAR(30) NOT NULL DEFAULT '',
  verified_at BIGINT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS record_attachments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  record_type VARCHAR(20) NOT NULL,
  record_code VARCHAR(100) NOT NULL,
  owner_id INT NOT NULL,
  filename VARCHAR(255) NOT NULL,
  mime_type VARCHAR(50) NOT NULL,
  content_base64 LONGTEXT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX record_lookup (record_type, record_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS complaints (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  complaint_code VARCHAR(30) NOT NULL UNIQUE,
  resident_user_id INT UNSIGNED DEFAULT NULL,
  resident_name VARCHAR(150) NOT NULL,
  subject VARCHAR(180) NOT NULL,
  category VARCHAR(100) DEFAULT NULL,
  details TEXT,
  location VARCHAR(180) DEFAULT NULL,
  remarks TEXT DEFAULT NULL,
  intake_source VARCHAR(20) NOT NULL DEFAULT 'Online',
  contact_mobile VARCHAR(30) DEFAULT NULL,
  status ENUM('Open', 'In Progress', 'Resolved', 'Closed') NOT NULL DEFAULT 'Open',
  filed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  archived_at DATETIME DEFAULT NULL,
  INDEX idx_complaints_status (status),
  INDEX idx_complaints_resident (resident_user_id),
  CONSTRAINT fk_complaints_resident FOREIGN KEY (resident_user_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS feedbacks (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  feedback_code VARCHAR(30) NOT NULL UNIQUE,
  resident_user_id INT UNSIGNED DEFAULT NULL,
  resident_name VARCHAR(150) NOT NULL,
  category VARCHAR(100) DEFAULT NULL,
  message TEXT NOT NULL,
  image_file_name VARCHAR(255) DEFAULT NULL,
  image_path VARCHAR(255) DEFAULT NULL,
  rating TINYINT UNSIGNED DEFAULT NULL,
  is_anonymous TINYINT(1) NOT NULL DEFAULT 0,
  status ENUM('Unread', 'Read', 'Archived') NOT NULL DEFAULT 'Unread',
  submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  archived_at DATETIME DEFAULT NULL,
  INDEX idx_feedbacks_status (status),
  INDEX idx_feedbacks_resident (resident_user_id),
  CONSTRAINT fk_feedbacks_resident FOREIGN KEY (resident_user_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS sms_logs (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  recipient_name VARCHAR(150) NOT NULL,
  recipient_mobile VARCHAR(30) DEFAULT NULL,
  message_type VARCHAR(100) NOT NULL,
  message TEXT NOT NULL,
  status ENUM('Sent', 'Pending', 'Failed') NOT NULL DEFAULT 'Sent',
  sent_by INT UNSIGNED DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  source VARCHAR(20) NOT NULL DEFAULT 'Legacy',
  delivery_state VARCHAR(30) DEFAULT NULL,
  failure_reason VARCHAR(255) DEFAULT NULL,
  provider_id VARCHAR(150) DEFAULT NULL,
  delivery_key VARCHAR(100) DEFAULT NULL,
  retry_of INT UNSIGNED DEFAULT NULL,
  request_code VARCHAR(30) DEFAULT NULL,
  UNIQUE KEY uq_sms_delivery_key (delivery_key),
  INDEX idx_sms_logs_created_at (created_at),
  CONSTRAINT fk_sms_logs_user FOREIGN KEY (sent_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS qr_scan_logs (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  resident_user_id INT UNSIGNED DEFAULT NULL,
  resident_code VARCHAR(30) NOT NULL,
  resident_name VARCHAR(150) NOT NULL,
  result ENUM('Verified', 'Not Found', 'Inactive') NOT NULL DEFAULT 'Verified',
  scanned_by INT UNSIGNED DEFAULT NULL,
  scanned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_qr_scan_logs_scanned_at (scanned_at),
  CONSTRAINT fk_qr_scan_logs_resident FOREIGN KEY (resident_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_qr_scan_logs_staff FOREIGN KEY (scanned_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS roles (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL UNIQUE,
  description VARCHAR(255) DEFAULT NULL,
  permissions TEXT DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS audit_logs (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED DEFAULT NULL,
  user_name VARCHAR(150) NOT NULL,
  user_role VARCHAR(100) NOT NULL,
  action VARCHAR(255) NOT NULL,
  module VARCHAR(100) NOT NULL,
  status ENUM('Success', 'Warning', 'Error') NOT NULL DEFAULT 'Success',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_audit_logs_created_at (created_at),
  CONSTRAINT fk_audit_logs_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS backups (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  filename VARCHAR(180) NOT NULL,
  created_by VARCHAR(150) NOT NULL,
  status ENUM('Complete', 'Failed', 'Pending') NOT NULL DEFAULT 'Complete',
  file_size VARCHAR(50) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS system_settings (
  setting_key VARCHAR(80) PRIMARY KEY,
  setting_value TEXT,
  updated_by INT UNSIGNED DEFAULT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_system_settings_user FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS notification_seen_state (
  user_id INT NOT NULL,
  state_kind VARCHAR(12) NOT NULL,
  notification_key VARCHAR(190) NOT NULL,
  PRIMARY KEY (user_id, state_kind, notification_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO roles (name, description, permissions) VALUES
  ('Barangay Secretary', 'Main administrative officer', 'Dashboard, Resident Management, Document Requests, Reports'),
  ('Barangay Treasurer', 'Financial management', 'Dashboard, Reports, System Settings'),
  ('Barangay Health Worker', 'Health services coordination', 'Dashboard, Resident Management, SMS Alerts'),
  ('Barangay Tanod', 'Security and QR verification', 'Dashboard, QR Resident Verification, Complaints'),
  ('Staff Encoder', 'Data entry operations', 'Dashboard, Resident Management, Document Requests, Complaints'),
  ('Document Processor', 'Document request handling', 'Dashboard, Document Requests');

INSERT IGNORE INTO users
  (user_code, role, full_name, position_title, username, mobile, password_hash, status)
VALUES
  ('ADM-001', 'admin', 'Christian Deloy', 'Administrator', 'christiandeloy@luna.gov', '09506972820', '$2y$10$cXaZpbIM9C10NY9ebRQAt.iaiZhwMClNXgrylDjBknsKWdEzWGJhm', 'Active');
