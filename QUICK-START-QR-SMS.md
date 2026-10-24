<!-- Quick Start Guide for QR & SMS Features -->

# Quick Start: QR Resident Verification & SMS Alerts

## What's New?

Two new features have been added to the Barangay Luna MIS:

### 1. QR Resident Verification
- **Location**: Admin Sidebar → Operations → QR Resident Verification
- **URL**: `dashboard.php?role=admin&view=qr-verification`
- **Features**:
  - Scan resident QR codes using camera
  - Manual QR code/ID input
  - View resident profile from QR scan
  - Manage QR status (Active, Inactive, Transferred, Deceased)
  - Track scan history in logs

### 2. SMS Alerts
- **Location**: Admin Sidebar → Operations → SMS Alerts
- **URL**: `dashboard.php?role=admin&view=sms-alerts`
- **Features**:
  - Send SMS announcements, emergency alerts, and reminders
  - Choose recipients (individual, household, purok, bulk)
  - Use pre-built SMS templates
  - Schedule SMS for later delivery
  - Track SMS history and delivery status

---

## Files Created

```
includes/
  ├── views/
  │   ├── qr-verification.php          (QR verification UI)
  │   └── sms-alerts.php               (SMS alerts UI)
  ├── qr-sms-helpers.php               (Backend logic & helpers)
  └── config-qr-sms.php                (Configuration - create this file)

js/
  └── qr-sms.js                        (QR scanning, SMS, JavaScript utilities)

QR-SMS-IMPLEMENTATION-GUIDE.md         (Complete documentation)
```

---

## Quick Integration (5 Steps)

### Step 1: Include Helper File
Add to the top of `dashboard.php`:
```php
<?php
require_once __DIR__ . '/includes/qr-sms-helpers.php';
```

### Step 2: Create Configuration File
Create `includes/config-qr-sms.php`:
```php
<?php
define('QR_ENABLED', true);
define('SMS_ENABLED', true);
define('SMS_MAX_LENGTH', 160);
?>
```

### Step 3: Add Scripts to Header
Add to `includes/header.php` or just before `</body>`:
```html
<script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.js"></script>
<script src="js/qr-sms.js"></script>
```

### Step 4: Create Database Tables
Run the SQL script from `QR-SMS-IMPLEMENTATION-GUIDE.md` to create:
- `qr_codes`
- `qr_scan_logs`
- `sms_logs`
- `sms_templates`
- `scheduled_sms`

### Step 5: Setup API Endpoints (Optional but Recommended)
Create `api/qr.php` and `api/sms.php` files for backend requests.

---

## Features at a Glance

### QR Verification Features
1. ✅ Real-time QR code scanning via camera
2. ✅ Manual QR code/Resident ID input
3. ✅ Display resident verification results
4. ✅ Download QR codes
5. ✅ Print QR codes
6. ✅ Update QR status with reason logging
7. ✅ View complete resident profile
8. ✅ Maintain QR scan history with staff tracking
9. ✅ QR status types: Active, Inactive, Transferred, Deceased
10. ✅ Searchable QR scan logs

### SMS Alerts Features
1. ✅ Send SMS announcements
2. ✅ Send emergency alerts
3. ✅ Send status updates
4. ✅ Send reminders
5. ✅ Target individual residents
6. ✅ Target households
7. ✅ Target purok/sitio groups
8. ✅ Bulk SMS to all residents
9. ✅ Pre-built message templates
10. ✅ Schedule SMS for later
11. ✅ Set priority levels (Normal, High, Urgent)
12. ✅ SMS history with delivery status
13. ✅ SMS status tracking (Sent, Failed, Pending)

---

## How to Use

### Using QR Verification

**Scanning a QR Code:**
1. Go to Admin Dashboard → QR Resident Verification
2. Click "Start Scanner"
3. Point camera at resident's QR code
4. System automatically verifies and displays resident info

**Manual Entry:**
1. Click the "QR Scanner" section
2. In "Manual QR Code Input", enter resident ID or QR code
3. Click "Verify Resident"

**Accessing Full Profile:**
1. After verification, click "View Full Profile" button
2. See complete resident information

**Updating QR Status:**
1. In QR Status & Management table, click "Status" button
2. Select new status (Active, Inactive, Transferred, Deceased)
3. Add reason if needed
4. Click "Update Status"

**Downloading QR:**
1. In QR Status & Management table, click "Download" button
2. QR code image will be saved

### Using SMS Alerts

**Sending a Quick SMS:**
1. Go to Admin Dashboard → SMS Alerts
2. Select "Message Type"
3. Enter "Message Content" (max 160 characters)
4. Choose "Recipient Type":
   - Individual: Select one resident
   - Household: Select a household
   - Purok: Select purok/sitio
   - Bulk: Send to all residents
5. Set priority if needed
6. Click "Preview" or "Send SMS"

**Using Templates:**
1. Select "Use Template (Optional)"
2. Choose from existing templates
3. Template text auto-fills in Message Content
4. Modify if needed
5. Send

**Creating a New Template:**
1. Click "+ Add New Template" button
2. Enter template name and category
3. Write template content
4. Click "Save Template"

**Scheduling SMS:**
1. After selecting all options, click "Schedule Send"
2. Set date and time
3. Click "Send SMS" button
4. SMS will be sent at scheduled time

---

## Mock Data for Testing

The features come with built-in mock data:

### Sample Residents (QR Verification)
- Resident Record 1 (RES-101) - Active
- Resident Record 2 (RES-102) - Inactive
- Resident Record 3 (RES-103) - Active

### Sample SMS Templates
- Barangay Assembly Notice
- Flood Warning Alert
- Fire Alert
- Typhoon Advisory
- Evacuation Notice
- Certificate Ready for Pickup
- Service Request Approved
- Service Request Rejected

### Sample SMS History
Pre-populated with example SMS logs for testing

---

## Browser Requirements

- **Camera Access**: Required for QR scanning
  - Recommended: Chrome 56+, Firefox 55+, Safari 11+, Edge 79+
  - HTTPS required for camera access
- **JavaScript**: Must be enabled
- **Modern Browser**: ES6+ support

---

## Security Notes

- Always validate inputs on backend
- Implement authentication checks
- Log all QR scans and SMS sends
- Use HTTPS for API endpoints
- Protect SMS credentials (API keys, tokens)
- Implement rate limiting for SMS sending
- Regular backup of QR and SMS logs

---

## Next Steps

1. ✅ Features are live in demo mode
2. 🔄 Connect to your MySQL database
3. 🔄 Setup SMS service provider (Twilio, Nexmo, etc.)
4. 🔄 Create API endpoints for backend operations
5. 🔄 Test with real data
6. 🔄 Deploy to production
7. 🔄 Monitor usage and performance

---

## Support

For detailed implementation guide, see: `QR-SMS-IMPLEMENTATION-GUIDE.md`

---

**Status**: Demo Mode ✅  
**Last Updated**: May 20, 2026  
**Version**: 1.0
