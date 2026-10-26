/**
 * QR Code Scanner Module
 * Barangay Luna MIS
 * Uses jsQR library for QR code decoding
 */

class QRCodeScanner {
  constructor(options = {}) {
    this.options = {
      videoElementId: options.videoElementId || 'qrVideoPreview',
      canvasElementId: options.canvasElementId || 'qrCanvas',
      onScan: options.onScan || (() => {}),
      onError: options.onError || (() => {}),
      ...options
    };

    this.video = document.getElementById(this.options.videoElementId);
    this.stream = null;
    this.scanning = false;
    this.worker = null;
  }

  /**
   * Initialize camera and start scanning
   */
  async startScanning() {
    try {
      // Request camera access
      this.stream = await navigator.mediaDevices.getUserMedia({
        video: {
          facingMode: 'environment',
          width: { ideal: 1280 },
          height: { ideal: 720 }
        }
      });

      this.video.srcObject = this.stream;
      this.video.play();

      this.scanning = true;
      this.scanQR();
    } catch (err) {
      console.error('Camera access error:', err);
      this.options.onError({
        message: 'Unable to access camera. Please check camera permissions.',
        error: err
      });
    }
  }

  /**
   * Stop camera and scanning
   */
  stopScanning() {
    this.scanning = false;

    if (this.stream) {
      this.stream.getTracks().forEach(track => track.stop());
      this.stream = null;
    }

    if (this.video) {
      this.video.srcObject = null;
    }
  }

  /**
   * Main scanning loop
   */
  scanQR() {
    if (!this.scanning) return;

    const canvas = document.createElement('canvas');
    const ctx = canvas.getContext('2d');

    if (this.video.readyState === this.video.HAVE_ENOUGH_DATA) {
      canvas.width = this.video.videoWidth;
      canvas.height = this.video.videoHeight;

      ctx.drawImage(this.video, 0, 0, canvas.width, canvas.height);

      try {
        const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
        
        // This requires jsQR library to be loaded
        if (typeof jsQR === 'undefined') {
          console.warn('jsQR library not loaded. QR scanning will not work.');
          this.options.onError({
            message: 'QR scanning library not loaded'
          });
          this.scanning = false;
          return;
        }

        const code = jsQR(imageData.data, imageData.width, imageData.height);

        if (code && code.data) {
          this.options.onScan({
            data: code.data,
            location: code.location
          });

          // Stop scanning after successful read
          this.stopScanning();
          return;
        }
      } catch (error) {
        console.error('QR scanning error:', error);
      }
    }

    // Continue scanning
    requestAnimationFrame(() => this.scanQR());
  }

  /**
   * Get camera permissions status
   */
  static async checkCameraPermission() {
    try {
      const result = await navigator.permissions.query({ name: 'camera' });
      return result.state;
    } catch (err) {
      return 'unknown';
    }
  }

  /**
   * Check if browser supports getUserMedia
   */
  static isSupported() {
    return !!(
      navigator.mediaDevices &&
      navigator.mediaDevices.getUserMedia
    );
  }
}

/**
 * QR Generator Module
 * Generate QR codes client-side
 */
class QRCodeGenerator {
  /**
   * Generate QR code image
   * @param {string} text - Text to encode
   * @param {number} size - Size of QR code in pixels
   * @returns {string} Data URL of QR code image
   */
  static generateDataURL(text, size = 300) {
    // Using qr-server.com API for simplicity
    // For production, use library like qrcode.js or endroid/qr-code
    const encoded = encodeURIComponent(text);
    return `https://api.qrserver.com/v1/create-qr-code/?size=${size}x${size}&data=${encoded}`;
  }

  /**
   * Download QR code
   * @param {string} qrText - Text encoded in QR
   * @param {string} filename - Download filename
   */
  static downloadQR(qrText, filename = 'qr-code.png') {
    const url = this.generateDataURL(qrText);
    
    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
  }

  /**
   * Print QR code
   * @param {string} qrText - Text encoded in QR
   * @param {string} title - Print title
   */
  static printQR(qrText, title = 'QR Code') {
    const url = this.generateDataURL(qrText, 400);
    
    const printWindow = window.open('', '', 'width=600,height=700');
    printWindow.document.write(`
      <!DOCTYPE html>
      <html>
      <head>
        <title>${title}</title>
        <style>
          body { font-family: Arial, sans-serif; text-align: center; padding: 2rem; }
          img { max-width: 400px; margin: 2rem 0; }
          .footer { margin-top: 2rem; font-size: 12px; color: #666; }
        </style>
      </head>
      <body>
        <h2>${title}</h2>
        <img src="${url}" alt="QR Code">
        <p style="font-family: monospace; font-size: 12px; word-break: break-all;">${qrText}</p>
        <div class="footer">
          <p>Barangay Luna MIS - QR Resident Verification</p>
          <p>Generated: ${new Date().toLocaleString('en-US', {hour12:true})}</p>
        </div>
      </body>
      </html>
    `);
    printWindow.document.close();
    printWindow.print();
  }
}

/**
 * QR Verification Module
 * Main module for QR verification functionality
 */
class QRVerification {
  constructor(options = {}) {
    this.options = {
      apiEndpoint: options.apiEndpoint || '/api/qr',
      ...options
    };

    this.scanner = null;
    this.currentResident = null;
  }

  /**
   * Initialize QR verification
   */
  init() {
    this.setupEventListeners();
  }

  /**
   * Setup event listeners
   */
  setupEventListeners() {
    // Scanner controls
    const startBtn = document.getElementById('startScannerBtn');
    const stopBtn = document.getElementById('stopScannerBtn');

    if (startBtn) {
      startBtn.addEventListener('click', () => this.initializeScanner());
    }

    if (stopBtn) {
      stopBtn.addEventListener('click', () => this.stopScanner());
    }
  }

  /**
   * Initialize QR scanner
   */
  initializeScanner() {
    if (!QRCodeScanner.isSupported()) {
      alert('Camera scanning is not supported on this device.');
      return;
    }

    this.scanner = new QRCodeScanner({
      videoElementId: 'qrVideoPreview',
      onScan: (result) => this.handleQRScan(result),
      onError: (error) => this.handleScanError(error)
    });

    this.scanner.startScanning();
  }

  /**
   * Stop QR scanner
   */
  stopScanner() {
    if (this.scanner) {
      this.scanner.stopScanning();
      this.scanner = null;
    }
  }

  /**
   * Handle QR scan result
   */
  handleQRScan(result) {
    const qrData = result.data;

    // Verify resident with backend
    this.verifyResident(qrData);
  }

  /**
   * Handle scan error
   */
  handleScanError(error) {
    console.error('Scan error:', error);
  }

  /**
   * Verify resident with backend
   */
  async verifyResident(qrData) {
    try {
      const response = await fetch(`${this.options.apiEndpoint}/verify`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({
          qr_code: qrData
        })
      });

      if (!response.ok) {
        throw new Error('Verification failed');
      }

      const data = await response.json();
      this.currentResident = data.resident;

      // Trigger custom event
      window.dispatchEvent(new CustomEvent('qr-verified', {
        detail: data
      }));
    } catch (error) {
      console.error('Verification error:', error);
      this.handleVerificationError(error);
    }
  }

  /**
   * Handle verification error
   */
  handleVerificationError(error) {
    alert('Resident verification failed. Please try again.');
  }

  /**
   * Get current verified resident
   */
  getCurrentResident() {
    return this.currentResident;
  }
}

/**
 * SMS Alerts Module
 */
class SMSAlerts {
  constructor(options = {}) {
    this.options = {
      apiEndpoint: options.apiEndpoint || '/api/sms',
      ...options
    };

    this.templates = [];
  }

  /**
   * Send SMS
   */
  async sendSMS(phone, message, options = {}) {
    try {
      const response = await fetch(`${this.options.apiEndpoint}/send`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({
          phone,
          message,
          message_type: options.messageType || 'custom',
          priority: options.priority || 'normal',
          scheduled_time: options.scheduledTime || null
        })
      });

      if (!response.ok) {
        throw new Error('SMS send failed');
      }

      return await response.json();
    } catch (error) {
      console.error('SMS send error:', error);
      throw error;
    }
  }

  /**
   * Send bulk SMS
   */
  async sendBulkSMS(recipients, message, options = {}) {
    try {
      const response = await fetch(`${this.options.apiEndpoint}/send-bulk`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({
          recipients,
          message,
          message_type: options.messageType || 'custom',
          priority: options.priority || 'normal'
        })
      });

      if (!response.ok) {
        throw new Error('Bulk SMS send failed');
      }

      return await response.json();
    } catch (error) {
      console.error('Bulk SMS send error:', error);
      throw error;
    }
  }

  /**
   * Get SMS templates
   */
  async getTemplates(category = null) {
    try {
      const url = new URL(`${this.options.apiEndpoint}/templates`, window.location.origin);
      if (category) {
        url.searchParams.append('category', category);
      }

      const response = await fetch(url);

      if (!response.ok) {
        throw new Error('Failed to fetch templates');
      }

      this.templates = await response.json();
      return this.templates;
    } catch (error) {
      console.error('Template fetch error:', error);
      throw error;
    }
  }

  /**
   * Save template
   */
  async saveTemplate(templateName, templateContent, category) {
    try {
      const response = await fetch(`${this.options.apiEndpoint}/templates`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({
          template_name: templateName,
          template_content: templateContent,
          category
        })
      });

      if (!response.ok) {
        throw new Error('Template save failed');
      }

      return await response.json();
    } catch (error) {
      console.error('Template save error:', error);
      throw error;
    }
  }
}

// Export for use in modules
if (typeof module !== 'undefined' && module.exports) {
  module.exports = {
    QRCodeScanner,
    QRCodeGenerator,
    QRVerification,
    SMSAlerts
  };
}
