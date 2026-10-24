# QR Resident Verification & SMS Alerts Implementation Guide

## Overview

This guide provides comprehensive instructions for implementing the **QR Resident Verification** and **SMS Alerts** features in the Barangay Luna Smart Barangay Portal.

---

## Table of Contents

1. [QR Resident Verification Feature](#qr-resident-verification-feature)
2. [SMS Alerts Feature](#sms-alerts-feature)
3. [Database Schema](#database-schema)
4. [Required Libraries](#required-libraries)
5. [Configuration](#configuration)
6. [API Endpoints](#api-endpoints)
7. [Integration Steps](#integration-steps)
8. [Usage Examples](#usage-examples)

---

## QR Resident Verification Feature

### Overview
The QR Resident Verification feature allows barangay staff to:
- Scan resident QR codes for quick identity verification
- View resident profiles and details
- Manage QR codes (reprint, download, update status)
- Track QR scan history and logs

### Files Created
- **View**: `includes/views/qr-verification.php`
- **Helper**: `includes/qr-sms-helpers.php` (QRVerificationHelper class)
- **JavaScript**: `js/qr-sms.js` (QRCodeScanner, QRCodeGenerator classes)

### Features

#### 1. QR Scanner
- Real-time camera-based QR code scanning
- Manual QR code/Resident ID input option
- Live preview of scanned results

#### 2. Verification Results
- Displays resident information:
  - Full Name
  - Resident ID
  - Contact Number
  - Household Number
  - Purok/Sitio
  - Address
  - QR Status (Active, Inactive, Transferred, Deceased)
- Verification status indicator

#### 3. QR Code Management
- **View Profile**: Access complete resident profile
- **Reprint QR**: Generate a new QR code for lost/damaged codes
- **Download QR**: Save QR code as image
- **Update Status**: Change QR status with reason logging

#### 4. QR Status Tracking
- Status options: Active, Inactive, Transferred, Deceased
- Status update history
- Reason logging

#### 5. Scan Logs
- Records all QR scans
- Tracks: Resident name, ID, staff member, date/time, verification result
- Searchable and filterable logs

---

## SMS Alerts Feature

### Overview
The SMS Alerts feature allows Admin to:
- Send SMS announcements and emergency alerts to residents
- Send status updates on barangay services
- Create and manage SMS templates
- Track SMS delivery status

### Files Created
- **View**: `includes/views/sms-alerts.php`
- **Helper**: `includes/qr-sms-helpers.php` (SMSAlertsHelper class)
- **JavaScript**: `js/qr-sms.js` (SMSAlerts class)

### Features

#### 1. Send SMS Message
- Message types:
  - Announcement
  - Emergency Alert
  - Request Status Update
  - Reminder
  - Community Activity
  - Custom Message

#### 2. Template System
- Pre-built SMS templates for common messages
- Categories:
  - Barangay Assembly Notice
  - Flood Warning Alert
  - Fire Alert
  - Typhoon Advisory
  - Evacuation Notice
  - Certificate Ready for Pickup
  - Service Request Approved
  - Service Request Rejected

#### 3. Recipient Targeting
- Individual resident
- Household
- Purok/Sitio
- Bulk send (all residents)

#### 4. Scheduling
- Immediate sending
- Schedule for later delivery
- Recurring messages (optional)

#### 5. Priority Levels
- Normal
- High
- Urgent (Emergency)

#### 6. SMS History & Logs
- Tracks all SMS communications
- Status tracking: Sent, Failed, Pending
- Searchable by recipient, date, status

---

## Database Schema

### QR Verification Tables

#### `qr_codes`
```sql
CREATE TABLE qr_codes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    resident_id VARCHAR(50) NOT NULL UNIQUE,
    qr_code VARCHAR(100) NOT NULL UNIQUE,
    qr_image_url LONGTEXT,
    status ENUM('Active', 'Inactive', 'Transferred', 'Deceased') DEFAULT 'Active',
    status_reason TEXT,
    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status_updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (resident_id) REFERENCES residents(id),
    INDEX (status),
    INDEX (generated_at)
);
```

#### `qr_scan_logs`
```sql
CREATE TABLE qr_scan_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    resident_id VARCHAR(50) NOT NULL,
    staff_id VARCHAR(50) NOT NULL,
    qr_code VARCHAR(100),
    scan_result ENUM('Verified', 'Not Found', 'Inactive') DEFAULT 'Verified',
    scanned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (resident_id) REFERENCES residents(id),
    FOREIGN KEY (staff_id) REFERENCES users(id),
    INDEX (resident_id),
    INDEX (staff_id),
    INDEX (scanned_at)
);
```

### SMS Alerts Tables

#### `sms_logs`
```sql
CREATE TABLE sms_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    recipient_id VARCHAR(50),
    phone_number VARCHAR(15) NOT NULL,
    message_content TEXT NOT NULL,
    message_type ENUM('announcement', 'emergency', 'request_update', 'reminder', 'community_activity', 'custom') DEFAULT 'custom',
    staff_id VARCHAR(50) NOT NULL,
    status ENUM('Sent', 'Failed', 'Pending') DEFAULT 'Pending',
    sms_id VARCHAR(100),
    sent_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (recipient_id) REFERENCES residents(id),
    FOREIGN KEY (staff_id) REFERENCES users(id),
    INDEX (recipient_id),
    INDEX (phone_number),
    INDEX (status),
    INDEX (created_at)
);
```

#### `sms_templates`
```sql
CREATE TABLE sms_templates (
    id INT PRIMARY KEY AUTO_INCREMENT,
    template_name VARCHAR(255) NOT NULL,
    template_content TEXT NOT NULL,
    category ENUM('announcement', 'emergency', 'request_update', 'reminder') DEFAULT 'custom',
    created_by VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX (category),
    INDEX (created_at)
);
```

#### `scheduled_sms`
```sql
CREATE TABLE scheduled_sms (
    id INT PRIMARY KEY AUTO_INCREMENT,
    recipient_id VARCHAR(50),
    phone_number VARCHAR(15) NOT NULL,
    message_content TEXT NOT NULL,
    scheduled_time DATETIME NOT NULL,
    status ENUM('Scheduled', 'Sent', 'Failed', 'Cancelled') DEFAULT 'Scheduled',
    staff_id VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (recipient_id) REFERENCES residents(id),
    FOREIGN KEY (staff_id) REFERENCES users(id),
    INDEX (scheduled_time),
    INDEX (status)
);
```

---

## Required Libraries

### QR Scanning
To enable camera-based QR code scanning, add this library to your HTML header:

```html
<!-- jsQR Library for QR decoding -->
<script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.js"></script>
```

### QR Code Generation
For generating QR codes, the application uses:

**Option 1: Server-side (Recommended for Production)**
```bash
composer require endroid/qr-code
```

**Option 2: Client-side CDN**
```html
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
```

### SMS Integration
Integrate with an SMS service provider:

**Recommended Options:**
1. **Twilio** - https://www.twilio.com/
   ```bash
   composer require twilio/sdk
   ```

2. **Nexmo/Vonage** - https://www.vonage.com/
   ```bash
   composer require vonage/client
   ```

3. **Local SMS Gateway** - Use local SMS provider in Philippines
   - Example: DegMobile, Bulkgate, etc.

---

## Configuration

### 1. Database Setup
Run the SQL scripts above to create the required tables.

### 2. Environment Variables
Add to your `.env` file:

```env
# QR Configuration
QR_ENABLED=true
QR_DEFAULT_SIZE=300

# SMS Configuration
SMS_ENABLED=true
SMS_PROVIDER=twilio
SMS_ACCOUNT_SID=your_twilio_account_sid
SMS_AUTH_TOKEN=your_twilio_auth_token
SMS_FROM_NUMBER=+1234567890
SMS_MAX_LENGTH=160

# Local SMS Gateway (if using local provider)
SMS_GATEWAY_URL=http://localhost:3000/sms
SMS_GATEWAY_API_KEY=your_api_key
```

### 3. PHP Configuration
Create a configuration file `includes/config-qr-sms.php`:

```php
<?php
// QR Verification Settings
define('QR_ENABLED', getenv('QR_ENABLED') !== false);
define('QR_DEFAULT_SIZE', (int) getenv('QR_DEFAULT_SIZE') ?: 300);
define('QR_CODE_PREFIX', 'QR');

// SMS Alerts Settings
define('SMS_ENABLED', getenv('SMS_ENABLED') !== false);
define('SMS_PROVIDER', getenv('SMS_PROVIDER') ?: 'twilio');
define('SMS_ACCOUNT_SID', getenv('SMS_ACCOUNT_SID'));
define('SMS_AUTH_TOKEN', getenv('SMS_AUTH_TOKEN'));
define('SMS_FROM_NUMBER', getenv('SMS_FROM_NUMBER'));
define('SMS_MAX_LENGTH', 160);

// Features
define('ALLOW_QR_SCANNING', true);
define('ALLOW_QR_DOWNLOAD', true);
define('ALLOW_QR_PRINT', true);
define('ALLOW_SMS_SCHEDULING', true);
?>
```

---

## API Endpoints

### QR Verification Endpoints

#### Verify Resident by QR Code
```
POST /api/qr/verify
Content-Type: application/json

{
    "qr_code": "QR-RES-101-2026"
}

Response:
{
    "success": true,
    "resident": {
        "id": "RES-101",
        "name": "Resident Record 1",
        "contact": "09171234567",
        "household": "HH-001",
        "purok": "Purok 1",
        "address": "123 Luna Street",
        "qr_status": "Active"
    }
}
```

#### Log QR Scan
```
POST /api/qr/scan-log
Content-Type: application/json

{
    "resident_id": "RES-101",
    "staff_id": "STF-001",
    "scan_result": "Verified"
}

Response:
{
    "success": true,
    "log_id": "LOG-123456"
}
```

#### Update QR Status
```
PUT /api/qr/status/:resident_id
Content-Type: application/json

{
    "new_status": "Transferred",
    "reason": "Resident moved to another barangay"
}

Response:
{
    "success": true,
    "message": "QR status updated"
}
```

### SMS Alerts Endpoints

#### Send SMS
```
POST /api/sms/send
Content-Type: application/json

{
    "phone": "09171234567",
    "message": "Barangay Assembly on May 22",
    "message_type": "announcement",
    "priority": "normal"
}

Response:
{
    "success": true,
    "sms_id": "SMS-123456",
    "status": "Sent"
}
```

#### Send Bulk SMS
```
POST /api/sms/send-bulk
Content-Type: application/json

{
    "recipients": [
        {"name": "Resident Record 1", "phone": "09171234567"},
        {"name": "Resident Record 2", "phone": "09189876543"}
    ],
    "message": "Medical mission on May 28",
    "message_type": "announcement"
}

Response:
{
    "total": 2,
    "sent": 2,
    "failed": 0,
    "logs": [...]
}
```

#### Schedule SMS
```
POST /api/sms/schedule
Content-Type: application/json

{
    "phone": "09171234567",
    "message": "Reminder: Barangay Assembly tomorrow",
    "scheduled_time": "2026-05-21 09:00:00"
}

Response:
{
    "success": true,
    "sms_id": "SCHEDULED-123456",
    "status": "Scheduled"
}
```

#### Get SMS Templates
```
GET /api/sms/templates?category=emergency

Response:
{
    "templates": [
        {
            "id": "template-2",
            "name": "Flood Warning Alert",
            "content": "ALERT: Severe flooding expected...",
            "category": "emergency"
        }
    ]
}
```

---

## Integration Steps

### Step 1: Include Required Files in dashboard.php

```php
<?php
// At the top of dashboard.php
require_once __DIR__ . '/includes/qr-sms-helpers.php';
require_once __DIR__ . '/includes/config-qr-sms.php';

// ... rest of code
?>
```

### Step 2: Include JavaScript Files in View

Add to your view files or header:

```html
<!-- jsQR for QR scanning -->
<script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.js"></script>

<!-- QR & SMS Module -->
<script src="js/qr-sms.js"></script>
```

### Step 3: Create API Endpoints

Create file `api/qr.php`:

```php
<?php
require_once __DIR__ . '/../includes/qr-sms-helpers.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if ($method === 'POST' && strpos($path, '/verify') !== false) {
    $data = json_decode(file_get_contents('php://input'), true);
    $resident = QRVerificationHelper::getResidentByQRCode($data['qr_code']);
    
    echo json_encode([
        'success' => !!$resident,
        'resident' => $resident
    ]);
}
// ... other endpoints
?>
```

Create file `api/sms.php`:

```php
<?php
require_once __DIR__ . '/../includes/qr-sms-helpers.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if ($method === 'POST' && strpos($path, '/send') !== false) {
    $data = json_decode(file_get_contents('php://input'), true);
    $result = SMSAlertsHelper::sendSMS(
        $data['phone'],
        $data['message'],
        $_SESSION['user_id'] ?? 'system',
        $data['message_type'] ?? 'custom'
    );
    
    echo json_encode($result);
}
// ... other endpoints
?>
```

### Step 4: Generate QR Codes on Resident Registration

In your resident creation/registration code:

```php
<?php
// After resident is registered
$resident_id = 'RES-101';
$resident_name = 'Resident Record 1';

// Generate QR code
$qr_result = generateQROnResidentCreation($resident_id, $resident_name);

if ($qr_result['success']) {
    echo "QR code generated: " . $qr_result['qr_code'];
}
?>
```

---

## Usage Examples

### Example 1: Manual QR Verification in View

```javascript
// In your qr-verification.php view (already implemented)
document.getElementById('verifyManualBtn').addEventListener('click', () => {
    const input = document.getElementById('qrManualInput').value.trim();
    
    if (!input) {
        alert('Please enter a resident ID or QR code');
        return;
    }
    
    // Call backend to verify
    fetch('/api/qr/verify', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ qr_code: input })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            displayVerificationResult(data.resident);
        } else {
            alert('Resident not found');
        }
    });
});
```

### Example 2: Send SMS Announcement

```javascript
// Using the SMSAlerts class
const smsAlert = new SMSAlerts({
    apiEndpoint: '/api/sms'
});

smsAlert.sendBulkSMS([
    { name: 'Resident Record 1', phone: '09171234567' },
    { name: 'Resident Record 2', phone: '09189876543' }
], 'Barangay Assembly scheduled for May 22, 2026', {
    messageType: 'announcement',
    priority: 'normal'
})
.then(result => {
    console.log(`SMS sent: ${result.sent}/${result.total}`);
})
.catch(error => console.error('Error:', error));
```

### Example 3: Initialize QR Scanner

```javascript
// Initialize QR verification
const qrVerification = new QRVerification({
    apiEndpoint: '/api/qr'
});

qrVerification.init();

// Listen to QR verification event
window.addEventListener('qr-verified', (event) => {
    console.log('Resident verified:', event.detail.resident);
});
```

---

## Testing

### QR Code Testing
1. Use online QR code generator to create test codes
2. Test with different resident IDs
3. Verify scan logs are recorded

### SMS Testing
1. Use Twilio test credentials for development
2. Test all message types
3. Verify SMS history logs

---

## Security Considerations

1. **Authentication**: Require login for both QR and SMS features
2. **Authorization**: Only authorized staff can access QR verification
3. **Data Validation**: Validate all inputs on backend
4. **Rate Limiting**: Limit SMS sending to prevent abuse
5. **Logging**: Log all QR scans and SMS sends for audit trail
6. **Encryption**: Encrypt sensitive resident data
7. **API Security**: Use HTTPS and API tokens

---

## Troubleshooting

### QR Scanner Not Working
- Check browser camera permissions
- Verify jsQR library is loaded
- Test on HTTPS (camera access requires HTTPS)

### SMS Not Sending
- Verify SMS provider credentials
- Check phone number format
- Verify SMS balance/quota
- Check message length (max 160 characters)

### Database Errors
- Verify all tables are created
- Check foreign key relationships
- Review MySQL error logs

---

## Support & Maintenance

- Keep dependencies updated
- Monitor SMS service usage and costs
- Regular backup of QR and SMS logs
- Review security logs periodically
- Test recovery procedures

---

**Last Updated**: May 2026  
**Version**: 1.0  
**Author**: Barangay Luna MIS Team
